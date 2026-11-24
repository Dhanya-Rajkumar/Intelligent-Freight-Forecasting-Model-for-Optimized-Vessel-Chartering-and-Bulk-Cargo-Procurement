<?php
require_once __DIR__ . '/../middleware/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../services/ForecastEngine.php';
header('Content-Type: application/json');
require_permission('forecast.view');

$body = json_body();
verify_csrf($body['csrf_token'] ?? null);

$routeId = (int)($body['route_id'] ?? 0);
$classId = (int)($body['vessel_class_id'] ?? 0);
$horizon = max(7, min(180, (int)($body['horizon'] ?? 30)));
if (!$routeId || !$classId) { http_response_code(400); die(json_encode(['error'=>'route_id and vessel_class_id are required'])); }

$shocks = [
    'demand_change_pct' => (float)($body['demand_change_pct'] ?? 0),
    'congestion_change_pct' => (float)($body['congestion_change_pct'] ?? 0),
    'bunker_change_pct' => (float)($body['bunker_change_pct'] ?? 0),
];

$pdo = get_db();
$engine = new ForecastEngine($pdo);
$result = $engine->counterfactual($routeId, $classId, $horizon, $shocks);
audit($pdo, current_user()['id'], 'COUNTERFACTUAL_RUN', 'route', $routeId, json_encode($shocks));
echo json_encode($result);
