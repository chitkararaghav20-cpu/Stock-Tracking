<?php
// ============================================================
//  ai.php  —  Server-side Anthropic API Proxy
//  Keeps the API key hidden — it is never sent to the browser.
//
//  POST body (JSON):
//    { mode: "chat",    messages: [...], portfolio: "..." }
//    { mode: "suggest", portfolio: "..." }
// ============================================================
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Must be logged in to use AI features
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['ok' => false, 'error' => 'POST only'], 405);
}

if (!defined('ANTHROPIC_API_KEY') || !ANTHROPIC_API_KEY || str_contains(ANTHROPIC_API_KEY, 'XXXX')) {
    jsonOut(['ok' => false, 'error' => 'Anthropic API key not configured in config.php'], 503);
}

// ── Parse and validate input ─────────────────────────────────
$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$mode    = $body['mode']      ?? 'chat';
$portCtx = trim($body['portfolio'] ?? 'No portfolio data');

// Sanitise portfolio context — cap length to avoid prompt injection bloat
$portCtx = mb_substr($portCtx, 0, 500);

if (!in_array($mode, ['chat', 'suggest'], true)) {
    jsonOut(['ok' => false, 'error' => 'Invalid mode. Use "chat" or "suggest"'], 400);
}

// ── Build system prompt + messages ──────────────────────────
if ($mode === 'suggest') {

    $system = <<<'PROMPT'
You are an NSE India financial AI assistant. Return ONLY valid JSON — no markdown fences, no prose, no extra text.

Response schema:
{
  "portfolio": [
    { "id": "p1", "icon": "emoji", "title": "max 5 words", "desc": "one clear sentence", "side": "buy|sell", "sym": "TICKER.NS", "qty": number }
  ],
  "market": [
    { "id": "m1", "icon": "emoji", "title": "max 5 words", "desc": "one clear sentence", "sym": "TICKER.NS" }
  ]
}

Rules:
- portfolio must have exactly 4 entries (actionable NSE buy/sell trade ideas).
- If the user has no holdings, suggest RELIANCE.NS, TCS.NS, HDFCBANK.NS, INFY.NS.
- market must have exactly 4 entries (currently trending NSE stocks worth watching).
- qty must be a whole number between 1 and 10.
- All symbols must be real NSE tickers with the ".NS" suffix.
PROMPT;

    $msgs   = [['role' => 'user', 'content' => "My NSE portfolio: {$portCtx}. Generate 4 portfolio suggestions and 4 market opportunities."]];
    $maxTok = 1200;

} else {
    // chat mode
    $rawMessages = $body['messages'] ?? [];

    // Validate and sanitise messages array
    $msgs = [];
    foreach (array_slice($rawMessages, -20) as $msg) {
        $role    = ($msg['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
        $content = mb_substr(trim($msg['content'] ?? ''), 0, 2000);
        if ($content !== '') {
            $msgs[] = ['role' => $role, 'content' => $content];
        }
    }

    // Ensure there is at least one user message
    if (empty($msgs)) {
        jsonOut(['ok' => false, 'error' => 'No messages provided'], 400);
    }

    $system = <<<PROMPT
You are an expert NSE India stock market analyst for the Stock Tracking platform.

Focus areas:
- Indian markets: NSE, BSE, SEBI regulations, NIFTY 50, SENSEX
- The 7 featured companies: Reliance Industries, TCS, HDFC Bank, Infosys, SBI, Adani Ports, Wipro
- Portfolio analysis, SIP strategy, risk management, rupee-cost averaging

Current user portfolio:
{$portCtx}

Guidelines:
- Always use ₹ (rupee symbol) for prices, never $ or USD
- Be concise and actionable
- Include a brief risk disclaimer for any investment advice
- Reference current portfolio holdings when relevant
PROMPT;

    $maxTok = 1000;
}

// ── Call Anthropic API ───────────────────────────────────────
$payload = json_encode([
    'model'      => 'claude-sonnet-4-20250514',
    'max_tokens' => $maxTok,
    'system'     => $system,
    'messages'   => $msgs,
]);

if ($payload === false) {
    jsonOut(['ok' => false, 'error' => 'Failed to encode request payload'], 500);
}

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
]);

$result   = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    jsonOut(['ok' => false, 'error' => 'Network error: ' . $curlErr], 502);
}

if ($result === false || $result === '') {
    jsonOut(['ok' => false, 'error' => 'Empty response from Anthropic API'], 502);
}

$data = json_decode($result, true);

if ($httpCode !== 200) {
    $errMsg = $data['error']['message'] ?? "Anthropic API returned HTTP {$httpCode}";
    jsonOut(['ok' => false, 'error' => $errMsg], $httpCode);
}

$text = $data['content'][0]['text'] ?? '';

if ($text === '') {
    jsonOut(['ok' => false, 'error' => 'AI returned an empty response'], 500);
}

jsonOut(['ok' => true, 'text' => $text]);
