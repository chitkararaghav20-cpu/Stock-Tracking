<?php
// ============================================================
//  portfolio.php  —  Save Portfolio (upsert)
//  POST body (JSON): { cash: number, hold: {}, txns: [] }
//  Requires an active session (logged-in user).
// ============================================================
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['ok' => false, 'error' => 'POST only'], 405);
}

// Must be authenticated
$user = requireAuth();

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$cash     = isset($body['cash'])  ? (float) $body['cash']  : null;
$holdJson = isset($body['hold'])  ? json_encode($body['hold']) : null;
$txnsJson = isset($body['txns'])  ? json_encode($body['txns']) : null;

if ($cash === null || $holdJson === null || $txnsJson === null) {
    jsonOut(['ok' => false, 'error' => 'Missing required fields: cash, hold, txns'], 400);
}

// Sanity-check cash is non-negative
if ($cash < 0) {
    jsonOut(['ok' => false, 'error' => 'Cash balance cannot be negative'], 400);
}

$db = getDB();

// Upsert: update if exists, insert if not
$stmt = $db->prepare(
    'INSERT INTO portfolios (user_id, cash, hold_json, txns_json)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
       cash      = VALUES(cash),
       hold_json = VALUES(hold_json),
       txns_json = VALUES(txns_json)'
);
$stmt->execute([$user['id'], $cash, $holdJson, $txnsJson]);

jsonOut(['ok' => true]);
