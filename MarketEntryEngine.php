<?php
/**
 * FreightIQ Marine :: Market Entry Intelligence Engine
 * Computes a dynamic Buy Now / Monitor / Preferred Window / Avoid recommendation
 * from forecast trend, confidence, volatility, congestion, seasonality and
 * vessel availability — never a static rule.
 */
require_once __DIR__ . '/ForecastEngine.php';

class MarketEntryEngine
{
    private PDO $pdo;
    private ForecastEngine $forecast;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; $this->forecast = new ForecastEngine($pdo); }

    public function evaluate(int $routeId, int $classId): array
    {
        $bundle = $this->forecast->predictBundle($routeId, $classId);
        if (isset($bundle['error'])) return $bundle;

        $f7 = $bundle['forecast_7']; $f30 = $bundle['forecast_30'];

        // congestion at destination
        $stmt = $this->pdo->prepare(
            "SELECT pc.congestion_index, pc.congestion_level FROM routes r
             JOIN port_congestion pc ON pc.port_id = r.destination_port_id
             WHERE r.id=? ORDER BY pc.recorded_at DESC LIMIT 1"
        );
        $stmt->execute([$routeId]);
        $cong = $stmt->fetch() ?: ['congestion_index'=>1.0,'congestion_level'=>'Low'];

        // vessel class availability
        $stmt2 = $this->pdo->prepare(
            "SELECT COUNT(*) avail FROM vessels WHERE class_id=? AND availability='Available'"
        );
        $stmt2->execute([$classId]);
        $availCount = (int)$stmt2->fetch()['avail'];

        $score = 50;
        // trend: falling rates -> better time to buy
        if ($f30['market_direction'] === 'Falling') $score += 20;
        elseif ($f30['market_direction'] === 'Rising') $score -= 20;
        // confidence: higher confidence -> more decisive score
        $score += ($f30['confidence_pct'] - 70) * 0.4;
        // volatility: high volatility -> caution
        $score -= $f30['volatility_pct'] * 1.5;
        // congestion: high congestion -> caution (delays inflate effective cost)
        $score -= ($cong['congestion_index'] - 1.0) * 25;
        // seasonality trough -> favourable
        if ($f30['seasonal_factor'] < -0.15) $score += 10;
        elseif ($f30['seasonal_factor'] > 0.15) $score -= 8;
        // vessel scarcity -> less flexible timing, push toward "monitor" rather than "avoid"
        if ($availCount === 0) $score -= 10;

        $score = max(0, min(100, $score));

        if ($score >= 70) $label = 'Buy Now';
        elseif ($score >= 50) $label = 'Preferred Entry Window (next 7–14 days)';
        elseif ($score >= 30) $label = 'Monitor';
        else $label = 'Avoid / High-Risk Window';

        $reasons = [];
        $reasons[] = "30-day market direction: {$f30['market_direction']} (7-day: {$f7['market_direction']}).";
        $reasons[] = "Forecast confidence: {$f30['confidence_pct']}%, volatility: {$f30['volatility_pct']}%.";
        $reasons[] = "Destination port congestion: {$cong['congestion_level']} (index {$cong['congestion_index']}x).";
        $reasons[] = $availCount > 0
            ? "{$availCount} vessel(s) of this class currently available."
            : "No vessels of this class currently marked Available — factor in positioning lead time.";

        return [
            'score' => round($score,1),
            'label' => $label,
            'reasons' => $reasons,
            'forecast_7' => $f7,
            'forecast_30' => $f30,
            'congestion' => $cong,
            'available_vessels' => $availCount,
        ];
    }
}
