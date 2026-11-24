<?php
require_once __DIR__ . '/../middleware/security.php';
header('Content-Type: application/json');
$u = current_user();
if (!$u) { http_response_code(401); die(json_encode(['error'=>'Not authenticated'])); }
echo json_encode(['user' => $u, 'csrf_token' => csrf_token()]);
