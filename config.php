<?php
// ============================================================
//  config.php  —  Database & Application Configuration
//
//  HOW TO SET UP:
//  1. Set your MySQL credentials below (or use environment vars)
//  2. Paste your Anthropic API key in ANTHROPIC_API_KEY
//  3. Run:  mysql -u root -p < setup.sql   (first time only)
// ============================================================

// ── Database ─────────────────────────────────────────────────
// You can override any of these with environment variables,
// e.g. export ST_DB_PASS="my_password" before starting PHP.

define('DB_HOST',    getenv('ST_DB_HOST') ?: 'localhost');
define('DB_PORT',    getenv('ST_DB_PORT') ?: '3306');
define('DB_NAME',    getenv('ST_DB_NAME') ?: 'stock_tracking');
define('DB_USER',    getenv('ST_DB_USER') ?: 'root');
define('DB_PASS',    getenv('ST_DB_PASS') ?: '');          // ← change or set ST_DB_PASS env var
define('DB_CHARSET', 'utf8mb4');

// ── Anthropic API Key ────────────────────────────────────────
// Paste your key here OR export ST_ANTHROPIC_KEY="sk-ant-..."
// The key is NEVER sent to the browser — it stays server-side.
define('ANTHROPIC_API_KEY', getenv('ST_ANTHROPIC_KEY') ?: 'sk-ant-api03-XXXXXXXXXXXX');

// ── Session ──────────────────────────────────────────────────
session_name('STOCKTRACK_SESS');
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');

// Use HTTPS-only cookies in production (set to 0 for local development)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
ini_set('session.cookie_secure', $isHttps ? 1 : 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── PDO connection (singleton) ───────────────────────────────
function getDB(): PDO {
    static $pdo = null;

    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        // Don't leak connection details in the error message
        echo json_encode(['ok' => false, 'error' => 'Database connection failed. Check config.php.']);
        exit;
    }

    return $pdo;
}

// ── JSON response helper ─────────────────────────────────────
function jsonOut(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Current logged-in user (from session) ───────────────────
function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

// ── Require authentication — halt with 401 if not logged in ─
function requireAuth(): array {
    $user = currentUser();
    if (!$user) {
        jsonOut(['ok' => false, 'error' => 'Not authenticated. Please log in.'], 401);
    }
    return $user;
}
