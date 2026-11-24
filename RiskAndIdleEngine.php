<?php
/**
 * FreightIQ Marine :: Risk Center Engine + Idle Vessel Management Engine
 */
require_once __DIR__ . '/ForecastEngine.php';

class RiskEngine
{
    private PDO $pdo;
    private ForecastEngine $forecast;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; $this->forecast = new ForecastEngine($pdo); }

    /** Aggregate live-style risk snapshot across Freight / Port / Vessel / Route categories */
    public function snapshot(): array
    {
        $freight = $this->freightRisk();
        $port = $this->portRisk();
        $vessel = $this->vesselRisk();
        $route = $this->routeRisk();

        $overall = round((array_sum(array_column($freight,'risk_score')) / max(count($freight),1) * 0.3)
                        + (array_sum(array_column($port,'risk_score')) / max(count($port),1) * 0.3)
                        + (array_sum(array_column($vessel,'risk_score')) / max(count($vessel),1) * 0.2)
                        + (array_sum(array_column($route,'risk_score')) / max(count($route),1) * 0.2), 1);

        return ['overall_score' => $overall, 'freight' => $freight, 'port' => $port, 'vessel' => $vessel, 'route' => $route];
    }

    private function freightRisk(): array
    {
        // Row-based "most recent N per route/vessel-class" via window function —
        // robust to wall-clock drift relative to seeded data (see ForecastEngine
        // for the same rationale). ORDER BY repeats the full aggregate expression
        // rather than reusing a SELECT alias, since some MySQL/MariaDB versions
        // reject reusing an aggregate-derived alias inside a further expression
        // (error 1247, "reference to group function").
        $rows = $this->pdo->query(
            "SELECT ranked.route_id, o.load_point, p.name dest, vc.class_name,
                    STDDEV(ranked.rate_usd_per_tonne) vol, AVG(ranked.rate_usd_per_tonne) avgr
             FROM (
                SELECT fr.route_id, fr.vessel_class_id, fr.rate_usd_per_tonne,
                       ROW_NUMBER() OVER (PARTITION BY fr.route_id, fr.vessel_class_id ORDER BY fr.rate_date DESC) rn
                FROM freight_rates fr
             ) ranked
             JOIN routes r ON r.id = ranked.route_id
             JOIN origins o ON o.id = r.origin_id
             JOIN ports p ON p.id = r.destination_port_id
             JOIN vessel_classes vc ON vc.id = ranked.vessel_class_id
             WHERE ranked.rn <= 20
             GROUP BY ranked.route_id, ranked.vessel_class_id
             HAVING vol IS NOT NULL
             ORDER BY (STDDEV(ranked.rate_usd_per_tonne) / AVG(ranked.rate_usd_per_tonne)) DESC
             LIMIT 6"
        )->fetchAll();

        return array_map(function ($r) {
            $volPct = ($r['vol'] / max($r['avgr'],1)) * 100;
            $score = min(100, $volPct * 6);
            return [
                'route' => "{$r['load_point']} → {$r['dest']} ({$r['class_name']})",
                'risk_score' => round($score,1),
                'risk_level' => $this->levelFor($score),
                'detail' => "Volatility " . round($volPct,1) . "% over the most recent 20 data points.",
            ];
        }, $rows);
    }

    private function portRisk(): array
    {
        $rows = $this->pdo->query(
            "SELECT p.name, pc.congestion_level, pc.congestion_index, pc.estimated_turnaround_days, pc.weather_status
             FROM port_congestion pc JOIN ports p ON p.id=pc.port_id
             WHERE pc.id IN (SELECT MAX(id) FROM port_congestion GROUP BY port_id)"
        )->fetchAll();
        return array_map(function ($r) {
            $score = min(100, ($r['congestion_index']-1.0)*60 + ($r['weather_status'] !== 'Clear' ? 20 : 0));
            $score = max(5, $score);
            return [
                'port' => $r['name'],
                'risk_score' => round($score,1),
                'risk_level' => $this->levelFor($score),
                'detail' => "{$r['congestion_level']} congestion, {$r['estimated_turnaround_days']}d turnaround, weather: {$r['weather_status']}.",
            ];
        }, $rows);
    }

    private function vesselRisk(): array
    {
        $total = (int)$this->pdo->query("SELECT COUNT(*) n FROM vessels")->fetch()['n'];
        $idle = (int)$this->pdo->query("SELECT COUNT(*) n FROM vessels WHERE availability='Idle'")->fetch()['n'];
        $onVoyageUnknownEta = (int)$this->pdo->query("SELECT COUNT(*) n FROM vessels WHERE availability='On Voyage' AND eta IS NULL")->fetch()['n'];
        $idlePct = $total ? ($idle/$total)*100 : 0;
        $score = min(100, $idlePct*2.2 + $onVoyageUnknownEta*4);
        return [[
            'metric' => 'Fleet idle & ETA uncertainty',
            'risk_score' => round($score,1),
            'risk_level' => $this->levelFor($score),
            'detail' => "{$idle}/{$total} vessels idle; {$onVoyageUnknownEta} on-voyage vessels lack a confirmed ETA.",
        ]];
    }

    private function routeRisk(): array
    {
        $rows = $this->pdo->query(
            "SELECT r.id, o.load_point, p.name dest, r.distance_nm,
                    (SELECT COUNT(*) FROM weather_events we WHERE we.affected_port_id=p.id AND we.severity IN ('High','Severe')) storm_count
             FROM routes r JOIN origins o ON o.id=r.origin_id JOIN ports p ON p.id=r.destination_port_id"
        )->fetchAll();
        return array_map(function ($r) {
            $distFactor = min(30, $r['distance_nm']/300);
            $score = min(100, $distFactor + $r['storm_count']*35);
            $score = max(8, $score);
            return [
                'route' => "{$r['load_point']} → {$r['dest']}",
                'risk_score' => round($score,1),
                'risk_level' => $this->levelFor($score),
                'detail' => "{$r['distance_nm']} nm" . ($r['storm_count'] ? "; {$r['storm_count']} active severe weather event(s)." : "; no active severe weather."),
            ];
        }, $rows);
    }

    private function levelFor(float $score): string {
        if ($score >= 70) return 'Severe';
        if ($score >= 45) return 'High';
        if ($score >= 22) return 'Moderate';
        return 'Low';
    }
}

class IdleEngine
{
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function scenarios(): array
    {
        return $this->pdo->query(
            "SELECT i.*, v.vessel_name, v.imo_number, vc.class_name
             FROM idle_scenarios i
             JOIN vessels v ON v.id=i.vessel_id
             JOIN vessel_classes vc ON vc.id=v.class_id
             WHERE i.status != 'Closed'
             ORDER BY i.estimated_idle_cost_usd DESC"
        )->fetchAll();
    }

    public function summary(): array
    {
        $row = $this->pdo->query(
            "SELECT COUNT(*) n, COALESCE(SUM(estimated_idle_cost_usd),0) total_cost,
                    COALESCE(SUM(alternative_revenue_usd),0) recoverable
             FROM idle_scenarios WHERE status='Open'"
        )->fetch();
        return [
            'open_scenarios' => (int)$row['n'],
            'total_idle_cost_usd' => (float)$row['total_cost'],
            'recoverable_via_alternatives_usd' => (float)$row['recoverable'],
            'avoidable_loss_usd' => round(($row['total_cost'] - $row['recoverable']) * -1 * -1, 2),
        ];
    }
}
