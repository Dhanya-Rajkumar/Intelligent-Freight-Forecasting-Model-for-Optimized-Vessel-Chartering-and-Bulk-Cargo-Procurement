<?php
/**
 * FreightIQ Marine :: Vessel Optimization & Port Constraint Intelligence
 * Explains every recommendation and every rejection.
 */
class VesselOptimizer
{
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    /** Validate a vessel class against a port's physical constraints */
    public function checkPortCompatibility(array $port, array $vesselClass): array
    {
        $draftOk = $vesselClass['typical_draft_m'] <= $port['max_draft_m'];
        $loaOk   = $vesselClass['typical_loa_m']   <= $port['max_loa_m'];
        $beamOk  = $vesselClass['typical_beam_m']  <= $port['max_beam_m'];

        $reasons = [];
        if (!$draftOk) $reasons[] = "{$vesselClass['class_name']} draft ({$vesselClass['typical_draft_m']}m) exceeds {$port['name']} maximum draft ({$port['max_draft_m']}m).";
        if (!$loaOk)   $reasons[] = "{$vesselClass['class_name']} LOA ({$vesselClass['typical_loa_m']}m) exceeds {$port['name']} maximum LOA ({$port['max_loa_m']}m).";
        if (!$beamOk)  $reasons[] = "{$vesselClass['class_name']} beam ({$vesselClass['typical_beam_m']}m) exceeds {$port['name']} maximum beam ({$port['max_beam_m']}m).";

        if ($draftOk && $loaOk && $beamOk) {
            $status = 'Compatible';
            $reasons[] = "All physical dimensions (draft, LOA, beam) are within {$port['name']} limits.";
        } elseif ($draftOk || $loaOk || $beamOk) {
            $status = 'Restricted';
        } else {
            $status = 'Not Compatible';
        }

        return ['status' => $status, 'draft_ok' => $draftOk, 'loa_ok' => $loaOk, 'beam_ok' => $beamOk, 'reasons' => $reasons];
    }

    /**
     * Full vessel optimization for a cargo requirement.
     * Considers cargo volume, port constraints, congestion, availability, distance.
     */
    public function recommend(int $cargoVolume, int $destinationPortId, ?int $routeId = null): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM ports WHERE id=?");
        $stmt->execute([$destinationPortId]);
        $port = $stmt->fetch();
        if (!$port) return ['error' => 'Port not found'];

        $congStmt = $this->pdo->prepare("SELECT * FROM port_congestion WHERE port_id=? ORDER BY recorded_at DESC LIMIT 1");
        $congStmt->execute([$destinationPortId]);
        $congestion = $congStmt->fetch() ?: ['congestion_level'=>'Low','congestion_index'=>1.0,'estimated_turnaround_days'=>$port['historical_turnaround_days']];

        $classes = $this->pdo->query("SELECT * FROM vessel_classes ORDER BY dwt_max DESC")->fetchAll();

        $distance = 4000;
        if ($routeId) {
            $r = $this->pdo->prepare("SELECT distance_nm FROM routes WHERE id=?");
            $r->execute([$routeId]);
            $row = $r->fetch();
            if ($row) $distance = (int)$row['distance_nm'];
        }

        $candidates = [];
        foreach ($classes as $c) {
            $compat = $this->checkPortCompatibility($port, $c);
            $capacityFit = $cargoVolume >= $c['dwt_min']*0.6 && $cargoVolume <= $c['dwt_max']*1.1;
            $voyages = max(1, (int)ceil($cargoVolume / $c['dwt_max']));
            $utilisation = min(1.0, $cargoVolume / ($voyages * $c['dwt_max']));

            $availStmt = $this->pdo->prepare("SELECT COUNT(*) n FROM vessels WHERE class_id=? AND availability='Available'");
            $availStmt->execute([$c['id']]);
            $availableCount = (int)$availStmt->fetch()['n'];

            $voyageDays = round(($distance / ($c['service_speed_knots']*24)) + $congestion['estimated_turnaround_days'], 1);
            $hireCost = $c['base_daily_hire_usd'] * $voyageDays * $voyages;
            $fuelCost = $c['fuel_consumption_tpd'] * $voyageDays * $voyages * 650; // assume ~$650/t bunker
            $portCost = $port['cargo_handling_rate_usd_per_tonne'] * $cargoVolume;
            $estCostPerTonne = ($hireCost + $fuelCost + $portCost) / max($cargoVolume,1);

            $reasons = $compat['reasons'];
            $reasons[] = $capacityFit
                ? "Cargo quantity ({$this->fmt($cargoVolume)}t) is a good match for {$c['class_name']} capacity ({$this->fmt($c['dwt_min'])}-{$this->fmt($c['dwt_max'])}t)."
                : "Cargo quantity ({$this->fmt($cargoVolume)}t) is a poor match for {$c['class_name']} capacity — would require {$voyages} voyage(s) at {$this->fmt(round($utilisation*100))}% utilisation.";
            $reasons[] = "Destination congestion: {$congestion['congestion_level']} ({$congestion['estimated_turnaround_days']} day est. turnaround).";
            $reasons[] = $availableCount > 0 ? "{$availableCount} {$c['class_name']} vessel(s) currently Available." : "No {$c['class_name']} vessels currently marked Available.";

            $score = 0;
            if ($compat['status'] === 'Compatible') $score += 45;
            elseif ($compat['status'] === 'Restricted') $score += 15;
            if ($capacityFit) $score += 25;
            $score += $utilisation * 15;
            $score += min($availableCount, 3) * 5;
            $score -= ($congestion['congestion_index'] - 1.0) * 15;

            $riskLevel = 'Low';
            if ($compat['status'] === 'Not Compatible') $riskLevel = 'Severe';
            elseif ($compat['status'] === 'Restricted' || $congestion['congestion_level'] === 'High') $riskLevel = 'High';
            elseif ($congestion['congestion_level'] === 'Moderate') $riskLevel = 'Moderate';

            $candidates[] = [
                'class_id' => $c['id'],
                'class_name' => $c['class_name'],
                'port_compatibility' => $compat['status'],
                'capacity_fit' => $capacityFit,
                'voyages_needed' => $voyages,
                'utilisation_pct' => round($utilisation*100,1),
                'estimated_voyage_days' => $voyageDays,
                'estimated_freight_usd_per_tonne' => round($estCostPerTonne,2),
                'available_vessels' => $availableCount,
                'idle_time_estimate_days' => $availableCount === 0 ? round(random_int(2,6) + $congestion['estimated_turnaround_days']*0.3,1) : 0.0,
                'risk_level' => $riskLevel,
                'score' => round($score,1),
                'reasons' => $reasons,
            ];
        }

        usort($candidates, fn($a,$b) => $b['score'] <=> $a['score']);

        return [
            'port' => $port,
            'congestion' => $congestion,
            'candidates' => $candidates,
            'recommended' => $candidates[0] ?? null,
            'alternatives' => array_slice($candidates, 1),
        ];
    }

    private function fmt($n): string { return number_format((float)$n); }
}
