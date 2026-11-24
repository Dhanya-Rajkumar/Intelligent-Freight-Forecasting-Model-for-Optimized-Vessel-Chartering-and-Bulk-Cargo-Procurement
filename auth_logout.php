<?php
require_once __DIR__ . '/../middleware/security.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
if (current_user()) {
    audit(get_db(), current_user()['id'], 'LOGOUT', 'user', current_user()['id']);
}
$_SESSION = [];
session_destroy();
echo json_encode(['success' => true]);
