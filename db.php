<?php
/**
 * FreightIQ Marine :: Database Configuration
 * Environment-based configuration — never hard-code production secrets.
 * Falls back to stock XAMPP defaults for local development.
 */
define('DB_HOST', getenv('FIQ_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('FIQ_DB_NAME') ?: 'freightiq_marine');
define('DB_USER', getenv('FIQ_DB_USER') ?: 'root');
define('DB_PASS', getenv('FIQ_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            die(json_encode(['error' => 'Database connection error. Verify XAMPP MySQL is running and backend/config/db.php credentials are correct.']));
        }
    }
    return $pdo;
}
