<?php
require_once __DIR__ . '/../middleware/security.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
require_login(true);
$pdo = get_db();

echo json_encode([
    'routes' => $pdo->query(
        "SELECT r.id, o.load_point, o.country, p.name AS dest_port, c.name AS commodity, r.distance_nm
         FROM routes r JOIN origins o ON o.id=r.origin_id JOIN ports p ON p.id=r.destination_port_id
         JOIN commodities c ON c.id=r.commodity_id ORDER BY o.country, p.name"
    )->fetchAll(),
    'vessel_classes' => $pdo->query("SELECT * FROM vessel_classes ORDER BY dwt_max DESC")->fetchAll(),
    'commodities' => $pdo->query("SELECT * FROM commodities ORDER BY name")->fetchAll(),
    'origins' => $pdo->query("SELECT * FROM origins ORDER BY country")->fetchAll(),
    'ports' => $pdo->query("SELECT id, name, max_draft_m, max_loa_m, max_beam_m FROM ports ORDER BY name")->fetchAll(),
]);
