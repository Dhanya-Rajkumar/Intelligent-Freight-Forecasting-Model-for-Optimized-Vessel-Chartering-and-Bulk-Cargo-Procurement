<?php
require_once __DIR__ . '/../middleware/security.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); die(json_encode(['error'=>'Method not allowed'])); }

$body = json_body();
verify_csrf($body['csrf_token'] ?? null);

if (!rate_limit('login_attempts', 15, 300)) {
    http_response_code(429);
    die(json_encode(['error' => 'Too many login attempts. Please wait a few minutes.']));
}

$pdo = get_db();
$email = filter_var($body['email'] ?? '', FILTER_VALIDATE_EMAIL);
$password = $body['password'] ?? '';

if (!$email || !$password) {
    http_response_code(400);
    die(json_encode(['error' => 'Valid email and password are required.']));
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email=? AND deleted_at IS NULL");
$stmt->execute([$email]);
$user = $stmt->fetch();

// Generic error message regardless of which check fails — avoids user enumeration
$genericError = 'Invalid email or password.';

if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
    http_response_code(423);
    die(json_encode(['error' => 'Account temporarily locked due to repeated failed attempts. Try again later.']));
}

if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
    $pdo->prepare("UPDATE users SET failed_attempts=0, locked_until=NULL, last_login=NOW() WHERE id=?")->execute([$user['id']]);
    session_regenerate_id(true);
    $_SESSION['user'] = build_session_user($pdo, $user);
    audit($pdo, $user['id'], 'LOGIN_SUCCESS', 'user', $user['id']);
    echo json_encode(['success' => true, 'user' => $_SESSION['user'], 'csrf_token' => csrf_token()]);
    exit;
}

if ($user) {
    $attempts = $user['failed_attempts'] + 1;
    $lock = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
    $pdo->prepare("UPDATE users SET failed_attempts=?, locked_until=? WHERE id=?")->execute([$attempts, $lock, $user['id']]);
    audit($pdo, $user['id'], 'LOGIN_FAILED', 'user', $user['id'], "attempt {$attempts}");
}

http_response_code(401);
echo json_encode(['error' => $genericError]);
