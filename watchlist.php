<?php
// ============================================================
//  watchlist.php  —  Save Watchlist (upsert)
//  POST body (JSON): { symbols: ["RELIANCE.NS", "TCS.NS", ...] }
//  Requires an active session (logged-in user).
// ============================================================
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['ok' => false, 'error' => 'POST only'], 405);
}

// Must be authenticated
$user = requireAuth();

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$symbols = $body['symbols'] ?? null;

if (!is_array($symbols)) {
    jsonOut(['ok' => false, 'error' => 'symbols must be a JSON array'], 400);
}

// Sanitise each symbol — uppercase, strip anything that isn't alphanumeric or a dot
$clean = array_values(array_filter(array_map(function (string $sym): string {
    $sym = strtoupper(trim($sym));
    // Allow letters, digits, and a single dot (e.g. RELIANCE.NS)
    return preg_replace('/[^A-Z0-9.]/', '', $sym);
}, $symbols)));

$db = getDB();

$stmt = $db->prepare(
    'INSERT INTO watchlists (user_id, symbols)
     VALUES (?, ?)
     ON DUPLICATE KEY UPDATE symbols = VALUES(symbols)'
);
$stmt->execute([$user['id'], json_encode($clean)]);

jsonOut(['ok' => true, 'count' => count($clean)]);
