<?php
require_once __DIR__ . '/../middleware/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../services/ForecastEngine.php';
require_once __DIR__ . '/../services/VesselOptimizer.php';
header('Content-Type: application/json');
require_permission('cargo.view');
$pdo = get_db();
$u = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_permission('cargo.create');
    $body = json_body();
    verify_csrf($body['csrf_token'] ?? null);

    $commodityId = (int)($body['commodity_id'] ?? 0);
    $originId = (int)($body['origin_id'] ?? 0);
    $destPortId = (int)($body['destination_port_id'] ?? 0);
    $qty = (int)($body['quantity_tonnes'] ?? 0);
    $duration = in_array($body['contract_duration'] ?? '', ['Spot','Short-Term','Medium-Term']) ? $body['contract_duration'] : 'Spot';

    if (!$commodityId || !$originId || !$destPortId || $qty < 1000) {
        http_response_code(400);
        die(json_encode(['error' => 'commodity_id, origin_id, destination_port_id and a realistic quantity_tonnes are required.']));
    }

    // Find matching route (or nearest by destination+commodity) to anchor a forecast
    $routeStmt = $pdo->prepare("SELECT id FROM routes WHERE origin_id=? AND destination_port_id=? AND commodity_id=?");
    $routeStmt->execute([$originId, $destPortId, $commodityId]);
    $route = $routeStmt->fetch();
    if (!$route) {
        $routeStmt2 = $pdo->prepare("SELECT id FROM routes WHERE destination_port_id=? AND commodity_id=? LIMIT 1");
        $routeStmt2->execute([$destPortId, $commodityId]);
        $route = $routeStmt2->fetch();
    }

    $optimizer = new VesselOptimizer($pdo);
    $recommendation = $optimizer->recommend($qty, $destPortId, $route['id'] ?? null);
    $recommendedClassId = $recommendation['recommended']['class_id'] ?? null;
    $estFreight = null; $riskLevel = 'Low';

    if ($route && $recommendedClassId) {
        $engine = new ForecastEngine($pdo);
        $f = $engine->predictHorizon($route['id'], $recommendedClassId, 30);
        if (!isset($f['error'])) {
            $estFreight = $f['predicted_rate'];
            $riskLevel = $f['volatility_pct'] > 8 ? 'High' : ($f['volatility_pct'] > 4 ? 'Moderate' : 'Low');
        }
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO cargo_requests (shipper_id, commodity_id, origin_id, destination_port_id, quantity_tonnes,
             loading_window_start, loading_window_end, delivery_window_start, delivery_window_end,
             contract_duration, status, recommended_vessel_class_id, estimated_freight_usd_per_tonne, risk_level)
             VALUES (?,?,?,?,?,?,?,?,?,?, 'Submitted', ?,?,?)"
        );
        $stmt->execute([
            $u['id'], $commodityId, $originId, $destPortId, $qty,
            $body['loading_window_start'] ?? null, $body['loading_window_end'] ?? null,
            $body['delivery_window_start'] ?? null, $body['delivery_window_end'] ?? null,
            $duration, $recommendedClassId, $estFreight, $riskLevel,
        ]);
        $newId = (int)$pdo->lastInsertId();
        audit($pdo, $u['id'], 'CARGO_REQUEST_CREATED', 'cargo_request', $newId);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        die(json_encode(['error' => 'Could not save cargo request.']));
    }

    echo json_encode(['success' => true, 'id' => $newId, 'recommendation' => $recommendation, 'route_id' => $route['id'] ?? null]);
    exit;
}

// GET — list (shippers see only their own unless they have cargo.manage_all)
$canSeeAll = has_permission('cargo.manage_all');
$sql = "SELECT cr.*, c.name AS commodity, o.load_point, o.country, p.name AS dest_port, vc.class_name, u.full_name AS shipper_name
        FROM cargo_requests cr
        JOIN commodities c ON c.id=cr.commodity_id
        JOIN origins o ON o.id=cr.origin_id
        JOIN ports p ON p.id=cr.destination_port_id
        LEFT JOIN vessel_classes vc ON vc.id=cr.recommended_vessel_class_id
        JOIN users u ON u.id=cr.shipper_id
        WHERE cr.deleted_at IS NULL " . (!$canSeeAll ? "AND cr.shipper_id = ?" : "") . "
        ORDER BY cr.created_at DESC";
$stmt = $pdo->prepare($sql);
$canSeeAll ? $stmt->execute() : $stmt->execute([$u['id']]);
echo json_encode($stmt->fetchAll());
