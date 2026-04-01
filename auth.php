<?php
// ============================================================
//  auth.php  —  Login · Sign Up · Logout
//  POST body (JSON): { action: "login"|"signup"|"logout", ...fields }
//
//  login  → { action, email, password }
//  signup → { action, name, email, password }
//  logout → { action }
// ============================================================
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['ok' => false, 'error' => 'POST only'], 405);
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($body['action'] ?? '');

switch ($action) {

    // ── LOGIN ────────────────────────────────────────────────
    case 'login':
        $email = trim(strtolower($body['email'] ?? ''));
        $pass  = $body['password'] ?? '';

        if (!$email || !$pass) {
            jsonOut(['ok' => false, 'error' => 'Email and password are required'], 400);
        }

        $db  = getDB();
        $stmt = $db->prepare('SELECT id, name, email, pass_hash, initials FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($pass, $user['pass_hash'])) {
            jsonOut(['ok' => false, 'error' => 'Incorrect email or password'], 401);
        }

        // Start session
        $sessionUser = buildSessionUser($user);
        $_SESSION['user'] = $sessionUser;

        // Load portfolio
        $ps = $db->prepare('SELECT cash, hold_json, txns_json FROM portfolios WHERE user_id = ?');
        $ps->execute([$user['id']]);
        $portRow = $ps->fetch();
        $portfolio = $portRow ? [
            'cash' => (float) $portRow['cash'],
            'hold' => json_decode($portRow['hold_json'], true) ?: [],
            'txns' => json_decode($portRow['txns_json'],  true) ?: [],
        ] : null;

        // Load watchlist
        $ws = $db->prepare('SELECT symbols FROM watchlists WHERE user_id = ?');
        $ws->execute([$user['id']]);
        $wlRow = $ws->fetch();
        $watchlist = $wlRow ? (json_decode($wlRow['symbols'], true) ?: []) : [];

        jsonOut([
            'ok'        => true,
            'user'      => $sessionUser,
            'portfolio' => $portfolio,
            'watchlist' => $watchlist,
        ]);
        break;

    // ── SIGN UP ──────────────────────────────────────────────
    case 'signup':
        $name  = trim($body['name']     ?? '');
        $email = trim(strtolower($body['email'] ?? ''));
        $pass  = $body['password'] ?? '';

        // Validate
        if (!$name || !$email || !$pass) {
            jsonOut(['ok' => false, 'error' => 'Name, email, and password are required'], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonOut(['ok' => false, 'error' => 'Invalid email address'], 400);
        }
        if (strlen($pass) < 8) {
            jsonOut(['ok' => false, 'error' => 'Password must be at least 8 characters'], 400);
        }

        $db = getDB();

        // Check for duplicate email
        $check = $db->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            jsonOut(['ok' => false, 'error' => 'An account with that email already exists'], 409);
        }

        // Build initials from name (up to 2 letters)
        $words    = preg_split('/\s+/', $name);
        $initials = strtoupper(
            count($words) >= 2
                ? mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1)
                : mb_substr($words[0], 0, 2)
        );

        $hash = password_hash($pass, PASSWORD_DEFAULT);

        $ins = $db->prepare('INSERT INTO users (name, email, pass_hash, initials) VALUES (?, ?, ?, ?)');
        $ins->execute([$name, $email, $hash, $initials]);
        $userId = (int) $db->lastInsertId();

        // Create default portfolio (₹10,00,000 starting cash)
        $db->prepare('INSERT INTO portfolios (user_id, cash, hold_json, txns_json) VALUES (?, ?, ?, ?)')
           ->execute([$userId, 1000000.00, '{}', '[]']);

        // Create empty watchlist
        $db->prepare('INSERT INTO watchlists (user_id, symbols) VALUES (?, ?)')
           ->execute([$userId, '[]']);

        $newUser = [
            'id'       => $userId,
            'name'     => $name,
            'email'    => $email,
            'initials' => $initials,
        ];
        $_SESSION['user'] = $newUser;

        jsonOut(['ok' => true, 'user' => $newUser]);
        break;

    // ── LOGOUT ───────────────────────────────────────────────
    case 'logout':
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']
            );
        }
        session_destroy();
        jsonOut(['ok' => true]);
        break;

    default:
        jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
}

// ── Helpers ─────────────────────────────────────────────────

/**
 * Build the small user object stored in the session and returned to the browser.
 * Never includes the password hash.
 */
function buildSessionUser(array $row): array {
    return [
        'id'       => (int) $row['id'],
        'name'     => $row['name'],
        'email'    => $row['email'],
        'initials' => $row['initials'],
    ];
}
