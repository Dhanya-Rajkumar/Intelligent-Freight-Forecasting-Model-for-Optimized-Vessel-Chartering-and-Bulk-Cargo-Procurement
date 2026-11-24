<?php
require_once __DIR__ . '/../middleware/security.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
require_login(true);
$pdo = get_db();
$u = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_body();
    verify_csrf($body['csrf_token'] ?? null);
    $id = (int)($body['id'] ?? 0);
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND (user_id=? OR user_id IS NULL)")->execute([$id, $u['id']]);
    echo json_encode(['success'=>true]);
    exit;
}

$rows = $pdo->prepare(
    "SELECT * FROM notifications WHERE user_id=? OR user_id IS NULL ORDER BY created_at DESC LIMIT 20"
);
$rows->execute([$u['id']]);
echo json_encode($rows->fetchAll());
