<?php
// ============================================================
// config/db.php - Database connection
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');         // ← change to your DB user
define('DB_PASS', '');             // ← change to your DB password
define('DB_NAME', 'opulence_db');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed.']));
        }
    }
    return $pdo;
}

// Helper: generate unique reference codes
function generateRef(string $prefix = 'REF'): string
{
    return strtoupper($prefix) . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

// Helper: JSON response
function jsonResponse(mixed $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Helper: sanitize input
function clean(string $val): string
{
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}
