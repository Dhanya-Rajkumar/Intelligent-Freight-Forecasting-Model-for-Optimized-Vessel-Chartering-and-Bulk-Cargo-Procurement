<?php
/**
 * FreightIQ Marine :: Security Middleware
 * Session hardening · security headers · CSRF · RBAC · rate limiting · audit
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => isset($_SERVER['HTTPS']),
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; font-src https://fonts.gstatic.com; script-src 'self' https://cdn.jsdelivr.net https://unpkg.com 'unsafe-inline'; img-src 'self' data: https://*.tile.openstreetmap.org https://*.basemaps.cartocdn.com; connect-src 'self';");

if (!isset($_SESSION['last_regen'])) {
    $_SESSION['last_regen'] = time();
} elseif (time() - $_SESSION['last_regen'] > 900) {
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
}

// ---------------- CSRF ----------------
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}
function verify_csrf(?string $token = null): void {
    $token = $token ?? ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        die(json_encode(['error' => 'Invalid CSRF token. Refresh and try again.']));
    }
}

// ---------------- Auth / RBAC ----------------
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(bool $isApi = false): void {
    if (!current_user()) {
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json');
            die(json_encode(['error' => 'Authentication required.']));
        }
        header('Location: /login.php');
        exit;
    }
}

/** Server-side permission check — never trust frontend role gating alone. */
function has_permission(string $permKey): bool {
    $u = current_user();
    if (!$u) return false;
    return in_array($permKey, $u['permissions'] ?? [], true);
}

function require_permission(string $permKey, bool $isApi = true): void {
    require_login($isApi);
    if (!has_permission($permKey)) {
        http_response_code(403);
        if ($isApi) {
            header('Content-Type: application/json');
            die(json_encode(['error' => "Access denied: missing permission '$permKey'."]));
        }
        die('Access denied: insufficient privileges.');
    }
}

// ---------------- Input helpers ----------------
function clean(string $s): string {
    return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8');
}

function json_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// ---------------- Audit ----------------
function audit(PDO $pdo, ?int $userId, string $action, string $entityType = '', ?int $entityId = null, string $details = ''): void {
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$userId, $action, $entityType ?: null, $entityId, $details, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
}

// ---------------- Simple in-session rate limiting for sensitive endpoints ----------------
function rate_limit(string $key, int $maxAttempts, int $windowSeconds): bool {
    $bucket = $_SESSION['rate_limit'][$key] ?? ['count' => 0, 'reset_at' => time() + $windowSeconds];
    if (time() > $bucket['reset_at']) {
        $bucket = ['count' => 0, 'reset_at' => time() + $windowSeconds];
    }
    $bucket['count']++;
    $_SESSION['rate_limit'][$key] = $bucket;
    return $bucket['count'] <= $maxAttempts;
}

/** Load the full session user object (with role + permissions) after login. */
function build_session_user(PDO $pdo, array $userRow): array {
    $stmt = $pdo->prepare(
        "SELECT p.perm_key FROM role_permissions rp
         JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?"
    );
    $stmt->execute([$userRow['role_id']]);
    $perms = array_column($stmt->fetchAll(), 'perm_key');

    $stmt2 = $pdo->prepare("SELECT role_key, display_name FROM roles WHERE id = ?");
    $stmt2->execute([$userRow['role_id']]);
    $role = $stmt2->fetch();

    return [
        'id' => $userRow['id'],
        'name' => $userRow['full_name'],
        'email' => $userRow['email'],
        'role_key' => $role['role_key'],
        'role_name' => $role['display_name'],
        'permissions' => $perms,
    ];
}
