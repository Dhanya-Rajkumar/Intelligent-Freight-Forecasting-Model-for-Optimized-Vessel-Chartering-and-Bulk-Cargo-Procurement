<?php
require_once __DIR__ . '/../middleware/security.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
require_permission('admin.manage_users');
$pdo = get_db();
$u = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_body();
    verify_csrf($body['csrf_token'] ?? null);
    $action = $body['action'] ?? '';
    $targetId = (int)($body['id'] ?? 0);
    if (!$targetId) { http_response_code(400); die(json_encode(['error'=>'id required'])); }
    if ($targetId === $u['id'] && in_array($action, ['deactivate','change_role'], true)) {
        http_response_code(400); die(json_encode(['error'=>'You cannot change your own role or deactivate your own account.']));
    }

    switch ($action) {
        case 'change_role':
            $roleId = (int)($body['role_id'] ?? 0);
            $role = $pdo->prepare("SELECT id FROM roles WHERE id=?"); $role->execute([$roleId]);
            if (!$role->fetch()) { http_response_code(400); die(json_encode(['error'=>'Invalid role.'])); }
            $pdo->prepare("UPDATE users SET role_id=? WHERE id=?")->execute([$roleId, $targetId]);
            audit($pdo, $u['id'], 'USER_ROLE_CHANGED', 'user', $targetId, "role_id={$roleId}");
            break;
        case 'activate':
            $pdo->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$targetId]);
            audit($pdo, $u['id'], 'USER_ACTIVATED', 'user', $targetId);
            break;
        case 'deactivate':
            $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$targetId]);
            audit($pdo, $u['id'], 'USER_DEACTIVATED', 'user', $targetId);
            break;
        case 'unlock':
            $pdo->prepare("UPDATE users SET failed_attempts=0, locked_until=NULL WHERE id=?")->execute([$targetId]);
            audit($pdo, $u['id'], 'USER_UNLOCKED', 'user', $targetId);
            break;
        default:
            http_response_code(400); die(json_encode(['error'=>'Unknown action.']));
    }
    echo json_encode(['success'=>true]);
    exit;
}

$rows = $pdo->query(
    "SELECT u.id, u.full_name, u.email, u.organisation, u.is_active, u.failed_attempts, u.locked_until,
            u.last_login, u.created_at, r.id AS role_id, r.role_key, r.display_name AS role_name
     FROM users u JOIN roles r ON r.id=u.role_id
     WHERE u.deleted_at IS NULL ORDER BY u.created_at DESC"
)->fetchAll();
$roles = $pdo->query("SELECT id, role_key, display_name FROM roles ORDER BY id")->fetchAll();
echo json_encode(['users' => $rows, 'roles' => $roles]);
