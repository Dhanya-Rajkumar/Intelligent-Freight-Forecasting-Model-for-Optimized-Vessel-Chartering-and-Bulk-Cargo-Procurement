<?php
require_once __DIR__ . '/../middleware/security.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
require_permission('dashboard.view');
$pdo = get_db();

// Row-based recency (not clock-relative) — see ForecastEngine for rationale.
$avgFreight = $pdo->query("SELECT AVG(rate_usd_per_tonne) a FROM (SELECT rate_usd_per_tonne FROM freight_rates ORDER BY rate_date DESC LIMIT 300) recent")->fetch()['a'];
$activeVessels = $pdo->query("SELECT COUNT(*) c FROM vessels WHERE availability IN ('Available','On Voyage')")->fetch()['c'];
$availableVessels = $pdo->query("SELECT COUNT(*) c FROM vessels WHERE availability='Available'")->fetch()['c'];
$idleVessels = $pdo->query("SELECT COUNT(*) c FROM vessels WHERE availability='Idle'")->fetch()['c'];
$avgCongestion = $pdo->query(
    "SELECT AVG(congestion_index) a FROM port_congestion WHERE id IN (SELECT MAX(id) FROM port_congestion GROUP BY port_id)"
)->fetch()['a'];
$activeAlerts = $pdo->query("SELECT COUNT(*) c FROM alerts WHERE is_resolved=0")->fetch()['c'];
$contractOpportunities = $pdo->query("SELECT COUNT(*) c FROM alerts WHERE alert_type='Contract Opportunity' AND is_resolved=0")->fetch()['c'];

$vesselAvailability = $pdo->query(
    "SELECT vc.class_name, availability, COUNT(*) n FROM vessels v JOIN vessel_classes vc ON vc.id=v.class_id GROUP BY vc.class_name, availability"
)->fetchAll();

$portCongestion = $pdo->query(
    "SELECT p.name, pc.congestion_index, pc.congestion_level FROM port_congestion pc JOIN ports p ON p.id=pc.port_id
     WHERE pc.id IN (SELECT MAX(id) FROM port_congestion GROUP BY port_id)"
)->fetchAll();

$recentAlerts = $pdo->query("SELECT * FROM alerts WHERE is_resolved=0 ORDER BY created_at DESC LIMIT 6")->fetchAll();

echo json_encode([
    'kpis' => [
        'avg_freight_usd_per_tonne' => round((float)$avgFreight, 2),
        'active_vessels' => (int)$activeVessels,
        'available_vessels' => (int)$availableVessels,
        'idle_vessels' => (int)$idleVessels,
        'avg_port_congestion_index' => round((float)$avgCongestion, 2),
        'active_alerts' => (int)$activeAlerts,
        'contract_opportunities' => (int)$contractOpportunities,
    ],
    'vessel_availability' => $vesselAvailability,
    'port_congestion' => $portCongestion,
    'recent_alerts' => $recentAlerts,
    'data_freshness' => 'Simulated demo dataset — refreshed at seed time, not live',
]);
