<?php
// ============================================================
//  settings.php  —  Save User Settings (upsert)
//  POST body (JSON): { theme, layout, font, autoref, ticker,
//                      compact, notif, sound }
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

// Whitelist and validate each setting so arbitrary data cannot be stored
$allowed = [
    'theme'   => ['dark', 'white', 'blue'],
    'layout'  => ['default', 'rounded', 'sharp'],
    'font'    => ['14', '15', '16', '17'],
    'autoref' => null,   // boolean
    'ticker'  => null,   // boolean
    'compact' => null,   // boolean
    'notif'   => null,   // boolean
    'sound'   => null,   // boolean
];

$clean = [];

foreach ($allowed as $key => $validValues) {
    if (!array_key_exists($key, $body)) continue;

    $val = $body[$key];

    if ($validValues !== null) {
        // String setting — must be one of the listed values
        if (!in_array((string) $val, $validValues, true)) continue;
        $clean[$key] = (string) $val;
    } else {
        // Boolean setting
        $clean[$key] = (bool) $val;
    }
}

if (empty($clean)) {
    jsonOut(['ok' => false, 'error' => 'No valid settings provided'], 400);
}

$db = getDB();

$stmt = $db->prepare(
    'INSERT INTO user_settings (user_id, settings_json)
     VALUES (?, ?)
     ON DUPLICATE KEY UPDATE settings_json = VALUES(settings_json)'
);
$stmt->execute([$user['id'], json_encode($clean)]);

jsonOut(['ok' => true]);
