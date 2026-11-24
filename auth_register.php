<?php
/** Self-registration is limited to the Shipper role; other roles are provisioned by an Admin. */
require_once __DIR__ . '/../middleware/security.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); die(json_encode(['error'=>'Method not allowed'])); }

$body = json_body();
verify_csrf($body['csrf_token'] ?? null);
if (!rate_limit('register_attempts', 10, 600)) {
    http_response_code(429); die(json_encode(['error'=>'Too many registration attempts. Try again later.']));
}

$pdo = get_db();
$name = clean($body['full_name'] ?? '');
$org = clean($body['organisation'] ?? 'Independent Shipper');
$email = filter_var($body['email'] ?? '', FILTER_VALIDATE_EMAIL);
$password = $body['password'] ?? '';

$pwStrong = strlen($password) >= 8 && preg_match('/[A-Z]/', $password) && preg_match('/[0-9]/', $password) && preg_match('/[^A-Za-z0-9]/', $password);
if (!$name || !$email || !$pwStrong) {
    http_response_code(400);
    die(json_encode(['error' => 'Provide a valid name, email, and a strong password (8+ chars, upper-case, number, symbol).']));
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
$stmt->execute([$email]);
if ($stmt->fetch()) { http_response_code(409); die(json_encode(['error'=>'An account with this email already exists.'])); }

$shipperRole = $pdo->query("SELECT id FROM roles WHERE role_key='shipper'")->fetch();
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $pdo->prepare("INSERT INTO users (full_name,email,password_hash,role_id,organisation) VALUES (?,?,?,?,?)");
$stmt->execute([$name, $email, $hash, $shipperRole['id'], $org]);
audit($pdo, (int)$pdo->lastInsertId(), 'ACCOUNT_CREATED', 'user', (int)$pdo->lastInsertId());
echo json_encode(['success' => true]);
