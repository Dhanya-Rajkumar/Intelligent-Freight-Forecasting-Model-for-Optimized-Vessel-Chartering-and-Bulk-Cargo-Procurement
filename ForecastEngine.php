<?php
/**
 * FreightIQ Marine :: Forecast Engine
 * -----------------------------------------------------------------------
 * Explainable statistical baseline model (model_key: weighted_regression_v1).
 *
 * Architecture note: this class exposes a stable predict()/counterfactual()
 * contract so ARIMA / SARIMA / Prophet / XGBoost / LSTM implementations can
 * later be dropped in (e.g. as a Python microservice called over HTTP) by
 * implementing the same interface — see forecast_models table, where those
 * model keys already exist with is_active=0 pending real integration.
 *
 * All output here is derived from `freight_rates` and `market_indicators`,
 * which are clearly seeded as "Simulated (demo)" data — see data_source
 * columns. This is never presented as live market data.
 */
class ForecastEngine
{
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    private function getHistory(int $routeId, int $classId, int $lookbackRows = 150): array
    {
        // NOTE: intentionally row-based, not "WHERE rate_date >= NOW() - N days".
        // A clock-relative filter would silently return zero rows once the wall
        // clock moves past the seeded data's range — row-based lookback is robust
        // regardless of when this is actually run relative to when it was seeded.
        $stmt = $this->pdo->prepare(
            "SELECT rate_date, rate_usd_per_tonne FROM (
                SELECT rate_date, rate_usd_per_tonne FROM freight_rates
                WHERE route_id = ? AND vessel_class_id = ?
                ORDER BY rate_date DESC LIMIT ?
             ) recent ORDER BY rate_date ASC"
        );
        $stmt->bindValue(1, $routeId, PDO::PARAM_INT);
        $stmt->bindValue(2, $classId, PDO::PARAM_INT);
        $stmt->bindValue(3, $lookbackRows, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function getMarketWindow(int $rows = 10): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT AVG(bunker_price_usd_per_tonne) bunker, AVG(demand_index) demand, AVG(supply_index) supply,
                    AVG(baltic_dry_index) bdi
             FROM (SELECT * FROM market_indicators ORDER BY indicator_date DESC LIMIT ?) recent"
        );
        $stmt->bindValue(1, $rows, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    private function weightedLinearRegression(array $x, array $y, array $w): array
    {
        $sw=$swx=$swy=$swxx=$swxy=0.0; $n=count($x);
        for ($i=0;$i<$n;$i++){ $sw+=$w[$i]; $swx+=$w[$i]*$x[$i]; $swy+=$w[$i]*$y[$i]; $swxx+=$w[$i]*$x[$i]*$x[$i]; $swxy+=$w[$i]*$x[$i]*$y[$i]; }
        $den = $sw*$swxx - $swx*$swx;
        if (abs($den) < 1e-9) return [$swy/max($sw,1e-9), 0.0];
        $b = ($sw*$swxy - $swx*$swy)/$den;
        $a = ($swy - $b*$swx)/$sw;
        return [$a,$b];
    }

    private function stddev(array $vals): float {
        $n = count($vals); if ($n<2) return 0.0;
        $m = array_sum($vals)/$n;
        return sqrt(array_sum(array_map(fn($v)=>($v-$m)**2,$vals)) / ($n-1));
    }

    /** Predict for a single horizon (7/14/30 or custom) */
    public function predictHorizon(int $routeId, int $classId, int $horizonDays): array
    {
        $hist = $this->getHistory($routeId, $classId);
        if (count($hist) < 10) return ['error' => 'Insufficient historical data.'];

        $n = count($hist);
        $x = range(0, $n-1);
        $y = array_map(fn($r)=>(float)$r['rate_usd_per_tonne'], $hist);
        $w = array_map(fn($i)=>exp(-0.002*($n-1-$i)), $x);
        [$a,$b] = $this->weightedLinearRegression($x,$y,$w);

        $residuals = []; foreach ($x as $i) $residuals[] = $y[$i] - ($a+$b*$x[$i]);
        $seasonalAmp = $this->stddev($residuals) * 0.4;

        $targetX = $n - 1 + $horizonDays;
        $trendValue = $a + $b*$targetX;
        $targetDate = new DateTime(); $targetDate->modify("+{$horizonDays} days");
        $doy = (int)$targetDate->format('z');
        $seasonalFactor = sin(($doy/365.0)*2*M_PI + 1.2);
        $seasonalAdj = $seasonalAmp * $seasonalFactor;

        $market = $this->getMarketWindow(20);
        $demandAdj = 0.0; $supplyAdj = 0.0; $bunkerNote = '';
        if ($market) {
            $demandAdj = ($market['demand'] - 1.0) * $trendValue * 0.25;
            $supplyAdj = -($market['supply'] - 1.0) * $trendValue * 0.15;
        }

        $predicted = max($trendValue + $seasonalAdj + $demandAdj + $supplyAdj, 1.0);

        $volatility = $this->stddev(array_slice($residuals, -60));
        $horizonScale = sqrt($horizonDays/30.0);
        $margin = 1.28 * $volatility * $horizonScale;
        $lower = max($predicted - $margin, 0.5);
        $upper = $predicted + $margin;

        $currentRate = end($y);
        $volatilityPct = $volatility / max(array_sum($y)/$n, 1e-6) * 100;
        $confidence = max(50, min(96, 96 - $volatilityPct*3 - ($horizonDays>60?8:0)));

        $direction = 'Stable';
        if ($predicted > $currentRate * 1.02) $direction = 'Rising';
        elseif ($predicted < $currentRate * 0.98) $direction = 'Falling';

        return [
            'horizon_days' => $horizonDays,
            'current_rate' => round($currentRate,2),
            'predicted_rate' => round($predicted,2),
            'lower_bound' => round($lower,2),
            'upper_bound' => round($upper,2),
            'confidence_pct' => round($confidence,1),
            'market_direction' => $direction,
            'volatility_pct' => round($volatilityPct,2),
            'trend_slope' => round($b,4),
            'seasonal_factor' => round($seasonalFactor,3),
            'sample_size' => $n,
            'is_simulated' => true,
            'data_source' => 'Simulated (demo) historical time series',
        ];
    }

    /** Multi-horizon bundle: 7 / 14 / 30 day, plus historical series for charting */
    public function predictBundle(int $routeId, int $classId): array
    {
        $hist = $this->getHistory($routeId, $classId, 120);
        if (count($hist) < 10) return ['error' => 'Insufficient historical data for this route/vessel combination.'];

        return [
            'forecast_7' => $this->predictHorizon($routeId, $classId, 7),
            'forecast_14' => $this->predictHorizon($routeId, $classId, 14),
            'forecast_30' => $this->predictHorizon($routeId, $classId, 30),
            'model_used' => 'Weighted Trend + Seasonality (weighted_regression_v1)',
            'history' => array_map(fn($r)=>['date'=>$r['rate_date'],'rate'=>(float)$r['rate_usd_per_tonne']], $hist),
            'data_freshness' => 'Simulated demo dataset — not live market data',
        ];
    }

    /** Counterfactual "what-if" reapplication of assumptions */
    public function counterfactual(int $routeId, int $classId, int $horizonDays, array $shocks): array
    {
        $baseline = $this->predictHorizon($routeId, $classId, $horizonDays);
        if (isset($baseline['error'])) return $baseline;

        $adjusted = $baseline['predicted_rate'];
        $notes = [];
        if (!empty($shocks['demand_change_pct'])) {
            $adjusted *= (1 + ($shocks['demand_change_pct']/100.0)*0.6);
            $notes[] = "Demand shift of {$shocks['demand_change_pct']}% applied (elasticity 0.6).";
        }
        if (!empty($shocks['congestion_change_pct'])) {
            $adjusted *= (1 + ($shocks['congestion_change_pct']/100.0)*0.25);
            $notes[] = "Port congestion change of {$shocks['congestion_change_pct']}% applied.";
        }
        if (!empty($shocks['bunker_change_pct'])) {
            $adjusted *= (1 + ($shocks['bunker_change_pct']/100.0)*0.3);
            $notes[] = "Bunker price change of {$shocks['bunker_change_pct']}% applied.";
        }
        $deltaAbs = $adjusted - $baseline['predicted_rate'];
        $deltaPct = ($deltaAbs / $baseline['predicted_rate']) * 100;

        return [
            'baseline' => $baseline,
            'counterfactual_rate' => round($adjusted,2),
            'delta_abs' => round($deltaAbs,2),
            'delta_pct' => round($deltaPct,2),
            'assumptions' => $notes,
        ];
    }
}
