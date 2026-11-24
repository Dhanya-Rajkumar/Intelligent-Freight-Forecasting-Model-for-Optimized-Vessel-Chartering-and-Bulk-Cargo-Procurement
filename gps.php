<?php
require_once __DIR__ . '/../middleware/security.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
require_permission('gps.view');
$pdo = get_db();

$vessels = $pdo->query(
    "SELECT v.id, v.vessel_name, v.imo_number, v.availability, vc.class_name,
            vp.latitude, vp.longitude, vp.speed_knots, vp.heading_deg, vp.data_source
     FROM vessels v JOIN vessel_classes vc ON vc.id=v.class_id
     JOIN vessel_positions vp ON vp.id = (SELECT MAX(id) FROM vessel_positions WHERE vessel_id=v.id)"
)->fetchAll();

$ports = $pdo->query(
    "SELECT p.id, p.name, p.latitude, p.longitude, pc.congestion_level, pc.congestion_index
     FROM ports p LEFT JOIN port_congestion pc ON pc.id=(SELECT MAX(id) FROM port_congestion WHERE port_id=p.id)"
)->fetchAll();

$origins = $pdo->query("SELECT id, country, load_point, latitude, longitude FROM origins")->fetchAll();

echo json_encode([
    'vessels' => $vessels,
    'ports' => $ports,
    'origins' => $origins,
    'data_source' => 'Simulated vessel positions for demonstration — not live AIS. Architecture supports plugging in a real AIS provider (see docs/architecture.md).',
]);
