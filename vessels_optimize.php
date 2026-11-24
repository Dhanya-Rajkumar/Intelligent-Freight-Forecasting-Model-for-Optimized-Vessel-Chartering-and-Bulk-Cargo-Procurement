<?php
require_once __DIR__ . '/../middleware/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../services/VesselOptimizer.php';
header('Content-Type: application/json');
require_permission('vessels.optimize');

$cargoVolume = filter_input(INPUT_GET, 'cargo_volume', FILTER_VALIDATE_INT);
$portId = filter_input(INPUT_GET, 'port_id', FILTER_VALIDATE_INT);
$routeId = filter_input(INPUT_GET, 'route_id', FILTER_VALIDATE_INT);
if (!$cargoVolume || !$portId) { http_response_code(400); die(json_encode(['error'=>'cargo_volume and port_id are required'])); }

$pdo = get_db();
$optimizer = new VesselOptimizer($pdo);
$result = $optimizer->recommend($cargoVolume, $portId, $routeId ?: null);

if (!isset($result['error'])) {
    $u = current_user();
    $stmt = $pdo->prepare(
        "INSERT INTO port_constraints_log (user_id, port_id, vessel_class_id, draft_ok, loa_ok, beam_ok, overall_status, reason)
         VALUES (?,?,?,?,?,?,?,?)"
    );
    foreach ($result['candidates'] as $c) {
        $stmt->execute([
            $u['id'], $portId, $c['class_id'],
            $c['port_compatibility'] === 'Compatible' ? 1 : 0,
            $c['port_compatibility'] !== 'Not Compatible' ? 1 : 0,
            $c['port_compatibility'] !== 'Not Compatible' ? 1 : 0,
            $c['port_compatibility'],
            $c['reasons'][0] ?? '',
        ]);
    }
    audit($pdo, $u['id'], 'VESSEL_OPTIMIZATION_RUN', 'port', $portId, "cargo={$cargoVolume}");
}
echo json_encode($result);
