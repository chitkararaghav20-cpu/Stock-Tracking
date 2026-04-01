<?php
// ============================================================
//  Stock-Tracking.php  —  Main Entry Point
//  Requires: config.php, auth.php, portfolio.php,
//            watchlist.php, settings.php
//  Database:  setup.sql  (run once to create schema)
// ============================================================
require_once __DIR__ . '/config.php';

// ── Prefetch data for the logged-in user ────────────────────
$phpUser     = currentUser();   // array|null from session
$phpPort     = null;
$phpWl       = null;
$phpSettings = null;

if ($phpUser) {
    $db = getDB();

    // Portfolio
    $s = $db->prepare('SELECT cash, hold_json, txns_json FROM portfolios WHERE user_id = ?');
    $s->execute([$phpUser['id']]);
    $pr = $s->fetch();
    if ($pr) {
        $phpPort = [
            'cash' => (float)$pr['cash'],
            'hold' => json_decode($pr['hold_json'], true) ?: [],
            'txns' => json_decode($pr['txns_json'],  true) ?: [],
        ];
    }

    // Watchlist
    $w = $db->prepare('SELECT symbols FROM watchlists WHERE user_id = ?');
    $w->execute([$phpUser['id']]);
    $wr = $w->fetch();
    if ($wr) {
        $phpWl = json_decode($wr['symbols'], true) ?: [];
    }

    // Settings
    $st = $db->prepare('SELECT settings_json FROM user_settings WHERE user_id = ?');
    $st->execute([$phpUser['id']]);
    $str = $st->fetch();
    if ($str) {
        $phpSettings = json_decode($str['settings_json'], true) ?: null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="#050a14">
<meta name="apple-mobile-web-app-capable" content="yes">
<title>Stock Tracking</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── PHP → JS Data Bridge ─────────────────────────────────────
window.__ST_USER     = <?= json_encode($phpUser)     ?>;
window.__ST_PORT     = <?= json_encode($phpPort)     ?>;
window.__ST_WL       = <?= json_encode($phpWl)       ?>;
window.__ST_SETTINGS = <?= json_encode($phpSettings) ?>;
</script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg0:#050a14;--bg1:#080f1e;--bg2:#0d1628;--bg3:#101c32;--bg4:#152238;
  --border:rgba(255,255,255,0.07);--border-b:rgba(61,142,245,0.2);
  --text:#dce8ff;--text2:#7090b8;--text3:#3d5a80;
  --teal:#00d4aa;--blue:#3d8ef5;--green:#00e396;--red:#ff4560;--gold:#ffc107;
  --font-h:'Syne',sans-serif;--font-b:'DM Sans',sans-serif;--font-m:'Space Mono',monospace;
  --r:12px;--rs:8px;--tr:0.2s ease;
  --sh:0 4px 24px rgba(0,0,0,.4);--sh2:0 8px 48px rgba(0,0,0,.6);
  --gl:0 0 20px rgba(0,212,170,.15);--glb:0 0 20px rgba(61,142,245,.15)
}
html,body{height:100%;background:var(--bg0);color:var(--text);font-family:var(--font-b);font-size:15px;line-height:1.5;overflow-x:hidden}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:var(--bg1)}
::-webkit-scrollbar-thumb{background:var(--bg4);border-radius:3px}
.app{display:flex;height:100vh;overflow:hidden}
/* SIDEBAR */
.sidebar{width:230px;min-width:230px;background:var(--bg1);border-right:1px solid var(--border);display:flex;flex-direction:column;z-index:100}
.sb-logo{padding:20px 18px 16px;border-bottom:1px solid var(--border)}
.logo-t{font-family:var(--font-h);font-size:20px;font-weight:800;background:linear-gradient(135deg,var(--teal),var(--blue));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;letter-spacing:-.5px}
.logo-flag{font-size:16px;margin-right:6px}
.sb-nav{padding:14px 10px;flex:1;display:flex;flex-direction:column;gap:3px;overflow-y:auto}
.nav-i{display:flex;align-items:center;gap:11px;padding:11px 12px;border-radius:var(--rs);cursor:pointer;font-weight:500;color:var(--text2);transition:all var(--tr);border:1px solid transparent;font-size:14px;user-select:none}
.nav-i:hover{background:var(--bg3);color:var(--text);border-color:var(--border)}
.nav-i.active{background:linear-gradient(135deg,rgba(0,212,170,.12),rgba(61,142,245,.12));color:var(--teal);border-color:rgba(0,212,170,.2);box-shadow:var(--gl)}
.nav-ic{font-size:17px;width:19px;text-align:center}
.nav-badge{margin-left:auto;background:var(--teal);color:#000;font-size:9px;font-weight:700;padding:2px 6px;border-radius:20px;font-family:var(--font-m)}
.sb-foot{padding:12px 10px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:6px}
.sb-user-bar{display:none;align-items:center;gap:9px;padding:9px 12px;background:var(--bg3);border-radius:var(--rs);cursor:pointer}
.sb-user-bar:hover{background:var(--bg4)}
.key-btn{width:100%;padding:9px 12px;background:var(--bg3);border:1px solid var(--border);color:var(--text2);border-radius:var(--rs);cursor:pointer;font-family:var(--font-b);font-size:13px;display:flex;align-items:center;gap:8px;transition:all var(--tr)}
.key-btn:hover{background:var(--bg4);color:var(--text);border-color:var(--border-b)}
/* MAIN */
.main{flex:1;overflow-y:auto;overflow-x:hidden;display:flex;flex-direction:column}
/* TOPBAR */
.topbar{position:sticky;top:0;z-index:50;background:rgba(5,10,20,.9);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);padding:10px 22px;display:flex;align-items:center;gap:14px}
.srch-w{display:flex;align-items:center;gap:9px;background:var(--bg2);border:1px solid var(--border);border-radius:var(--rs);padding:8px 12px;flex:1;max-width:380px;transition:all var(--tr)}
.srch-w:focus-within{border-color:var(--teal);box-shadow:0 0 0 3px rgba(0,212,170,.1)}
.srch-w input{background:none;border:none;outline:none;color:var(--text);font-family:var(--font-b);font-size:14px;width:100%;text-transform:uppercase}
.srch-w input::placeholder{color:var(--text3);text-transform:none}
.srch-btn{background:linear-gradient(135deg,var(--teal),var(--blue));border:none;color:#fff;padding:5px 14px;border-radius:6px;cursor:pointer;font-family:var(--font-b);font-weight:600;font-size:13px;transition:all var(--tr)}
.srch-btn:hover{opacity:.9;transform:translateY(-1px)}
.tb-right{display:flex;align-items:center;gap:12px;margin-left:auto}
.pv-wrap .lbl{font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:1px}
.pv-wrap .amt{font-family:var(--font-m);font-size:15px;font-weight:700;color:var(--gold)}
.mkt-st{display:flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:var(--green)}
.mkt-dot{width:6px;height:6px;border-radius:50%;background:var(--green);box-shadow:0 0 6px var(--green);animation:pulse 2s ease-in-out infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
.login-btn-topbar{background:linear-gradient(135deg,var(--teal),var(--blue));border:none;color:#fff;padding:7px 14px;border-radius:var(--rs);font-family:var(--font-b);font-weight:600;font-size:13px;cursor:pointer;transition:all var(--tr);white-space:nowrap;display:flex;align-items:center;gap:6px}
.login-btn-topbar:hover{opacity:.9;transform:translateY(-1px);box-shadow:var(--gl)}
.user-av{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--teal),var(--blue));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#000;flex-shrink:0;cursor:pointer;transition:all .2s}
.user-av:hover{transform:scale(1.07);box-shadow:var(--gl)}
.user-info .u-name{font-size:13px;font-weight:600;color:var(--text)}
.user-info .u-role{font-size:10px;color:var(--text3);margin-top:1px}
/* TICKER */
.ticker-b{background:var(--bg1);border-bottom:1px solid var(--border);padding:7px 0;overflow:hidden;white-space:nowrap}
.ticker-in{display:inline-flex;gap:28px;animation:tick 60s linear infinite}
@keyframes tick{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}
.ticker-i{display:inline-flex;align-items:center;gap:7px;font-family:var(--font-m);font-size:11px}
.t-sym{color:var(--text2);font-weight:700}.t-price{color:var(--text)}
/* PAGES */
.page{display:none;padding:22px;flex:1;animation:fadeIn .3s ease}
.page.active{display:block}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.ph{margin-bottom:22px}
.pt{font-family:var(--font-h);font-size:26px;font-weight:800;letter-spacing:-.5px;color:var(--text)}
.ps{font-size:13px;color:var(--text2);margin-top:3px}
/* GRIDS */
.g4{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.g3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.g7{display:grid;grid-template-columns:repeat(7,1fr);gap:10px;margin-bottom:20px}
/* CARD */
.card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);padding:18px;transition:all var(--tr);position:relative;overflow:hidden}
.card:hover{border-color:rgba(255,255,255,.11);transform:translateY(-1px);box-shadow:var(--sh)}
.c-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.c-tt{font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:1.5px}
/* STAT CARDS */
.sc{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);padding:18px;position:relative;overflow:hidden;transition:all var(--tr)}
.sc::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--teal),var(--blue));opacity:.6}
.sc:hover{border-color:rgba(0,212,170,.15);box-shadow:var(--gl)}
.s-lbl{font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:1.5px;font-weight:600;margin-bottom:7px}
.s-val{font-family:var(--font-m);font-size:21px;font-weight:700;color:var(--text);line-height:1}
.s-chg{font-size:12px;font-family:var(--font-m);margin-top:5px;display:flex;align-items:center;gap:3px}
.up{color:var(--green)}.down{color:var(--red)}.neutral{color:var(--text2)}
/* COMPANY CARDS (7-grid) */
.co-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);padding:13px;cursor:pointer;transition:all var(--tr);position:relative}
.co-card:hover{transform:translateY(-2px);box-shadow:var(--sh);border-color:rgba(255,255,255,.12)}
.co-av{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:8px}
.co-sym{font-family:var(--font-m);font-weight:700;font-size:11.5px}
.co-nm{font-size:10.5px;color:var(--text2);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.co-price{font-family:var(--font-m);font-size:15px;font-weight:700;margin-top:7px}
.co-chg{font-family:var(--font-m);font-size:10.5px;margin-top:3px}
/* STOCK CARDS (list) */
.sk-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);padding:14px;cursor:pointer;transition:all var(--tr);display:flex;align-items:center;gap:11px}
.sk-card:hover{border-color:rgba(0,212,170,.2);background:var(--bg3);transform:translateY(-1px)}
.sk-av{width:38px;height:38px;border-radius:9px;background:linear-gradient(135deg,var(--bg4),var(--bg3));display:flex;align-items:center;justify-content:center;font-family:var(--font-h);font-weight:700;font-size:12px;color:var(--teal);border:1px solid rgba(0,212,170,.15);flex-shrink:0}
.sk-inf{flex:1;min-width:0}
.sk-sym{font-family:var(--font-m);font-weight:700;font-size:13px;color:var(--text)}
.sk-nm{font-size:11px;color:var(--text2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
.sk-pr-w{text-align:right}
.sk-pr{font-family:var(--font-m);font-weight:700;font-size:14px;color:var(--text)}
.sk-ch{font-family:var(--font-m);font-size:11px;margin-top:2px}
/* TABLE */
.tw{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px}
thead th{text-align:left;padding:9px 10px;font-size:10px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:1.5px;border-bottom:1px solid var(--border);white-space:nowrap}
tbody tr{border-bottom:1px solid var(--border);transition:background var(--tr)}
tbody tr:hover{background:var(--bg3)}
tbody tr:last-child{border-bottom:none}
tbody td{padding:11px 10px;vertical-align:middle}
td.mn{font-family:var(--font-m);font-size:12px}
/* BADGE */
.badge{display:inline-flex;align-items:center;gap:3px;padding:3px 7px;border-radius:20px;font-size:10px;font-weight:600;font-family:var(--font-m)}
.b-g{background:rgba(0,227,150,.12);color:var(--green);border:1px solid rgba(0,227,150,.2)}
.b-r{background:rgba(255,69,96,.12);color:var(--red);border:1px solid rgba(255,69,96,.2)}
.b-y{background:rgba(255,193,7,.12);color:var(--gold);border:1px solid rgba(255,193,7,.2)}
/* BUTTONS */
.btn{padding:9px 18px;border-radius:var(--rs);font-family:var(--font-b);font-weight:600;font-size:13px;cursor:pointer;transition:all var(--tr);border:none;display:inline-flex;align-items:center;gap:5px}
.btn-p{background:linear-gradient(135deg,var(--teal),#00a88a);color:#000}
.btn-p:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(0,212,170,.3)}
.btn-buy{background:linear-gradient(135deg,var(--green),#00c87a);color:#000}
.btn-buy:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(0,227,150,.3)}
.btn-sell{background:linear-gradient(135deg,var(--red),#e03050);color:#fff}
.btn-sell:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(255,69,96,.3)}
.btn-out{background:transparent;border:1px solid var(--border);color:var(--text2)}
.btn-out:hover{border-color:var(--teal);color:var(--teal)}
.btn-sm{padding:5px 10px;font-size:12px}
/* FORM */
.fg{margin-bottom:14px}
.fl{display:block;font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:1px;margin-bottom:5px}
.fi{width:100%;background:var(--bg1);border:1px solid var(--border);border-radius:var(--rs);padding:9px 12px;color:var(--text);font-family:var(--font-b);font-size:14px;outline:none;transition:all var(--tr)}
.fi:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(0,212,170,.1)}
.fi::placeholder{color:var(--text3)}
.fi[type="number"]{font-family:var(--font-m)}
/* MODAL */
.mo{position:fixed;inset:0;background:rgba(0,0,0,.75);backdrop-filter:blur(4px);z-index:1000;display:none;align-items:center;justify-content:center;padding:18px}
.mo.open{display:flex}
.mo-b{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);padding:26px;width:100%;max-width:420px;animation:moIn .3s ease}
.mo-b-wide{max-width:520px}
@keyframes moIn{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:scale(1)}}
.mo-t{font-family:var(--font-h);font-size:19px;font-weight:700;margin-bottom:7px}
.mo-d{font-size:13px;color:var(--text2);margin-bottom:18px;line-height:1.6}
.mo-ft{display:flex;gap:9px;margin-top:18px}
/* TRADE MODAL */
.tr-tabs{display:flex;gap:7px;margin-bottom:14px}
.tr-t{flex:1;padding:9px;border-radius:var(--rs);border:1px solid var(--border);background:transparent;font-family:var(--font-b);font-weight:600;font-size:13px;cursor:pointer;transition:all var(--tr);color:var(--text2)}
.tr-t.buy.active{background:rgba(0,227,150,.12);color:var(--green);border-color:rgba(0,227,150,.3)}
.tr-t.sell.active{background:rgba(255,69,96,.12);color:var(--red);border-color:rgba(255,69,96,.3)}
.tr-t:hover:not(.active){background:var(--bg3);color:var(--text)}
.tr-sum{background:var(--bg1);border:1px solid var(--border);border-radius:var(--rs);padding:11px;font-size:13px}
.tr-row{display:flex;justify-content:space-between;align-items:center;padding:5px 0}
.tr-row:not(:last-child){border-bottom:1px solid var(--border)}
.tr-lbl{color:var(--text2)}.tr-val{font-family:var(--font-m);font-weight:700}
/* AUTH MODAL */
.auth-tabs{display:flex;border-bottom:1px solid var(--border);margin-bottom:20px}
.auth-tab{flex:1;padding:10px;background:none;border:none;font-family:var(--font-b);font-size:13px;font-weight:600;color:var(--text2);cursor:pointer;transition:all var(--tr);border-bottom:2px solid transparent;margin-bottom:-1px}
.auth-tab.active{color:var(--teal);border-bottom-color:var(--teal)}
.auth-tab:hover:not(.active){color:var(--text)}
.auth-panel{display:none}.auth-panel.active{display:block}
.social-btn{width:100%;padding:10px;background:var(--bg3);border:1px solid var(--border);border-radius:var(--rs);color:var(--text);font-family:var(--font-b);font-size:13px;cursor:pointer;transition:all var(--tr);margin-bottom:9px;display:flex;align-items:center;justify-content:center;gap:9px}
.social-btn:hover{background:var(--bg4);border-color:var(--border-b)}
.divider{display:flex;align-items:center;gap:10px;margin:12px 0;color:var(--text3);font-size:11px}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--border)}
/* SETTINGS */
.set-section{margin-bottom:20px}
.set-sec-t{font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:10px}
.set-row{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:var(--bg1);border:1px solid var(--border);border-radius:var(--rs);margin-bottom:7px;gap:10px}
.set-row-info .sr-t{font-size:13px;font-weight:500;color:var(--text)}
.set-row-info .sr-s{font-size:11px;color:var(--text3);margin-top:2px}
.set-toggle{position:relative;width:40px;height:22px;flex-shrink:0}
.set-toggle input{opacity:0;width:0;height:0}
.tog-sl{position:absolute;inset:0;background:var(--bg4);border-radius:11px;cursor:pointer;transition:.3s}
.tog-sl::before{content:'';position:absolute;width:16px;height:16px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.3s}
input:checked+.tog-sl{background:var(--teal)}
input:checked+.tog-sl::before{transform:translateX(18px)}
.set-select{background:var(--bg1);border:1px solid var(--border);border-radius:6px;color:var(--text);font-family:var(--font-b);font-size:12px;padding:5px 8px;cursor:pointer;outline:none;transition:border-color var(--tr)}
.set-select:focus{border-color:var(--teal)}
/* CHARTS */
.cp-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:8px}
.cp-tt{font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:1.5px}
.cp-sub{font-size:11px;color:var(--text3);margin-top:3px}
.cp-tabs{display:flex;gap:3px;flex-wrap:wrap}
.cp-tab{padding:4px 9px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--text3);font-size:11px;font-weight:600;cursor:pointer;transition:all var(--tr);font-family:var(--font-b)}
.cp-tab.active{background:rgba(0,212,170,.12);color:var(--teal);border-color:rgba(0,212,170,.3)}
.cp-tab:hover:not(.active){background:var(--bg3);color:var(--text)}
/* PIE LEGEND */
.pie-legend{display:flex;flex-direction:column;gap:5px}
.pl-item{display:flex;align-items:center;gap:8px;padding:4px 0;border-bottom:1px solid rgba(255,255,255,.04)}
.pl-item:last-child{border-bottom:none}
.pl-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.pl-name{color:var(--text2);flex:1;font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pl-val{font-family:var(--font-m);font-size:11px;font-weight:700}
/* AI CHAT */
.chat-c{display:flex;flex-direction:column;height:calc(100vh - 210px);min-height:380px}
.chat-m{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:14px;background:var(--bg1);border-radius:var(--r) var(--r) 0 0;border:1px solid var(--border);border-bottom:none}
.msg{display:flex;gap:10px;align-items:flex-start;animation:slideIn .3s ease}
@keyframes slideIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.msg.user{flex-direction:row-reverse}
.m-av{width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0}
.m-av.ai{background:linear-gradient(135deg,var(--teal),var(--blue));color:#000;font-weight:700;font-family:var(--font-h);font-size:11px}
.m-av.user{background:var(--bg4);color:var(--text2)}
.m-cnt{flex:1;background:var(--bg2);border:1px solid var(--border);border-radius:11px;padding:11px 13px;font-size:13px;line-height:1.65;max-width:82%}
.msg.user .m-cnt{background:rgba(0,212,170,.1);border-color:rgba(0,212,170,.2)}
.m-cnt p{margin-bottom:7px}.m-cnt p:last-child{margin-bottom:0}
.m-cnt strong{color:var(--teal)}
.chat-in-w{display:flex;gap:9px;padding:12px;background:var(--bg2);border:1px solid var(--border);border-radius:0 0 var(--r) var(--r);border-top:1px solid rgba(0,212,170,.1)}
.chat-in{flex:1;background:var(--bg1);border:1px solid var(--border);border-radius:7px;padding:9px 12px;color:var(--text);font-family:var(--font-b);font-size:13px;outline:none;resize:none;min-height:40px;max-height:110px;transition:border-color var(--tr)}
.chat-in:focus{border-color:var(--teal)}
.send-btn{background:linear-gradient(135deg,var(--teal),var(--blue));border:none;color:#fff;width:40px;height:40px;border-radius:7px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;transition:all var(--tr);flex-shrink:0}
.send-btn:hover{transform:scale(1.05);box-shadow:0 4px 12px rgba(0,212,170,.3)}
.send-btn:disabled{opacity:.5;cursor:not-allowed;transform:none}
/* CHIPS */
.chips{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:14px}
.chip{padding:5px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:20px;font-size:12px;color:var(--text2);cursor:pointer;transition:all var(--tr)}
.chip:hover{background:rgba(0,212,170,.1);border-color:rgba(0,212,170,.3);color:var(--teal)}
/* SUGGESTIONS */
.sug-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(185px,1fr));gap:11px}
.sug-card{background:var(--bg1);border:1px solid var(--border);border-radius:var(--r);padding:16px;transition:all var(--tr);display:flex;flex-direction:column;gap:9px;position:relative;overflow:hidden}
.sug-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--teal),var(--blue));opacity:0;transition:opacity var(--tr)}
.sug-card:hover{border-color:rgba(0,212,170,.22);background:var(--bg3);transform:translateY(-2px);box-shadow:var(--sh)}
.sug-card:hover::before{opacity:1}
.sug-ic{font-size:30px;line-height:1;margin-bottom:2px}
.sug-tt{font-family:var(--font-h);font-size:13px;font-weight:700;color:var(--text);line-height:1.2}
.sug-ds{font-size:11px;color:var(--text2);flex:1;line-height:1.5}
.apply-btn{width:100%;padding:8px;border-radius:var(--rs);background:linear-gradient(135deg,var(--teal),var(--blue));border:none;color:#000;font-family:var(--font-b);font-weight:700;font-size:12px;cursor:pointer;transition:all var(--tr);margin-top:2px}
.apply-btn:hover:not([disabled]){transform:translateY(-1px);box-shadow:0 4px 14px rgba(0,212,170,.35);opacity:.92}
.apply-btn[disabled]{cursor:not-allowed;background:rgba(0,227,150,.15);color:var(--green);border:1px solid rgba(0,227,150,.3)}
.gen-btn-wrap{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:22px;background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);padding:16px}
.gen-btn-wrap .info .title{font-family:var(--font-h);font-size:15px;font-weight:700;color:var(--text)}
.gen-btn-wrap .info .sub{font-size:12px;color:var(--text2);margin-top:2px}
.sug-section{margin-bottom:22px}
.sug-sec-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid var(--border)}
.sug-sec-t{font-family:var(--font-h);font-size:15px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px}
.sug-sec-s{font-size:11px;color:var(--text3)}
.theme-active-ring{box-shadow:0 0 0 2px var(--teal)!important;border-color:var(--teal)!important}
/* MARKETS */
.mkt-tbl{width:100%;border-collapse:collapse;font-size:12px}
.mkt-tbl th{text-align:left;padding:8px 10px;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1.2px;border-bottom:1px solid var(--border)}
.mkt-tbl td{padding:9px 10px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:middle}
.mkt-tbl tr:last-child td{border-bottom:none}
.mkt-tbl tr:hover td{background:var(--bg3)}
.bar-fill{height:6px;border-radius:3px;background:linear-gradient(90deg,var(--teal),var(--blue));transition:width .6s ease}
.bar-track{background:var(--bg4);border-radius:3px;overflow:hidden;flex:1}
/* STOCK DETAIL */
.sk-det{background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);padding:18px;margin-bottom:22px}
.sk-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--border)}
.m-itm .m-lbl{font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:1px;margin-bottom:3px}
.m-itm .m-val{font-family:var(--font-m);font-size:12px;color:var(--text);font-weight:700}
/* LOADING / EMPTY / SHIMMER */
.loading{display:flex;align-items:center;justify-content:center;padding:36px;color:var(--text2);gap:9px;font-size:13px}
.spin{width:18px;height:18px;border:2px solid var(--border);border-top-color:var(--teal);border-radius:50%;animation:spin .8s linear infinite;flex-shrink:0}
@keyframes spin{to{transform:rotate(360deg)}}
.empty{text-align:center;padding:44px 18px;color:var(--text2)}
.ei{font-size:44px;margin-bottom:11px}.et{font-size:15px;font-weight:600;color:var(--text);margin-bottom:7px}
.ed{font-size:12px;color:var(--text2);max-width:260px;margin:0 auto}
.shim{background:linear-gradient(90deg,var(--bg2) 25%,var(--bg3) 50%,var(--bg2) 75%);background-size:200% 100%;animation:shim 1.5s infinite;border-radius:4px}
@keyframes shim{0%{background-position:200% 0}100%{background-position:-200% 0}}
.typing{display:flex;align-items:center;gap:4px;padding:11px 13px}
.ty-d{width:6px;height:6px;border-radius:50%;background:var(--teal);animation:tyB 1.4s ease-in-out infinite}
.ty-d:nth-child(2){animation-delay:.2s}.ty-d:nth-child(3){animation-delay:.4s}
@keyframes tyB{0%,60%,100%{transform:translateY(0);opacity:.4}30%{transform:translateY(-6px);opacity:1}}
/* TOAST */
.toast{position:fixed;bottom:22px;right:22px;background:var(--bg3);border:1px solid var(--border);border-radius:var(--rs);padding:11px 15px;font-size:13px;z-index:9999;animation:toIn .3s ease;max-width:300px;display:flex;align-items:center;gap:9px;box-shadow:var(--sh2)}
.toast.success{border-color:rgba(0,227,150,.3)}.toast.error{border-color:rgba(255,69,96,.3)}
@keyframes toIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
/* WARN BOX */
.warn-box{background:rgba(255,193,7,.05);border:1px solid rgba(255,193,7,.25);border-radius:var(--r);padding:14px;margin-bottom:16px;display:flex;align-items:center;gap:12px}
/* MISC */
.df{display:flex}.aic{align-items:center}.jsb{justify-content:space-between}
.gap8{gap:8px}.gap12{gap:12px}.mb16{margin-bottom:16px}.mb22{margin-bottom:22px}
.ts{font-size:12px}.txs{font-size:10px}.fmo{font-family:var(--font-m)}.tm{color:var(--text2)}.tg{color:var(--gold)}
.rm-btn{background:none;border:none;color:var(--text3);cursor:pointer;font-size:15px;padding:3px;border-radius:4px;transition:all var(--tr)}
.rm-btn:hover{color:var(--red);background:rgba(255,69,96,.1)}
/* NOTIFICATION BUTTON */
.notif-btn {
  position: relative;
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: var(--rs);
  color: var(--text2);
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 16px;
  transition: all var(--tr);
  flex-shrink: 0;
}
.notif-btn:hover {
  background: var(--bg3);
  border-color: var(--border-b);
  color: var(--gold);
  transform: translateY(-1px);
}
.notif-badge {
  position: absolute;
  top: -5px;
  right: -5px;
  background: var(--red);
  color: #fff;
  font-size: 9px;
  font-weight: 700;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--font-m);
  border: 1px solid var(--bg0);
}
/* NOTIFICATION PANEL */
.notif-panel {
  position: fixed;
  top: 60px;
  right: 16px;
  width: 340px;
  max-height: 520px;
  background: var(--bg2);
  border: 1px solid var(--border-b);
  border-radius: var(--r);
  box-shadow: var(--sh2);
  z-index: 500;
  display: none;
  flex-direction: column;
  overflow: hidden;
  animation: moIn .25s ease;
}
.notif-panel.open { display: flex; }
.notif-hd {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px 12px;
  border-bottom: 1px solid var(--border);
}
.notif-hd-t {
  font-family: var(--font-h);
  font-size: 15px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 7px;
}
.notif-body { overflow-y: auto; flex: 1; padding: 12px; display: flex; flex-direction: column; gap: 8px; }
.notif-add {
  display: flex;
  gap: 7px;
  padding: 10px 12px;
  background: var(--bg1);
  border-top: 1px solid var(--border);
}
.notif-inp {
  flex: 1;
  background: var(--bg3);
  border: 1px solid var(--border);
  border-radius: var(--rs);
  color: var(--text);
  font-family: var(--font-b);
  font-size: 12px;
  padding: 6px 9px;
  outline: none;
  transition: border-color var(--tr);
  text-transform: uppercase;
}
.notif-inp:focus { border-color: var(--teal); }
.notif-inp::placeholder { text-transform: none; color: var(--text3); }
.notif-item {
  background: var(--bg1);
  border: 1px solid var(--border);
  border-radius: var(--rs);
  padding: 10px 12px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.notif-item.triggered {
  border-color: rgba(0,227,150,.3);
  background: rgba(0,227,150,.05);
}
.notif-sym { font-family: var(--font-m); font-weight: 700; font-size: 12px; color: var(--text); min-width: 60px; }
.notif-cond { font-size: 11px; color: var(--text2); flex: 1; }
.notif-trg-lbl { font-size: 10px; color: var(--green); font-weight: 700; font-family: var(--font-m); }
/* COMPACT MODE — hides secondary table columns */
.compact-mode .col-avg,
.compact-mode .col-return { display: none; }
/* WATCHLIST GRID */
.wl-g{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:11px}
/* MOBILE HAMBURGER + SIDEBAR OVERLAY */
.hamburger-btn {
  display: none;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: var(--rs);
  cursor: pointer;
  color: var(--text2);
  font-size: 18px;
  transition: all var(--tr);
  flex-shrink: 0;
}
.hamburger-btn:hover { background: var(--bg3); color: var(--text); }

.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.55);
  z-index: 99;
  backdrop-filter: blur(2px);
}
.sidebar-overlay.open { display: block; }

/* On small screens the sidebar slides in over content as a drawer */
@media(max-width:560px) {
  .hamburger-btn { display: flex; }

  /* Hide sidebar off-screen (NOT display:none — that kills the slide animation) */
  .sidebar {
    position: fixed;
    left: -240px;
    top: 0;
    height: 100vh;
    z-index: 200;
    transition: left 0.25s ease;
    display: flex !important;   /* override the base flex; keep it renderable */
  }

  /* Slide into view when JS adds this class */
  .sidebar.mob-open { left: 0; }
}
/* STOCK DETAIL PANEL */
.sk-det-hd{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;flex-wrap:wrap}
.sk-det-pr{font-family:var(--font-m);font-size:24px;font-weight:700;color:var(--text)}
.sk-det-ch{font-family:var(--font-m);font-size:13px;margin-top:4px}
.ch-w{position:relative;height:240px;margin-bottom:14px}
/* RESPONSIVE */
@media(max-width:900px){.g7{grid-template-columns:repeat(4,1fr)}}
@media(max-width:768px){
  .sidebar{width:200px;min-width:200px}
  .topbar{padding:8px 14px}
  .page{padding:14px}
  .g4{grid-template-columns:repeat(2,1fr);gap:10px}
  .g2,.g3{grid-template-columns:1fr}
  .g7{grid-template-columns:repeat(2,1fr)}
  .pt{font-size:22px}
  .chat-c{height:calc(100vh - 180px)}
  .mo-b{padding:18px}
  .toast{bottom:22px;left:14px;right:14px;max-width:none}
  .sk-meta{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:560px){
  .g4{grid-template-columns:1fr 1fr}
  .g7{grid-template-columns:repeat(2,1fr)}
}
</style>
</head>
<body>


<!-- ════ TRADE MODAL ════ -->
<div class="mo" id="mo-trade">
  <div class="mo-b">
    <div class="mo-t" id="tr-title">💰 Buy Shares</div>
    <div class="tr-tabs">
      <button class="tr-t buy active" id="tb-buy" onclick="setTrMode('buy')">BUY</button>
      <button class="tr-t sell" id="tb-sell" onclick="setTrMode('sell')">SELL</button>
    </div>
    <div class="fg"><label class="fl">NSE Symbol</label><input type="text" class="fi" id="tr-sym" placeholder="e.g. RELIANCE" oninput="onTradeInput()"></div>
    <div class="fg"><label class="fl">Quantity (Shares)</label><input type="number" class="fi" id="tr-qty" placeholder="0" min="1" step="1" oninput="updTrSum()"></div>
    <div class="tr-sum">
      <div class="tr-row"><span class="tr-lbl">Market Price</span><span class="tr-val" id="tr-p">—</span></div>
      <div class="tr-row"><span class="tr-lbl">Est. Total</span><span class="tr-val" id="tr-tot">—</span></div>
      <div class="tr-row"><span class="tr-lbl">Cash Balance</span><span class="tr-val tg" id="tr-cash">—</span></div>
    </div>
    <div class="mo-ft">
      <button class="btn btn-out" onclick="closeModal('mo-trade')">Cancel</button>
      <button class="btn btn-buy" id="tr-conf-btn" onclick="confirmTrade()">Confirm Buy</button>
    </div>
  </div>
</div>

<!-- ════ AUTH + SETTINGS MODAL ════ -->
<div class="mo" id="mo-auth">
  <div class="mo-b mo-b-wide">
    <div class="auth-tabs">
      <button class="auth-tab active" id="at-login"    onclick="authTab('login')">🔐 Log In</button>
      <button class="auth-tab"        id="at-signup"   onclick="authTab('signup')">✨ Sign Up</button>
      <button class="auth-tab"        id="at-settings" onclick="authTab('settings')">⚙️ Settings</button>
    </div>
    <!-- LOGIN -->
    <div class="auth-panel active" id="ap-login">
      <div id="lv-logged" style="display:none;text-align:center;padding:12px 0 18px">
        <div class="user-av" style="width:56px;height:56px;font-size:20px;margin:0 auto 12px">👤</div>
        <div style="font-family:var(--font-h);font-size:18px;font-weight:700" id="lv-name">User</div>
        <div style="font-size:12px;color:var(--text2);margin-top:4px" id="lv-email"></div>
        <div style="display:inline-flex;align-items:center;gap:5px;background:rgba(0,227,150,.1);border:1px solid rgba(0,227,150,.2);color:var(--green);font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;margin-top:8px">✓ Logged In</div>
        <button class="btn btn-out" style="width:100%;margin-top:14px" onclick="logOut()">🚪 Log Out</button>
      </div>
      <div id="lv-form">
        <button class="social-btn" onclick="socialLogin('Google')"><span style="font-size:17px">G</span>Continue with Google</button>
        <button class="social-btn" onclick="socialLogin('Apple')"><span style="font-size:17px">🍎</span>Continue with Apple</button>
        <div class="divider">or</div>
        <div class="fg"><label class="fl">Email</label><input type="email" class="fi" id="l-email" placeholder="you@email.com"></div>
        <div class="fg"><label class="fl">Password</label><input type="password" class="fi" id="l-pass" placeholder="••••••••"></div>
        <div class="mo-ft" style="margin-top:0">
          <button class="btn btn-out" onclick="closeModal('mo-auth')">Cancel</button>
          <button class="btn btn-p" onclick="doLogin()">Log In →</button>
        </div>
        <div style="text-align:center;font-size:12px;color:var(--text2);margin-top:12px">No account? <span style="color:var(--teal);cursor:pointer;font-weight:600" onclick="authTab('signup')">Sign Up free</span></div>
      </div>
    </div>
    <!-- SIGN UP -->
    <div class="auth-panel" id="ap-signup">
      <button class="social-btn" onclick="socialLogin('Google')"><span style="font-size:17px">G</span>Sign up with Google</button>
      <button class="social-btn" onclick="socialLogin('Apple')"><span style="font-size:17px">🍎</span>Sign up with Apple</button>
      <div class="divider">or create account</div>
      <div class="fg"><label class="fl">Full Name</label><input type="text" class="fi" id="s-name" placeholder="Your Name"></div>
      <div class="fg"><label class="fl">Email</label><input type="email" class="fi" id="s-email" placeholder="you@email.com"></div>
      <div class="fg"><label class="fl">Password</label><input type="password" class="fi" id="s-pass" placeholder="Min 8 characters"></div>
      <div class="mo-ft" style="margin-top:0">
        <button class="btn btn-out" onclick="closeModal('mo-auth')">Cancel</button>
        <button class="btn btn-p" onclick="doSignup()">Create Account →</button>
      </div>
      <div style="text-align:center;font-size:12px;color:var(--text2);margin-top:12px">Already have an account? <span style="color:var(--teal);cursor:pointer;font-weight:600" onclick="authTab('login')">Log In</span></div>
    </div>
    <!-- SETTINGS -->
    <div class="auth-panel" id="ap-settings" style="max-height:420px;overflow-y:auto">
      <div class="set-section">
        <div class="set-sec-t">🎨 Appearance</div>
        <div class="set-row"><div class="set-row-info"><div class="sr-t">Color Theme</div><div class="sr-s">Pick a visual style</div></div>
          <select class="set-select" id="set-theme" onchange="settingChanged('theme',this.value)">
            <option value="dark">🌙 Dark</option>
            <option value="white">🌞 White</option>
            <option value="blue">💙 Blue</option>
          </select></div>
        <div class="set-row"><div class="set-row-info"><div class="sr-t">Corner Style</div><div class="sr-s">Card &amp; button rounding</div></div>
          <select class="set-select" id="set-layout" onchange="settingChanged('layout',this.value)">
            <option value="default">Default</option><option value="rounded">Rounded</option><option value="sharp">Sharp</option>
          </select></div>
        <div class="set-row"><div class="set-row-info"><div class="sr-t">Font Size</div><div class="sr-s">UI text size</div></div>
          <select class="set-select" id="set-font" onchange="settingChanged('font',this.value)">
            <option value="14">Small</option><option value="15" selected>Normal</option><option value="16">Large</option><option value="17">X-Large</option>
          </select></div>
      </div>
      <div class="set-section">
        <div class="set-sec-t">📊 Dashboard</div>
        <div class="set-row"><div class="set-row-info"><div class="sr-t">Auto-Refresh</div><div class="sr-s">Refresh prices every 5 min</div></div>
          <label class="set-toggle"><input type="checkbox" id="set-autoref" checked onchange="settingChanged('autoref',this.checked)"><span class="tog-sl"></span></label></div>
        <div class="set-row"><div class="set-row-info"><div class="sr-t">Show Ticker Bar</div><div class="sr-s">Scrolling price ticker</div></div>
          <label class="set-toggle"><input type="checkbox" id="set-ticker" checked onchange="settingChanged('ticker',this.checked)"><span class="tog-sl"></span></label></div>
      </div>
      <div class="set-section">
        <div class="set-sec-t">💼 Portfolio</div>
        <div class="set-row"><div class="set-row-info"><div class="sr-t">Compact Holdings</div><div class="sr-s">Hide less important columns</div></div>
          <label class="set-toggle"><input type="checkbox" id="set-compact" onchange="settingChanged('compact',this.checked)"><span class="tog-sl"></span></label></div>
        <div class="set-row"><div class="set-row-info"><div class="sr-t">Reset Portfolio</div><div class="sr-s">Start fresh with ₹10,00,000 cash</div></div>
          <button class="btn btn-sell btn-sm" onclick="resetPortfolio()">Reset</button></div>
      </div>
      <div class="set-section">
        <div class="set-sec-t">🔔 Notifications</div>
        <div class="set-row"><div class="set-row-info"><div class="sr-t">Trade Confirmations</div><div class="sr-s">Toast on every trade</div></div>
          <label class="set-toggle"><input type="checkbox" id="set-notif" checked onchange="settingChanged('notif',this.checked)"><span class="tog-sl"></span></label></div>
        <div class="set-row"><div class="set-row-info"><div class="sr-t">Sound Effects</div><div class="sr-s">Subtle audio on actions</div></div>
          <label class="set-toggle"><input type="checkbox" id="set-sound" onchange="settingChanged('sound',this.checked)"><span class="tog-sl"></span></label></div>
      </div>
    </div>
  </div>
</div>

<!-- ════ PRICE ALERT PANEL ════ -->
<div class="notif-panel" id="notif-panel">
  <div class="notif-hd">
    <div class="notif-hd-t">🔔 Price Alerts</div>
    <button class="rm-btn" style="font-size:18px" onclick="closeNotifPanel()" aria-label="Close alerts panel">✕</button>
  </div>
  <div class="notif-body" id="notif-list">
    <div class="empty" style="padding:28px 14px">
      <div class="ei">🔔</div>
      <div class="et">No alerts set</div>
      <div class="ed">Add a stock symbol and target price below to get notified</div>
    </div>
  </div>
  <div class="notif-add">
    <input class="notif-inp" id="na-sym" placeholder="Symbol (e.g. TCS)" style="max-width:90px">
    <select id="na-dir" style="background:var(--bg3);border:1px solid var(--border);border-radius:var(--rs);color:var(--text);font-size:12px;padding:6px 6px;outline:none;cursor:pointer">
      <option value="above">≥ Above</option>
      <option value="below">≤ Below</option>
    </select>
    <input class="notif-inp" id="na-price" type="number" placeholder="₹ Price" style="max-width:80px;text-transform:none">
    <button class="btn btn-p btn-sm" onclick="addPriceAlert()" style="white-space:nowrap;flex-shrink:0">+ Add</button>
  </div>
</div>

<!-- Sidebar overlay for mobile (closes sidebar on tap-outside) -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeMobSidebar()"></div>

<!-- ════ APP ════ -->
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="sb-logo">
      <div><span class="logo-flag">🇮🇳</span><span class="logo-t">Stock Tracking</span></div>
      <div style="font-size:9px;color:var(--text3);text-transform:uppercase;letter-spacing:2px;margin-top:3px">National Stock Exchange</div>
    </div>
    <nav class="sb-nav">
      <div class="nav-i active" id="n-dashboard" onclick="tab('dashboard')"><span class="nav-ic">📊</span><span>Dashboard</span></div>
      <div class="nav-i" id="n-markets" onclick="tab('markets')"><span class="nav-ic">🏦</span><span>Markets</span><span class="nav-badge">7</span></div>
      <div class="nav-i" id="n-portfolio" onclick="tab('portfolio')"><span class="nav-ic">💼</span><span>Portfolio</span></div>
      <div class="nav-i" id="n-watchlist" onclick="tab('watchlist')"><span class="nav-ic">👁</span><span>Watchlist</span></div>
      <div class="nav-i" id="n-analyst" onclick="tab('analyst')"><span class="nav-ic">🤖</span><span>AI Analyst</span></div>
      <div class="nav-i" id="n-suggestions" onclick="tab('suggestions')"><span class="nav-ic">💡</span><span>Suggestions</span></div>
    </nav>
    <div class="sb-foot">
      <div class="sb-user-bar" id="sb-user-bar" onclick="openAuth('settings')">
        <div class="user-av" style="width:30px;height:30px;font-size:13px" id="sb-av">👤</div>
        <div class="user-info" style="flex:1;min-width:0"><div class="u-name" id="sb-uname" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:12px">User</div><div class="u-role">Member</div></div>
        <span style="color:var(--text3);font-size:13px">⚙</span>
      </div>
      <button id="sb-login-btn" class="btn btn-p" style="width:100%;margin-bottom:2px;justify-content:center" onclick="openAuth('login')">🔐 Log In / Sign Up</button>
      <button class="key-btn" style="margin-top:4px" onclick="openAuth('settings')"><span>⚙️</span><span>Settings</span></button>
    </div>
  </aside>

  <div class="main" id="main">
    <!-- Topbar: search + market status + portfolio value -->
    <div class="topbar">
      <!-- Hamburger — only visible on small screens when sidebar is hidden -->
      <button class="hamburger-btn" onclick="openMobSidebar()" title="Menu">☰</button>
      <div class="srch-w">
        <span style="color:var(--text3)">🔍</span>
        <input type="text" id="srch-inp" placeholder="Search NSE symbol (RELIANCE, TCS...)" onkeydown="if(event.key==='Enter')doSearch()">
        <button class="srch-btn" onclick="doSearch()">Go</button>
      </div>
      <div class="tb-right">
        <div class="mkt-st"><div class="mkt-dot" id="mkt-dot"></div><span id="mkt-txt">NSE</span></div>
        <div class="pv-wrap"><div class="lbl">Portfolio</div><div class="amt" id="top-pv">₹10,00,000</div></div>
        <button class="notif-btn" id="notif-bell-btn" onclick="openNotifPanel()" title="Price Alerts">
          🔔
          <span class="notif-badge" id="notif-badge" style="display:none">0</span>
        </button>
        <button class="btn btn-out btn-sm" onclick="openAuth('settings')" style="padding:7px 10px">⚙️</button>
        <button class="login-btn-topbar" id="top-login-btn" onclick="openAuth('login')">🔐 Log In</button>
        <div id="top-user-bar" style="display:none;cursor:pointer;align-items:center;gap:8px" onclick="openAuth('login')">
          <div class="user-av" id="top-av">👤</div>
          <div class="user-info"><div class="u-name" id="top-uname">User</div><div class="u-role">NSE Member</div></div>
        </div>
      </div>
    </div>
    <!-- Ticker -->
    <div class="ticker-b"><div class="ticker-in" id="ticker">Loading NSE data…</div></div>

    <!-- ════ DASHBOARD ════ -->
    <div class="page active" id="p-dashboard">
      <div class="ph"><div class="pt">🇮🇳 NSE Dashboard</div><div class="ps">Live National Stock Exchange data — 7 top Indian companies</div></div>
      <div class="g4" id="stats-g">
        <div class="sc"><div class="s-lbl">Portfolio Value</div><div class="s-val" id="s-pv">₹10,00,000</div><div class="s-chg neutral">—</div></div>
        <div class="sc"><div class="s-lbl">Cash Balance</div><div class="s-val" id="s-cash">₹10,00,000</div><div class="s-chg neutral">Available</div></div>
        <div class="sc"><div class="s-lbl">Total P&amp;L</div><div class="s-val" id="s-pnl">₹0.00</div><div class="s-chg neutral" id="s-pnlp">0.00%</div></div>
        <div class="sc"><div class="s-lbl">Positions</div><div class="s-val" id="s-pos">0</div><div class="s-chg neutral">Holdings</div></div>
      </div>
      <div id="det-panel" style="display:none"></div>
      <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:10px;font-family:var(--font-m)">NSE Top 7 Companies</div>
      <div class="g7 mb22" id="dash-co-cards"></div>
      <div class="g2 mb22">
        <div class="card">
          <div class="cp-hd">
            <div><div class="cp-tt">📈 Share Price Trend</div><div class="cp-sub">% change — all 7 companies</div></div>
            <div class="cp-tabs" id="dash-range-tabs">
              <button class="cp-tab active" onclick="setDashRange('1wk','1d',this)">1W</button>
              <button class="cp-tab" onclick="setDashRange('1mo','1d',this)">1M</button>
              <button class="cp-tab" onclick="setDashRange('3mo','1d',this)">3M</button>
              <button class="cp-tab" onclick="setDashRange('1y','1wk',this)">1Y</button>
            </div>
          </div>
          <div style="position:relative;height:240px" id="dash-line-wrap"><canvas id="cv-dash-line"></canvas></div>
        </div>
        <div class="card">
          <div class="cp-hd"><div><div class="cp-tt">🥧 Market Cap Share</div><div class="cp-sub">Relative size — ₹ Lakh Crore</div></div></div>
          <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <canvas id="cv-dash-pie" width="160" height="160" style="flex-shrink:0"></canvas>
            <div class="pie-legend" id="dash-pie-legend" style="flex:1;min-width:120px"></div>
          </div>
        </div>
      </div>
    </div><!-- /.page#p-dashboard -->

    <!-- ════ MARKETS ════ -->
    <div class="page" id="p-markets">
      <div class="ph">
        <div class="df aic jsb" style="flex-wrap:wrap;gap:10px">
          <div><div class="pt">🏦 NSE Top 7 Companies</div><div class="ps">Share price · market cap · valuation — Reliance, TCS, HDFC, Infosys, SBI, Adani, Wipro</div></div>
          <button class="btn btn-out btn-sm" id="mkt-refresh-btn" onclick="loadMarketsPage()">↻ Refresh</button>
        </div>
      </div>
      <div class="g7 mb22" id="mkt-co-cards"></div>
      <!-- Prev Close vs Current Bar -->
      <div class="card mb22">
        <div class="cp-hd">
          <div><div class="cp-tt">📊 Previous Close vs Current Share Price (₹)</div><div class="cp-sub">Yesterday vs today — all 7 companies</div></div>
          <div style="display:flex;gap:14px;font-size:11px;align-items:center;flex-wrap:wrap">
            <span style="display:flex;align-items:center;gap:4px"><span style="width:11px;height:11px;border-radius:3px;background:#3d5a80;display:inline-block"></span>Prev Close</span>
            <span style="display:flex;align-items:center;gap:4px"><span style="width:11px;height:11px;border-radius:3px;background:var(--teal);display:inline-block"></span>Current</span>
          </div>
        </div>
        <div style="position:relative;height:320px"><canvas id="cv-pvc"></canvas></div>
      </div>
      <!-- Market Cap + Pie row -->
      <div class="g2 mb22">
        <div class="card">
          <div class="cp-hd">
            <div><div class="cp-tt">🏆 Market Capitalisation</div><div class="cp-sub">Total company value (₹ Lakh Crore)</div></div>
            <div class="cp-tabs" id="mktcap-tabs">
              <button class="cp-tab active" onclick="setMktUnit('LC',this)">Lakh Cr</button>
              <button class="cp-tab" onclick="setMktUnit('Cr',this)">Crore</button>
            </div>
          </div>
          <div style="position:relative;height:280px"><canvas id="cv-mktcap"></canvas></div>
        </div>
        <div class="card">
          <div class="cp-hd">
            <div><div class="cp-tt">🥧 Valuation Distribution</div><div class="cp-sub">Market cap share of all 7</div></div>
            <div class="cp-tabs" id="mkt-pie-tabs">
              <button class="cp-tab active" onclick="setMktPie('mktcap',this)">Mkt Cap</button>
              <button class="cp-tab" onclick="setMktPie('price',this)">Price</button>
            </div>
          </div>
          <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <canvas id="cv-mktpie" width="160" height="160" style="flex-shrink:0"></canvas>
            <div class="pie-legend" id="mkt-pie-legend" style="flex:1;min-width:100px"></div>
          </div>
        </div>
      </div>
      <!-- Historical Line -->
      <div class="card mb22">
        <div class="cp-hd">
          <div><div class="cp-tt">📈 Historical Share Price Comparison</div><div class="cp-sub">Normalised % change — all 7 companies</div></div>
          <div class="cp-tabs" id="mkt-line-tabs">
            <button class="cp-tab active" onclick="setMktLine('1wk','1d',this)">1W</button>
            <button class="cp-tab" onclick="setMktLine('1mo','1d',this)">1M</button>
            <button class="cp-tab" onclick="setMktLine('3mo','1d',this)">3M</button>
            <button class="cp-tab" onclick="setMktLine('1y','1wk',this)">1Y</button>
            <button class="cp-tab" onclick="setMktLine('5y','1mo',this)">5Y</button>
          </div>
        </div>
        <div style="position:relative;height:300px" id="mkt-line-wrap"><canvas id="cv-mkt-line"></canvas></div>
      </div>
      <!-- Day Change % -->
      <div class="card mb22">
        <div class="cp-hd"><div><div class="cp-tt">📉 Day Change %</div><div class="cp-sub">Today's gain / loss per company</div></div></div>
        <div style="position:relative;height:240px"><canvas id="cv-daychange"></canvas></div>
      </div>
      <!-- Data Table -->
      <div class="card mb22">
        <div class="c-hd"><div class="c-tt">📋 Company Snapshot — Live Data</div><div id="mkt-updated" style="font-size:10px;color:var(--text3);font-family:var(--font-m)"></div></div>
        <div class="tw" id="mkt-table"><div class="loading"><div class="spin"></div>Loading…</div></div>
      </div>
    </div>

    <!-- ════ PORTFOLIO ════ -->
    <div class="page" id="p-portfolio">
      <div class="ph">
        <div class="df aic jsb" style="flex-wrap:wrap;gap:9px">
          <div><div class="pt">💼 My Portfolio</div><div class="ps">Track your NSE holdings &amp; performance</div></div>
          <div class="df gap8">
            <button class="btn btn-buy btn-sm" onclick="openTrade('buy')">+ Buy</button>
            <button class="btn btn-sell btn-sm" onclick="openTrade('sell')">↓ Sell</button>
          </div>
        </div>
      </div>
      <div class="g4 mb22" id="port-stats"></div>
      <div class="g2 mb22" id="port-charts-row" style="display:none">
        <div class="card">
          <div class="cp-hd">
            <div><div class="cp-tt">📈 Holdings Trend</div><div class="cp-sub">% change from start</div></div>
            <div class="cp-tabs" id="port-range-tabs">
              <button class="cp-tab active" onclick="setPortRange('1wk','1d',this)">1W</button>
              <button class="cp-tab" onclick="setPortRange('1mo','1d',this)">1M</button>
              <button class="cp-tab" onclick="setPortRange('3mo','1d',this)">3M</button>
            </div>
          </div>
          <div style="position:relative;height:200px" id="port-line-wrap"><canvas id="cv-port-line"></canvas></div>
        </div>
        <div class="card">
          <div class="cp-hd"><div><div class="cp-tt">🥧 Portfolio Allocation</div><div class="cp-sub">Cash vs holdings</div></div></div>
          <div style="display:flex;gap:12px;align-items:center">
            <canvas id="cv-port-pie" width="150" height="150" style="flex-shrink:0"></canvas>
            <div class="pie-legend" id="port-pie-legend" style="flex:1"></div>
          </div>
        </div>
      </div>
      <div class="card mb22">
        <div class="c-hd"><div class="c-tt">💼 Holdings</div><div class="ts tm" id="h-cnt"></div></div>
        <div class="tw" id="h-cont"><div class="empty"><div class="ei">📭</div><div class="et">No holdings yet</div><div class="ed">Buy NSE shares to build your portfolio</div></div></div>
      </div>
      <div class="card">
        <div class="c-hd"><div class="c-tt">📋 Transactions</div><button class="btn btn-out btn-sm" onclick="clearTxns()">Clear</button></div>
        <div class="tw" id="txn-cont"><div class="empty"><div class="ei">📝</div><div class="et">No transactions</div><div class="ed">Buy &amp; sell history appears here</div></div></div>
      </div>
    </div>

    <!-- ════ WATCHLIST ════ -->
    <div class="page" id="p-watchlist">
      <div class="ph"><div class="pt">👁 Watchlist</div><div class="ps">Monitor your favourite NSE stocks in real-time</div></div>
      <div class="card mb22" style="padding:14px">
        <div class="df gap8" style="flex-wrap:wrap">
          <div class="srch-w" style="flex:1;min-width:180px;background:var(--bg1)">
            <span style="color:var(--text3)">+</span>
            <input type="text" id="wl-inp" placeholder="Add NSE symbol (e.g. WIPRO)" oninput="this.value=this.value.toUpperCase()" onkeydown="if(event.key==='Enter')addWL()">
          </div>
          <button class="btn btn-p" onclick="addWL()">Add to Watchlist</button>
        </div>
      </div>
      <div class="wl-g" id="wl-cont"><div class="loading"><div class="spin"></div>Loading…</div></div>
    </div>

    <!-- ════ AI ANALYST ════ -->
    <div class="page" id="p-analyst">
      <div class="ph">
        <div class="df aic jsb" style="flex-wrap:wrap;gap:9px">
          <div><div class="pt">🤖 AI Analyst</div><div class="ps">AI-powered NSE market insights &amp; investment strategies</div></div>
          <button class="btn btn-out btn-sm" onclick="clearChat()">🗑 Clear</button>
        </div>
      </div>
      <div class="chips" id="chips">
        <div class="chip" onclick="suggest('Analyse my NSE portfolio and give recommendations')">📊 Analyse Portfolio</div>
        <div class="chip" onclick="suggest('Compare Reliance vs TCS for long-term investment')">⚡ Reliance vs TCS</div>
        <div class="chip" onclick="suggest('Is HDFC Bank a good buy at current levels?')">🏦 HDFC Bank</div>
        <div class="chip" onclick="suggest('What are the best NSE large-cap stocks to buy now?')">🚀 Large-Cap Picks</div>
        <div class="chip" onclick="suggest('Explain SIP investment strategy for NSE stocks')">💡 SIP Strategy</div>
        <div class="chip" onclick="suggest('What is the outlook for Adani group stocks?')">⚡ Adani Outlook</div>
      </div>
      <div class="chat-c">
        <div class="chat-m" id="chat-msgs">
          <div class="msg"><div class="m-av ai">AI</div>
            <div class="m-cnt">
              <p>🇮🇳 Namaste! I'm your NSE AI Analyst. I can help with:</p>
              <p>• <strong>Stock Analysis</strong> — Reliance, TCS, HDFC, Infosys, SBI, Adani, Wipro</p>
              <p>• <strong>Portfolio Strategy</strong> — SIP, diversification, risk management</p>
              <p>• <strong>Market Insights</strong> — NSE/BSE trends, SEBI regulations</p>
              <p>• <strong>Valuation</strong> — P/E, market cap, price targets in ₹</p>
              <p>Ask me anything to get started!</p>
            </div>
          </div>
        </div>
        <div class="chat-in-w">
          <textarea class="chat-in" id="chat-inp" placeholder="Ask about NSE stocks, strategies, market trends…" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg()}" rows="1"></textarea>
          <button class="send-btn" onclick="sendMsg()" id="send-btn" aria-label="Send message">➤</button>
        </div>
      </div>
    </div>

    <!-- ════ SUGGESTIONS ════ -->
    <div class="page" id="p-suggestions">
      <div class="ph"><div class="pt">💡 Smart Suggestions</div><div class="ps">AI-powered themes, portfolio tips &amp; NSE market ideas</div></div>
      <div class="gen-btn-wrap">
        <div style="font-size:36px">✨</div>
        <div class="info"><div class="title">AI-Powered Suggestions</div><div class="sub">Analyses your portfolio &amp; generates personalised NSE trade ideas</div></div>
        <button class="btn btn-p" id="gen-sug-btn" onclick="generateAISugs()">✨ Generate Suggestions</button>
      </div>
      <div class="sug-section">
        <div class="sug-sec-hd"><div class="sug-sec-t">🎨 UI &amp; Design Themes</div><div class="sug-sec-s">Applied instantly — no reload needed</div></div>
        <div class="sug-grid" id="ui-sugs"></div>
      </div>
      <div class="sug-section">
        <div class="sug-sec-hd"><div class="sug-sec-t">⚖️ Portfolio Rebalancing</div><div class="sug-sec-s" id="port-sug-sub">Click Generate above for AI analysis</div></div>
        <div id="port-sugs"><div class="empty"><div class="ei">⚖️</div><div class="et">No suggestions yet</div><div class="ed">Generate AI suggestions for personalised portfolio tips</div></div></div>
      </div>
      <div class="sug-section">
        <div class="sug-sec-hd"><div class="sug-sec-t">📈 NSE Market Opportunities</div><div class="sug-sec-s" id="mkt-sug-sub">Click Generate above for market ideas</div></div>
        <div id="mkt-sugs"><div class="empty"><div class="ei">📈</div><div class="et">No opportunities yet</div><div class="ed">Generate AI suggestions to discover trending NSE stocks</div></div></div>
      </div>
    </div><!-- /.page#p-suggestions -->
  </div><!-- /.main -->
</div><!-- /.app -->

<script>
// ══════════════════════════════════════════════════
// STOCK TRACKING — NSE INDIA
// 7 Companies · ₹ INR · Live Data
// ══════════════════════════════════════════════════
const NSE7=[
  {sym:'RELIANCE.NS',label:'RELIANCE',name:'Reliance Industries',  icon:'⚡',color:'#00d4aa',sector:'Conglomerate',fb:{price:2920,prev:2905,mktcap:19.8e12,pe:28.4,h52:3218,l52:2220,pb:2.1}},
  {sym:'TCS.NS',     label:'TCS',     name:'Tata Consultancy Svcs',icon:'💻',color:'#3d8ef5',sector:'IT Services',  fb:{price:3940,prev:3910,mktcap:14.3e12,pe:30.2,h52:4592,l52:3311,pb:14.2}},
  {sym:'HDFCBANK.NS',label:'HDFC',    name:'HDFC Bank Ltd',        icon:'🏦',color:'#ffc107',sector:'Banking',     fb:{price:1645,prev:1632,mktcap:12.5e12,pe:19.6,h52:1796,l52:1363,pb:2.8}},
  {sym:'INFY.NS',    label:'INFOSYS', name:'Infosys Limited',      icon:'🖥',color:'#a855f7',sector:'IT Services',  fb:{price:1785,prev:1770,mktcap:7.42e12,pe:24.1,h52:1975,l52:1390,pb:7.4}},
  {sym:'SBIN.NS',    label:'SBI',     name:'State Bank of India',  icon:'🏛',color:'#00e396',sector:'Banking (PSU)',fb:{price:812, prev:805, mktcap:7.25e12,pe:10.2,h52:912, l52:600, pb:1.7}},
  {sym:'ADANIPORTS.NS',label:'ADANI', name:'Adani Ports & SEZ',    icon:'🚢',color:'#ff4560',sector:'Infrastructure',fb:{price:1285,prev:1260,mktcap:2.77e12,pe:32.8,h52:1621,l52:900, pb:4.1}},
  {sym:'WIPRO.NS',   label:'WIPRO',   name:'Wipro Limited',        icon:'🔧',color:'#f97316',sector:'IT Services',  fb:{price:542, prev:538, mktcap:2.85e12,pe:22.3,h52:620, l52:428, pb:4.3}},
];

// ── Formatting helpers ──

// Format a number as Indian Rupees  e.g.  ₹2,920.00
const INR = (n, decimals = 2) => {
  if (n == null || isNaN(n)) return '—';
  return '₹' + Number(n).toLocaleString('en-IN', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  });
};

// Compact large INR values — Crore / Lakh Crore
const INRL = n => {
  if (!n || isNaN(n)) return '—';
  const v = +n;
  if (v >= 1e12) return '₹' + (v / 1e12).toFixed(2) + ' L.Cr';
  if (v >= 1e9)  return '₹' + (v / 1e9).toFixed(2)  + ' Cr';
  return INR(v);
};

// Plain number formatter (no currency symbol)
const f = (n, decimals = 2) =>
  Number(n).toLocaleString('en-IN', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  });

// CSS class for up/down/neutral colour
const cc = v => v > 0 ? 'up' : v < 0 ? 'down' : 'neutral';

// Leading + sign for positive numbers
const cs = v => v > 0 ? '+' : '';

// Format a Unix timestamp as "Jan 5"
const fd = ts => new Date(ts * 1000).toLocaleDateString('en-IN', { month: 'short', day: 'numeric' });

// Format a date-string as "Jan 5, 03:45 PM"
const fdt = s => new Date(s).toLocaleString('en-IN', {
  month: 'short', day: 'numeric',
  hour: '2-digit', minute: '2-digit'
});

// Shorthand for getElementById
const el = id => document.getElementById(id);

// ── App State ──
// We prefer PHP-injected data (loaded from MySQL on page load)
// and fall back to localStorage if the user isn't logged in.

const S = {
  port: window.__ST_PORT
    ? {
        cash: window.__ST_PORT.cash,
        hold: window.__ST_PORT.hold || {},
        txns: window.__ST_PORT.txns || []
      }
    : JSON.parse(localStorage.getItem('st_port') || JSON.stringify({ cash: 1000000, hold: {}, txns: [] })),

  wl: window.__ST_WL
    ? window.__ST_WL
    : JSON.parse(localStorage.getItem('st_wl') || JSON.stringify(NSE7.map(c => c.sym))),

  cache:   {},      // price cache  { sym: { d: quoteData, ts: timestamp } }
  chat:    [],      // AI chat message history
  live:    {},      // latest live quote data by symbol
  trMode:  'buy',   // current trade modal mode
  trPrice: 0,
  trSym:   '',
  curTab:  'dashboard',
  chart:   null     // currently rendered stock detail chart instance
};

// ── Save portfolio: localStorage + DB (if logged in) ─────────
const savePf = async () => {
  localStorage.setItem('st_port', JSON.stringify(S.port));
  if (currentUser) {
    try {
      await fetch('portfolio.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({cash: S.port.cash, hold: S.port.hold, txns: S.port.txns})
      });
    } catch(e) { console.warn('Portfolio DB save failed:', e); }
  }
};

// ── Save watchlist: localStorage + DB (if logged in) ─────────
const saveWl = async () => {
  localStorage.setItem('st_wl', JSON.stringify(S.wl));
  if (currentUser) {
    try {
      await fetch('watchlist.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({symbols: S.wl})
      });
    } catch(e) { console.warn('Watchlist DB save failed:', e); }
  }
};

// ── Data Fetching ──

// Fetch a single quote from Yahoo Finance via a CORS proxy
async function fetchQ(sym) {
  sym = sym.toUpperCase().trim();
  if (!sym.includes('.')) sym += '.NS';

  // Return cached data if it's less than 5 minutes old
  const cached = S.cache[sym];
  if (cached && Date.now() - cached.ts < 300000) return cached.d;

  const fields = [
    'regularMarketPrice', 'regularMarketChange', 'regularMarketChangePercent',
    'shortName', 'regularMarketVolume', 'marketCap', 'regularMarketPreviousClose',
    'fiftyTwoWeekHigh', 'fiftyTwoWeekLow', 'regularMarketDayHigh',
    'regularMarketDayLow', 'trailingPE', 'priceToBook'
  ].join(',');

  const url = `https://query1.finance.yahoo.com/v7/finance/quote?symbols=${sym}&fields=${fields}`;

  const proxies = [
    `https://api.allorigins.win/get?url=${encodeURIComponent(url)}`,
    `https://corsproxy.io/?${encodeURIComponent(url)}`
  ];

  for (const proxy of proxies) {
    try {
      const res  = await fetch(proxy, { signal: AbortSignal.timeout(8000) });
      const json = await res.json();
      const data = json.contents ? JSON.parse(json.contents) : json;
      const quote = data?.quoteResponse?.result?.[0];
      if (quote) {
        S.cache[sym] = { d: quote, ts: Date.now() };
        return quote;
      }
    } catch (e) {
      // try next proxy
    }
  }
  return null;
}

// Fetch multiple quotes in a single request (more efficient for bulk loads)
async function fetchMany(syms) {
  if (!syms?.length) return [];

  const fields = 'regularMarketPrice,regularMarketChange,regularMarketChangePercent,shortName,regularMarketVolume,marketCap,regularMarketPreviousClose,fiftyTwoWeekHigh,fiftyTwoWeekLow,trailingPE,priceToBook';
  const url = `https://query1.finance.yahoo.com/v7/finance/quote?symbols=${syms.join(',')}&fields=${fields}`;

  const proxies = [
    `https://api.allorigins.win/get?url=${encodeURIComponent(url)}`,
    `https://corsproxy.io/?${encodeURIComponent(url)}`
  ];

  for (const proxy of proxies) {
    try {
      const res    = await fetch(proxy, { signal: AbortSignal.timeout(10000) });
      const json   = await res.json();
      const parsed = json.contents ? JSON.parse(json.contents) : json;
      const results = parsed?.quoteResponse?.result || [];
      results.forEach(q => {
        if (q.symbol) S.cache[q.symbol] = { d: q, ts: Date.now() };
      });
      return results;
    } catch (e) {
      // try next proxy
    }
  }
  return [];
}

// Fetch historical price chart data for a given symbol + range
async function fetchChart(sym, range = '1mo', interval = '1d') {
  if (!sym.includes('.')) sym += '.NS';

  const url = `https://query1.finance.yahoo.com/v8/finance/chart/${sym}?range=${range}&interval=${interval}&includePrePost=false`;

  const proxies = [
    `https://api.allorigins.win/get?url=${encodeURIComponent(url)}`,
    `https://corsproxy.io/?${encodeURIComponent(url)}`
  ];

  for (const proxy of proxies) {
    try {
      const res    = await fetch(proxy, { signal: AbortSignal.timeout(10000) });
      const json   = await res.json();
      const parsed = json.contents ? JSON.parse(json.contents) : json;
      return parsed?.chart?.result?.[0] || null;
    } catch (e) {
      // try next proxy
    }
  }
  return null;
}

// Load all 7 NSE companies, using fallback data if live fetch fails
async function loadNSE7() {
  const quotes = await fetchMany(NSE7.map(c => c.sym));
  S.live = {};
  quotes.forEach(q => { S.live[q.symbol] = q; });

  // Fill in fallback data for any symbols that failed to load
  NSE7.forEach(c => {
    if (!S.live[c.sym]) {
      S.live[c.sym] = {
        symbol: c.sym,
        shortName: c.name,
        regularMarketPrice: c.fb.price,
        regularMarketPreviousClose: c.fb.prev,
        marketCap: c.fb.mktcap,
        regularMarketChangePercent: (c.fb.price - c.fb.prev) / c.fb.prev * 100,
        regularMarketChange: c.fb.price - c.fb.prev,
        fiftyTwoWeekHigh: c.fb.h52,
        fiftyTwoWeekLow: c.fb.l52,
        trailingPE: c.fb.pe,
        priceToBook: c.fb.pb,
        _fb: true   // flag to indicate this is fallback data
      };
    }
  });
}

// ── Tab Navigation — sidebar only ──
function tab(t) {
  S.curTab = t;
  ['dashboard', 'markets', 'portfolio', 'watchlist', 'analyst', 'suggestions'].forEach(x => {
    el('n-' + x)?.classList.toggle('active', x === t);
    el('p-' + x)?.classList.toggle('active', x === t);
  });
  el('main').scrollTop = 0;

  if (t === 'dashboard')   loadDash();
  if (t === 'markets')     loadMarketsPage();
  if (t === 'portfolio')   loadPort();
  if (t === 'watchlist')   loadWL();
  if (t === 'analyst')     loadAI();
  if (t === 'suggestions') loadSuggestions();
}

// ── Portfolio Value Calculation ──
async function pvCalc() {
  const syms = Object.keys(S.port.hold);
  if (!syms.length) return { total: S.port.cash, sv: 0, pnl: 0, pp: 0, qmap: {} };

  const quotes = await fetchMany(syms);
  const qmap = {};
  quotes.forEach(q => { qmap[q.symbol] = q; });

  let stockValue = 0;
  let costBasis  = 0;

  for (const [sym, holding] of Object.entries(S.port.hold)) {
    const price = qmap[sym]?.regularMarketPrice || holding.avg;
    stockValue += holding.sh * price;
    costBasis  += holding.sh * holding.avg;
  }

  const total  = S.port.cash + stockValue;
  const pnl    = stockValue - costBasis;
  const pnlPct = costBasis ? (pnl / costBasis) * 100 : 0;

  return { total, sv: stockValue, cb: costBasis, pnl, pp: pnlPct, qmap };
}

// Update the stat cards and header portfolio value display
async function updStats() {
  try {
    const { total, pnl, pp } = await pvCalc();
    const positions = Object.keys(S.port.hold).length;

    el('s-pv').textContent   = INRL(total);
    el('s-cash').textContent = INRL(S.port.cash);
    el('s-pos').textContent  = positions;

    const pnlEl  = el('s-pnl');
    const pnlPEl = el('s-pnlp');
    pnlEl.textContent  = (pnl >= 0 ? '+' : '') + INRL(Math.abs(pnl));
    pnlEl.className    = 's-val ' + cc(pnl);
    pnlPEl.textContent = cs(pp) + f(pp) + '%';
    pnlPEl.className   = 's-chg ' + cc(pp);

    el('top-pv').textContent = INRL(total);
  } catch (e) {}
}

// ── Market Status (NSE open 9:15–15:30 IST, Mon–Fri) ──
function mktStatus() {
  const ist  = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Kolkata' }));
  const day  = ist.getDay();
  const time = ist.getHours() + ist.getMinutes() / 60;
  const isOpen = day >= 1 && day <= 5 && time >= 9.25 && time < 15.5;

  const dot = el('mkt-dot');
  const txt = el('mkt-txt');
  if (dot) {
    dot.style.background  = isOpen ? 'var(--green)' : 'var(--gold)';
    dot.style.boxShadow   = isOpen ? '0 0 6px var(--green)' : '0 0 6px var(--gold)';
  }
  if (txt) txt.textContent = isOpen ? 'NSE Open' : 'NSE Closed';
}

// ── Scrolling Ticker Bar ──
function updTicker(quotes) {
  if (!quotes?.length) return;
  // Duplicate the items so the scroll animation loops seamlessly
  const items = [...quotes, ...quotes].map(q => {
    const chg = q.regularMarketChangePercent || 0;
    return `<span class="ticker-i">
      <span class="t-sym">${(q.symbol || '').replace('.NS', '')}</span>
      <span class="t-price">${INR(q.regularMarketPrice)}</span>
      <span class="${cc(chg)}" style="font-size:10px">${cs(chg)}${f(chg)}%</span>
    </span>`;
  }).join('');
  el('ticker').innerHTML = items;
}

// ══ COMPANY CARDS (reused on Dashboard + Markets pages) ══
function renderCoCards(targetId) {
  const wrap = el(targetId);
  if (!wrap) return;

  wrap.innerHTML = NSE7.map(c => {
    const quote    = S.live[c.sym];
    const price    = quote?.regularMarketPrice   || c.fb.price;
    const chgPct   = quote?.regularMarketChangePercent || 0;
    const isUp     = chgPct >= 0;
    const isFallback = quote?._fb;

    return `<div class="co-card" onclick="viewStock('${c.sym}')" style="border-bottom:3px solid ${c.color}30">
      <div class="co-av" style="background:${c.color}20;color:${c.color}">${c.icon}</div>
      <div class="co-sym" style="color:${c.color}">${c.label}</div>
      <div class="co-nm">${c.name}</div>
      <div class="co-price">${INR(price)}</div>
      <div class="co-chg ${isUp ? 'up' : 'down'}">
        ${isUp ? '▲' : '▼'} ${Math.abs(chgPct).toFixed(2)}%
        ${isFallback ? '<span style="font-size:9px;color:var(--text3);margin-left:3px">(est)</span>' : ''}
      </div>
    </div>`;
  }).join('');
}

// ══ DASHBOARD ══
let dashLineChart = null;
let dashPieChart  = null;
let dashRange     = '1wk';
let dashInt       = '1d';

async function loadDash() {
  updStats();
  await loadNSE7();
  renderCoCards('dash-co-cards');
  updTicker(NSE7.map(c => S.live[c.sym]));
  await loadDashLine();
  renderDashPie();
}

async function loadDashLine() {
  const wrap = el('dash-line-wrap');
  if (!wrap) return;

  if (dashLineChart) { dashLineChart.destroy(); dashLineChart = null; }
  wrap.innerHTML = '<div class="loading"><div class="spin"></div>Loading chart…</div>';

  const results = await Promise.all(NSE7.map(c => fetchChart(c.sym, dashRange, dashInt)));
  wrap.innerHTML = '<canvas id="cv-dash-line"></canvas>';

  const cv = el('cv-dash-line');
  if (!cv) return;

  const datasets = [];
  results.forEach((chartData, i) => {
    if (!chartData) return;
    const closes = (chartData.indicators?.quote?.[0]?.close || []).filter(v => v != null);
    if (!closes.length) return;

    const base = closes[0];
    datasets.push({
      label:           NSE7[i].label,
      data:            closes.map(v => +((v / base - 1) * 100).toFixed(2)),
      borderColor:     NSE7[i].color,
      backgroundColor: 'transparent',
      tension:         0.35,
      pointRadius:     0,
      pointHoverRadius: 5,
      borderWidth:     2.5,
      _ts: (chartData.timestamp || []).slice(0, closes.length).map(t => fd(t))
    });
  });

  if (!datasets.length) {
    wrap.innerHTML = '<div style="padding:20px;color:var(--text2);text-align:center">No chart data available</div>';
    return;
  }

  dashLineChart = new Chart(cv, {
    type: 'line',
    data: { labels: datasets[0]._ts, datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          display: true,
          position: 'top',
          labels: { color: '#7090b8', font: { family: "'DM Sans'", size: 11 }, boxWidth: 12, padding: 8 }
        },
        tooltip: {
          backgroundColor: 'rgba(5,10,20,.93)',
          borderColor: 'rgba(255,255,255,.07)',
          borderWidth: 1,
          titleColor: '#dce8ff',
          bodyColor: '#7090b8',
          callbacks: {
            label: c => ' ' + c.dataset.label + ': ' + (c.parsed.y >= 0 ? '+' : '') + c.parsed.y + '%'
          }
        }
      },
      scales: {
        x: {
          grid: { color: 'rgba(255,255,255,.03)' },
          ticks: { color: '#3d5a80', font: { family: "'Space Mono'", size: 9 }, maxTicksLimit: 7 }
        },
        y: {
          grid: { color: 'rgba(255,255,255,.04)' },
          ticks: {
            color: '#3d5a80',
            font: { family: "'Space Mono'", size: 9 },
            callback: v => (v >= 0 ? '+' : '') + v + '%'
          }
        }
      }
    }
  });
}

function setDashRange(range, interval, btn) {
  dashRange = range;
  dashInt   = interval;
  document.querySelectorAll('#dash-range-tabs .cp-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  loadDashLine();
}

function renderDashPie() {
  const cv = el('cv-dash-pie');
  if (!cv) return;
  if (dashPieChart) { dashPieChart.destroy(); dashPieChart = null; }

  const labels = [], vals = [], colors = [];
  NSE7.forEach(c => {
    const q = S.live[c.sym];
    labels.push(c.label);
    vals.push(q?.marketCap || c.fb.mktcap);
    colors.push(c.color);
  });

  const total = vals.reduce((a, b) => a + b, 0);

  // Build legend
  const leg = el('dash-pie-legend');
  if (leg) {
    leg.innerHTML = NSE7.map((c, i) =>
      `<div class="pl-item">
        <div class="pl-dot" style="background:${colors[i]}"></div>
        <div class="pl-name">${c.label}</div>
        <div class="pl-val" style="color:${colors[i]}">${((vals[i] / total) * 100).toFixed(1)}%</div>
      </div>`
    ).join('');
  }

  dashPieChart = new Chart(cv, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data:            vals,
        backgroundColor: colors.map(c => c + 'cc'),
        borderColor:     colors,
        borderWidth:     2,
        hoverOffset:     7
      }]
    },
    options: {
      responsive: false,
      cutout: '60%',
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(5,10,20,.93)',
          borderColor: 'rgba(255,255,255,.07)',
          borderWidth: 1,
          callbacks: {
            label: c => ' ' + c.label + ': ' + ((c.parsed / total) * 100).toFixed(1) + '%  (' + INRL(vals[c.dataIndex]) + ')'
          }
        }
      }
    }
  });
}

// ══ MARKETS PAGE ══
let mktLineChart=null,mktPvcChart=null,mktCapChart=null,mktPieChart=null,mktDayChart=null;
let mktLineRange='1wk',mktLineInt='1d',mktPieMode='mktcap',mktCapUnit='LC';

async function loadMarketsPage() {
  const btn = el('mkt-refresh-btn');
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Loading…'; }

  await loadNSE7();
  renderCoCards('mkt-co-cards');
  renderPvcChart();
  renderMktCapChart();
  renderMktPieChart();
  await loadMktLineChart();
  renderDayChangeChart();
  renderMktTable();

  const lastUpdated = el('mkt-updated');
  if (lastUpdated) lastUpdated.textContent = 'Updated ' + new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
  if (btn) { btn.disabled = false; btn.textContent = '↻ Refresh'; }
}

function renderPvcChart() {
  const cv = el('cv-pvc');
  if (!cv) return;
  if (mktPvcChart) { mktPvcChart.destroy(); mktPvcChart = null; }

  const labels = [], prev = [], curr = [], colors = [];
  NSE7.forEach(c => {
    const q = S.live[c.sym];
    labels.push(c.label);
    prev.push(q?.regularMarketPreviousClose || c.fb.prev);
    curr.push(q?.regularMarketPrice         || c.fb.price);
    colors.push(c.color);
  });

  mktPvcChart = new Chart(cv, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        { label: 'Previous Close', data: prev, backgroundColor: 'rgba(61,90,128,.6)', borderColor: '#3d5a80', borderWidth: 2, borderRadius: 5, borderSkipped: false },
        { label: 'Current Price',  data: curr, backgroundColor: colors.map(c => c + '99'), borderColor: colors, borderWidth: 2, borderRadius: 5, borderSkipped: false }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(5,10,20,.93)', borderColor: 'rgba(255,255,255,.07)', borderWidth: 1,
          titleColor: '#dce8ff', bodyColor: '#7090b8',
          callbacks: {
            title: c => `${c[0].label} — ${NSE7[c[0].dataIndex]?.name || ''}`,
            label: c => {
              const diff    = curr[c.dataIndex] - prev[c.dataIndex];
              const pct     = prev[c.dataIndex] ? (diff / prev[c.dataIndex] * 100).toFixed(2) : '0';
              const extra   = c.datasetIndex === 1 ? `  (${diff >= 0 ? '+' : ''}${pct}%)` : '';
              return ` ${c.dataset.label}: ${INR(c.parsed.y)}${extra}`;
            }
          }
        }
      },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,.03)' }, ticks: { color: '#7090b8', font: { family: "'Space Mono'", size: 10 }, maxRotation: 0 } },
        y: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { color: '#3d5a80', font: { family: "'Space Mono'", size: 9 }, callback: v => '₹' + v.toLocaleString('en-IN') } }
      }
    }
  });
}

function renderMktCapChart() {
  const cv = el('cv-mktcap');
  if (!cv) return;
  if (mktCapChart) { mktCapChart.destroy(); mktCapChart = null; }

  const div     = mktCapUnit === 'LC' ? 1e12 : 1e7;
  const unitLbl = mktCapUnit === 'LC' ? 'L.Cr' : 'Cr';

  const rows = NSE7.map(c => {
    const q = S.live[c.sym];
    return { label: c.label, v: +((q?.marketCap || c.fb.mktcap) / div).toFixed(2), color: c.color, name: c.name };
  }).sort((a, b) => b.v - a.v);

  mktCapChart = new Chart(cv, {
    type: 'bar',
    data: {
      labels: rows.map(r => r.label),
      datasets: [{ label: `Mkt Cap (₹ ${unitLbl})`, data: rows.map(r => r.v), backgroundColor: rows.map(r => r.color + 'aa'), borderColor: rows.map(r => r.color), borderWidth: 2, borderRadius: 8, borderSkipped: false }]
    },
    options: {
      indexAxis: 'y', responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(5,10,20,.93)', borderColor: 'rgba(255,255,255,.07)', borderWidth: 1,
          callbacks: { title: c => rows[c[0].dataIndex].name, label: c => ` ₹${c.parsed.x.toFixed(2)} ${unitLbl}` }
        }
      },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { color: '#3d5a80', font: { family: "'Space Mono'", size: 9 }, callback: v => `₹${v}` } },
        y: { grid: { display: false }, ticks: { color: '#7090b8', font: { family: "'Space Mono'", size: 10 } } }
      }
    }
  });
}

function setMktUnit(unit, btn) {
  mktCapUnit = unit;
  document.querySelectorAll('#mktcap-tabs .cp-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  renderMktCapChart();
}

function renderMktPieChart() {
  const cv = el('cv-mktpie');
  if (!cv) return;
  if (mktPieChart) { mktPieChart.destroy(); mktPieChart = null; }

  const labels = [], vals = [], colors = [];
  NSE7.forEach(c => {
    const q = S.live[c.sym];
    labels.push(c.label);
    vals.push(mktPieMode === 'mktcap' ? q?.marketCap || c.fb.mktcap : q?.regularMarketPrice || c.fb.price);
    colors.push(c.color);
  });

  const total = vals.reduce((a, b) => a + b, 0);

  const leg = el('mkt-pie-legend');
  if (leg) {
    leg.innerHTML = NSE7.map((c, i) => {
      const cap = S.live[c.sym]?.marketCap || c.fb.mktcap;
      const pct = ((vals[i] / total) * 100).toFixed(1);
      return `<div class="pl-item">
        <div class="pl-dot" style="background:${colors[i]}"></div>
        <div style="flex:1">
          <div class="pl-name" style="font-weight:600;color:var(--text);font-size:11.5px">${c.label}</div>
          <div style="font-size:10px;color:var(--text3)">${INRL(cap)}</div>
        </div>
        <div class="pl-val" style="color:${colors[i]}">${pct}%</div>
      </div>`;
    }).join('');
  }

  mktPieChart = new Chart(cv, {
    type: 'doughnut',
    data: { labels, datasets: [{ data: vals, backgroundColor: colors.map(c => c + 'cc'), borderColor: colors, borderWidth: 2, hoverOffset: 8 }] },
    options: {
      responsive: false, cutout: '58%',
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(5,10,20,.93)', borderColor: 'rgba(255,255,255,.07)', borderWidth: 1,
          callbacks: { label: c => ' ' + c.label + ': ' + ((c.parsed / total) * 100).toFixed(1) + '%' }
        }
      }
    }
  });
}

function setMktPie(mode, btn) {
  mktPieMode = mode;
  document.querySelectorAll('#mkt-pie-tabs .cp-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  renderMktPieChart();
}

async function loadMktLineChart() {
  const wrap = el('mkt-line-wrap');
  if (!wrap) return;
  if (mktLineChart) { mktLineChart.destroy(); mktLineChart = null; }
  wrap.innerHTML = '<div class="loading"><div class="spin"></div>Loading…</div>';

  const results = await Promise.all(NSE7.map(c => fetchChart(c.sym, mktLineRange, mktLineInt)));
  wrap.innerHTML = '<canvas id="cv-mkt-line"></canvas>';
  const cv = el('cv-mkt-line');
  if (!cv) return;

  const datasets = [];
  results.forEach((cd, i) => {
    if (!cd) return;
    const closes = (cd.indicators?.quote?.[0]?.close || []).filter(v => v != null);
    if (!closes.length) return;
    const base = closes[0];
    datasets.push({
      label: NSE7[i].label,
      data: closes.map(v => +((v / base - 1) * 100).toFixed(2)),
      borderColor: NSE7[i].color, backgroundColor: 'transparent',
      tension: 0.35, pointRadius: 0, pointHoverRadius: 5, borderWidth: 2.5,
      _ts: (cd.timestamp || []).slice(0, closes.length).map(t => fd(t))
    });
  });

  if (!datasets.length) { wrap.innerHTML = '<div style="padding:20px;color:var(--text2);text-align:center">No data</div>'; return; }

  mktLineChart = new Chart(cv, {
    type: 'line',
    data: { labels: datasets[0]._ts, datasets },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: true, position: 'top', labels: { color: '#7090b8', font: { family: "'DM Sans'", size: 11 }, boxWidth: 14, padding: 10 } },
        tooltip: {
          backgroundColor: 'rgba(5,10,20,.93)', borderColor: 'rgba(255,255,255,.07)', borderWidth: 1,
          titleColor: '#dce8ff', bodyColor: '#7090b8',
          callbacks: { label: c => ' ' + c.dataset.label + ': ' + (c.parsed.y >= 0 ? '+' : '') + c.parsed.y + '%' }
        }
      },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,.03)' }, ticks: { color: '#3d5a80', font: { family: "'Space Mono'", size: 9 }, maxTicksLimit: 8 } },
        y: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { color: '#3d5a80', font: { family: "'Space Mono'", size: 9 }, callback: v => (v >= 0 ? '+' : '') + v + '%' } }
      }
    }
  });
}

function setMktLine(range, interval, btn) {
  mktLineRange = range; mktLineInt = interval;
  document.querySelectorAll('#mkt-line-tabs .cp-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  loadMktLineChart();
}

function renderDayChangeChart() {
  const cv = el('cv-daychange');
  if (!cv) return;
  if (mktDayChart) { mktDayChart.destroy(); mktDayChart = null; }

  const rows = NSE7.map(c => {
    const q    = S.live[c.sym];
    const prev = q?.regularMarketPreviousClose || c.fb.prev;
    const cur  = q?.regularMarketPrice         || c.fb.price;
    return { label: c.label, v: prev ? +((cur - prev) / prev * 100).toFixed(2) : 0, color: c.color };
  }).sort((a, b) => b.v - a.v);

  mktDayChart = new Chart(cv, {
    type: 'bar',
    data: {
      labels: rows.map(r => r.label),
      datasets: [{
        data: rows.map(r => r.v),
        backgroundColor: rows.map(r => r.v >= 0 ? 'rgba(0,227,150,.65)' : 'rgba(255,69,96,.65)'),
        borderColor:     rows.map(r => r.v >= 0 ? '#00e396' : '#ff4560'),
        borderWidth: 2, borderRadius: 5, borderSkipped: false
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(5,10,20,.93)', borderColor: 'rgba(255,255,255,.07)', borderWidth: 1,
          callbacks: { label: c => ` Day Change: ${c.parsed.y >= 0 ? '+' : ''}${c.parsed.y}%` }
        }
      },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,.03)' }, ticks: { color: '#7090b8', font: { family: "'Space Mono'", size: 10 }, maxRotation: 0 } },
        y: {
          grid: { color: 'rgba(255,255,255,.04)' },
          ticks: { color: '#3d5a80', font: { family: "'Space Mono'", size: 9 }, callback: v => (v >= 0 ? '+' : '') + v + '%' },
          afterDataLimits: s => { const m = Math.max(Math.abs(s.min), Math.abs(s.max), 0.5); s.min = -m - 0.4; s.max = m + 0.4; }
        }
      }
    }
  });
}

function renderMktTable() {
  const wrap = el('mkt-table');
  if (!wrap) return;

  const medals = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣', '6️⃣', '7️⃣'];
  const sorted = [...NSE7].sort((a, b) => (S.live[b.sym]?.marketCap || b.fb.mktcap) - (S.live[a.sym]?.marketCap || a.fb.mktcap));

  wrap.innerHTML = `<table class="mkt-tbl">
    <thead>
      <tr><th>Rank</th><th>Company</th><th>Current (₹)</th><th>Prev Close (₹)</th><th>Change</th><th>Change %</th><th>Mkt Cap</th><th>P/E</th><th>52W High</th><th>52W Low</th><th>Action</th></tr>
    </thead>
    <tbody>${sorted.map((c, i) => {
      const q    = S.live[c.sym];
      const cur  = q?.regularMarketPrice        || c.fb.price;
      const prev = q?.regularMarketPreviousClose || c.fb.prev;
      const diff = cur - prev;
      const pct  = prev ? (diff / prev * 100) : 0;
      const cap  = q?.marketCap        || c.fb.mktcap;
      const pe   = q?.trailingPE       || c.fb.pe;
      const h52  = q?.fiftyTwoWeekHigh || c.fb.h52;
      const l52  = q?.fiftyTwoWeekLow  || c.fb.l52;

      return `<tr>
        <td>${medals[i]}</td>
        <td>
          <div style="display:flex;align-items:center;gap:7px">
            <span style="font-size:16px">${c.icon}</span>
            <div>
              <div style="font-weight:700;color:${c.color};font-family:var(--font-m);font-size:11.5px">${c.label}</div>
              <div style="font-size:10px;color:var(--text2)">${c.name}</div>
            </div>
          </div>
        </td>
        <td class="mn" style="font-weight:700">${INR(cur)}</td>
        <td class="mn" style="color:var(--text2)">${INR(prev)}</td>
        <td class="mn ${cc(diff)}">${diff >= 0 ? '+' : ''}${INR(Math.abs(diff))}</td>
        <td><span class="badge ${pct >= 0 ? 'b-g' : 'b-r'}">${pct >= 0 ? '+' : ''}${f(pct)}%</span></td>
        <td class="mn" style="color:var(--gold)">${INRL(cap)}</td>
        <td class="mn">${pe ? f(pe, 1) : '—'}</td>
        <td class="mn up">${INR(h52)}</td>
        <td class="mn down">${INR(l52)}</td>
        <td>
          <div style="display:flex;gap:5px">
            <button class="btn btn-buy btn-sm" onclick="openTrade('buy','${c.sym}')">Buy</button>
            <button class="btn btn-sell btn-sm" onclick="openTrade('sell','${c.sym}')">Sell</button>
          </div>
        </td>
      </tr>`;
    }).join('')}</tbody></table>`;
}

// ══ STOCK DETAIL ══
async function viewStock(sym) {
  if (!sym.includes('.')) sym += '.NS';

  // Switch to dashboard and show the detail panel
  tab('dashboard');
  const panel = el('det-panel');
  panel.style.display = 'block';
  panel.innerHTML = `<div class="sk-det">
    <div class="loading"><div class="spin"></div>Loading ${sym.replace('.NS', '')}…</div>
  </div>`;
  panel.scrollIntoView({ behavior: 'smooth', block: 'start' });

  // Fetch quote + 1-month chart data in parallel
  const [q, chartData] = await Promise.all([
    fetchQ(sym),
    fetchChart(sym, '1mo', '1d')
  ]);

  if (!q) {
    panel.innerHTML = `<div class="sk-det">
      <div style="color:var(--red);padding:16px;text-align:center">⚠️ Symbol not found.</div>
    </div>`;
    return;
  }

  const chg = q.regularMarketChangePercent || 0;
  const co  = NSE7.find(x => x.sym === sym) || { color: 'var(--teal)', icon: '📊' };

  panel.innerHTML = `<div class="sk-det" style="border-top:3px solid ${co.color}">
    <div class="sk-det-hd">
      <div>
        <div style="font-size:10px;color:var(--text2);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:4px">
          ${co.icon} ${q.shortName || sym}
        </div>
        <div style="font-family:var(--font-h);font-size:22px;font-weight:800;color:${co.color}">
          ${sym.replace('.NS', '')}
        </div>
      </div>
      <div style="text-align:right">
        <div class="sk-det-pr">${INR(q.regularMarketPrice)}</div>
        <div class="sk-det-ch ${cc(chg)}">
          ${cs(q.regularMarketChange)}${INR(Math.abs(q.regularMarketChange))}
          (${cs(chg)}${f(chg)}%)
        </div>
      </div>
    </div>

    <div class="ch-w"><canvas id="cv-det"></canvas></div>

    <div class="sk-meta">
      <div class="m-itm"><div class="m-lbl">Prev Close</div><div class="m-val">${INR(q.regularMarketPreviousClose)}</div></div>
      <div class="m-itm"><div class="m-lbl">Day Low</div><div class="m-val">${INR(q.regularMarketDayLow)}</div></div>
      <div class="m-itm"><div class="m-lbl">Day High</div><div class="m-val">${INR(q.regularMarketDayHigh)}</div></div>
      <div class="m-itm"><div class="m-lbl">52W Low</div><div class="m-val">${INR(q.fiftyTwoWeekLow)}</div></div>
      <div class="m-itm"><div class="m-lbl">52W High</div><div class="m-val">${INR(q.fiftyTwoWeekHigh)}</div></div>
      <div class="m-itm"><div class="m-lbl">Mkt Cap</div><div class="m-val">${INRL(q.marketCap)}</div></div>
      <div class="m-itm"><div class="m-lbl">P/E Ratio</div><div class="m-val">${q.trailingPE ? f(q.trailingPE, 1) : '—'}</div></div>
    </div>

    <div style="display:flex;gap:7px;margin-top:14px;flex-wrap:wrap">
      <button class="btn btn-buy  btn-sm" onclick="openTrade('buy','${sym}')">💰 Buy</button>
      <button class="btn btn-sell btn-sm" onclick="openTrade('sell','${sym}')">↓ Sell</button>
      <button class="btn btn-out  btn-sm" onclick="addWLsym('${sym}')">+ Watchlist</button>
      <button class="btn btn-out  btn-sm" onclick="aiAbout('${sym}')">🤖 AI Analysis</button>
      <button class="btn btn-out  btn-sm" style="margin-left:auto"
              onclick="el('det-panel').style.display='none'">✕</button>
    </div>
  </div>`;

  // Render the price chart if we have data
  if (chartData) {
    const closes     = (chartData.indicators?.quote?.[0]?.close || []).filter(v => v != null);
    const timestamps = (chartData.timestamp || []).slice(0, closes.length);
    const cv         = el('cv-det');
    if (!cv) return;

    // Build gradient fill based on today's price direction
    const ctx = cv.getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, 260);
    grad.addColorStop(0, chg >= 0 ? 'rgba(0,227,150,.2)' : 'rgba(255,69,96,.2)');
    grad.addColorStop(1, 'rgba(0,0,0,0)');

    if (S.chart) { S.chart.destroy(); S.chart = null; }

    S.chart = new Chart(cv, {
      type: 'line',
      data: {
        labels:   timestamps.map(t => fd(t)),
        datasets: [{
          data:            closes,
          borderColor:     chg >= 0 ? '#00e396' : '#ff4560',
          backgroundColor: grad,
          fill:            true,
          tension:         0.3,
          pointRadius:     0,
          pointHoverRadius: 4,
          borderWidth:     2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(5,10,20,.9)',
            borderColor:     'rgba(255,255,255,.1)',
            borderWidth:     1,
            callbacks: { label: c => ' ' + INR(c.parsed.y) }
          }
        },
        scales: {
          x: {
            grid:  { color: 'rgba(255,255,255,.04)' },
            ticks: { color: '#3d5a80', font: { family: "'Space Mono'", size: 10 }, maxTicksLimit: 7 }
          },
          y: {
            position: 'right',
            grid:     { color: 'rgba(255,255,255,.04)' },
            ticks: {
              color: '#3d5a80',
              font:  { family: "'Space Mono'", size: 10 },
              callback: v => '₹' + v.toLocaleString('en-IN')
            }
          }
        }
      }
    });
  }
}

// Search bar — look up any NSE symbol and show its detail panel
function doSearch() {
  const inp = el('srch-inp');
  const sym = inp.value.trim().toUpperCase();
  inp.value = '';
  if (!sym) return;
  viewStock(sym);
}

// ══ PORTFOLIO ══
let portLineChart = null;
let portPieChart  = null;
let portRange     = '1wk';
let portInt       = '1d';

async function loadPort() {
  const { total, sv, pnl, pp } = await pvCalc();
  const positions = Object.keys(S.port.hold).length;

  el('port-stats').innerHTML = `
    <div class="sc">
      <div class="s-lbl">Total Value</div>
      <div class="s-val tg">${INRL(total)}</div>
      <div class="s-chg neutral">${positions} positions</div>
    </div>
    <div class="sc">
      <div class="s-lbl">Cash</div>
      <div class="s-val">${INRL(S.port.cash)}</div>
      <div class="s-chg neutral">Available</div>
    </div>
    <div class="sc">
      <div class="s-lbl">Invested</div>
      <div class="s-val">${INRL(sv)}</div>
      <div class="s-chg neutral">Market value</div>
    </div>
    <div class="sc">
      <div class="s-lbl">Total P&amp;L</div>
      <div class="s-val ${cc(pnl)}">${pnl >= 0 ? '+' : ''}${INRL(Math.abs(pnl))}</div>
      <div class="s-chg ${cc(pp)}">${cs(pp)}${f(pp)}%</div>
    </div>`;

  await renderHoldings();
  renderTxns();

  // Show charts grid and load it
  const chartsRow = el('port-charts-row');
  if (chartsRow) { chartsRow.style.display = 'grid'; loadPortCharts(); }
}

async function renderHoldings() {
  const syms = Object.keys(S.port.hold);
  el('h-cnt').textContent = `${syms.length} position${syms.length !== 1 ? 's' : ''}`;

  if (!syms.length) {
    el('h-cont').innerHTML = `<div class="empty">
      <div class="ei">📭</div>
      <div class="et">No holdings yet</div>
      <div class="ed">Buy NSE shares to build your portfolio</div>
    </div>`;
    return;
  }

  el('h-cont').innerHTML = '<div class="loading"><div class="spin"></div>Fetching prices…</div>';
  const quotes = await fetchMany(syms);
  const qmap   = {};
  quotes.forEach(q => { qmap[q.symbol] = q; });

  el('h-cont').innerHTML = `<table>
    <thead>
      <tr>
        <th>Symbol</th><th>Shares</th>
        <th class="col-avg">Avg Cost</th>
        <th>Mkt Price</th>
        <th>Value</th><th>P&amp;L</th>
        <th class="col-return">Return</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      ${syms.map(sym => {
        const h   = S.port.hold[sym];
        const q   = qmap[sym];
        const mp  = q?.regularMarketPrice || h.avg;
        const val  = h.sh * mp;
        const cost = h.sh * h.avg;
        const pnl  = val - cost;
        const pp   = cost ? (pnl / cost) * 100 : 0;
        const co   = NSE7.find(x => x.sym === sym) || { color: 'var(--teal)', label: sym.replace('.NS', '') };

        return `<tr>
          <td>
            <div style="font-weight:700;color:${co.color};font-family:var(--font-m)">${sym.replace('.NS', '')}</div>
            <div style="font-size:10px;color:var(--text2)">${q?.shortName || ''}</div>
          </td>
          <td class="mn">${f(h.sh, 0)}</td>
          <td class="mn col-avg">${INR(h.avg)}</td>
          <td class="mn">${INR(mp)}</td>
          <td class="mn">${INR(val)}</td>
          <td class="mn ${cc(pnl)}">${pnl >= 0 ? '+' : ''}${INR(Math.abs(pnl))}</td>
          <td class="col-return"><span class="badge ${pnl >= 0 ? 'b-g' : 'b-r'}">${cs(pp)}${f(pp)}%</span></td>
          <td>
            <div style="display:flex;gap:5px">
              <button class="btn btn-buy  btn-sm" onclick="openTrade('buy','${sym}')">Buy</button>
              <button class="btn btn-sell btn-sm" onclick="openTrade('sell','${sym}')">Sell</button>
            </div>
          </td>
        </tr>`;
      }).join('')}
    </tbody>
  </table>`;
}

function renderTxns() {
  // Show most recent 60 transactions, newest first
  const txns = [...(S.port.txns || [])].reverse().slice(0, 60);

  if (!txns.length) {
    el('txn-cont').innerHTML = `<div class="empty">
      <div class="ei">📝</div><div class="et">No transactions</div>
    </div>`;
    return;
  }

  el('txn-cont').innerHTML = `<table>
    <thead>
      <tr><th>Date</th><th>Type</th><th>Symbol</th><th>Shares</th><th>Price</th><th>Total</th></tr>
    </thead>
    <tbody>
      ${txns.map(t => `<tr>
        <td style="font-size:11px;color:var(--text2)">${fdt(t.d)}</td>
        <td><span class="badge ${t.t === 'buy' ? 'b-g' : 'b-r'}">${t.t.toUpperCase()}</span></td>
        <td class="mn" style="font-weight:700">${t.sym.replace('.NS', '')}</td>
        <td class="mn">${f(t.sh, 0)}</td>
        <td class="mn">${INR(t.p)}</td>
        <td class="mn">${INR(t.tot)}</td>
      </tr>`).join('')}
    </tbody>
  </table>`;
}

function clearTxns() {
  if (!confirm('Clear all transaction history?')) return;
  S.port.txns = [];
  savePf();
  renderTxns();
  toast('Transaction history cleared', 'success');
}

async function loadPortCharts() {
  const syms      = Object.keys(S.port.hold);
  const allColors = NSE7.map(c => c.color);

  // ── Holdings Performance Line Chart ──
  const lineWrap = el('port-line-wrap');
  if (lineWrap) {
    if (portLineChart) { portLineChart.destroy(); portLineChart = null; }

    if (!syms.length) {
      lineWrap.innerHTML = `<div style="display:flex;align-items:center;justify-content:center;
        height:100%;color:var(--text2);font-size:12.5px">Buy shares to see price trends</div>`;
    } else {
      lineWrap.innerHTML = '<div class="loading"><div class="spin"></div>Loading…</div>';
      const results = await Promise.all(syms.map(s => fetchChart(s, portRange, portInt)));
      lineWrap.innerHTML = '<canvas id="cv-port-line"></canvas>';

      const cv = el('cv-port-line');
      if (cv) {
        const datasets = [];
        results.forEach((cd, i) => {
          if (!cd) return;
          const closes = (cd.indicators?.quote?.[0]?.close || []).filter(v => v != null);
          if (!closes.length) return;

          const base    = closes[0];
          const coIndex = NSE7.findIndex(x => x.sym === syms[i]);
          const color   = coIndex >= 0 ? NSE7[coIndex].color : allColors[i % allColors.length];

          datasets.push({
            label:           syms[i].replace('.NS', ''),
            data:            closes.map(v => +((v / base - 1) * 100).toFixed(2)),
            borderColor:     color,
            backgroundColor: 'transparent',
            tension:         0.35,
            pointRadius:     0,
            borderWidth:     2,
            _ts:             (cd.timestamp || []).slice(0, closes.length).map(t => fd(t))
          });
        });

        if (datasets.length) {
          portLineChart = new Chart(cv, {
            type: 'line',
            data: { labels: datasets[0]._ts || [], datasets },
            options: {
              responsive:          true,
              maintainAspectRatio: false,
              plugins: {
                legend: { display: true, position: 'top', labels: { color: '#7090b8', font: { size: 10 }, boxWidth: 10, padding: 6 } }
              },
              scales: {
                x: { ticks: { color: '#3d5a80', font: { size: 9 }, maxTicksLimit: 6 }, grid: { color: 'rgba(255,255,255,.03)' } },
                y: { ticks: { color: '#3d5a80', font: { size: 9 }, callback: v => (v >= 0 ? '+' : '') + v + '%' }, grid: { color: 'rgba(255,255,255,.04)' } }
              }
            }
          });
        }
      }
    }
  }

  // ── Portfolio Allocation Pie Chart ──
  const pieCv = el('cv-port-pie');
  if (pieCv) {
    if (portPieChart) { portPieChart.destroy(); portPieChart = null; }

    const quotes = await fetchMany(syms);
    const qmap   = {};
    quotes.forEach(q => { qmap[q.symbol] = q; });

    const holdingValues = syms.map(s => S.port.hold[s].sh * (qmap[s]?.regularMarketPrice || S.port.hold[s].avg));
    const labels = ['Cash', ...syms.map(s => s.replace('.NS', ''))];
    const vals   = [S.port.cash, ...holdingValues];
    const colors = ['#3d5a80', ...syms.map(s => {
      const co = NSE7.find(x => x.sym === s);
      return co ? co.color : allColors[0];
    })];

    const total = vals.reduce((a, b) => a + b, 0);

    const leg = el('port-pie-legend');
    if (leg) {
      leg.innerHTML = labels.map((label, i) =>
        `<div class="pl-item">
          <div class="pl-dot" style="background:${colors[i]}"></div>
          <div class="pl-name">${label}</div>
          <div class="pl-val">${total > 0 ? ((vals[i] / total) * 100).toFixed(1) : 0}%</div>
        </div>`
      ).join('');
    }

    portPieChart = new Chart(pieCv, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data:            vals,
          backgroundColor: colors.map(c => c + 'cc'),
          borderColor:     colors,
          borderWidth:     2,
          hoverOffset:     6
        }]
      },
      options: {
        responsive:          false,
        cutout:              '60%',
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(5,10,20,.9)',
            callbacks: {
              label: c => ` ${c.label}: ${((c.parsed / total) * 100).toFixed(1)}%`
            }
          }
        }
      }
    });
  }
}

function setPortRange(range, interval, btn) {
  portRange = range;
  portInt   = interval;
  document.querySelectorAll('#port-range-tabs .cp-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  loadPortCharts();
}

// ══ TRADE ══
let _trTimer = null;

function openTrade(mode, sym = '') {
  S.trMode  = mode;
  S.trPrice = 0;
  S.trSym   = sym;

  el('tr-title').textContent    = mode === 'buy' ? '💰 Buy Shares' : '↓ Sell Shares';
  el('tb-buy').className        = 'tr-t buy'  + (mode === 'buy'  ? ' active' : '');
  el('tb-sell').className       = 'tr-t sell' + (mode === 'sell' ? ' active' : '');
  el('tr-conf-btn').textContent = mode === 'buy' ? 'Confirm Buy' : 'Confirm Sell';
  el('tr-conf-btn').className   = mode === 'buy' ? 'btn btn-buy' : 'btn btn-sell';

  el('tr-sym').value  = sym ? sym.replace('.NS', '') : '';
  el('tr-qty').value  = '';
  el('tr-cash').textContent = INRL(S.port.cash);
  el('tr-p').textContent    = '—';
  el('tr-tot').textContent  = '—';

  openModal('mo-trade');
  if (sym) loadTrP(sym);
}

function setTrMode(m) {
  S.trMode = m;
  el('tb-buy').className        = 'tr-t buy'  + (m === 'buy'  ? ' active' : '');
  el('tb-sell').className       = 'tr-t sell' + (m === 'sell' ? ' active' : '');
  el('tr-conf-btn').textContent = m === 'buy' ? 'Confirm Buy' : 'Confirm Sell';
  el('tr-conf-btn').className   = m === 'buy' ? 'btn btn-buy' : 'btn btn-sell';
  el('tr-title').textContent    = m === 'buy' ? '💰 Buy Shares' : '↓ Sell Shares';
}

// Debounce the symbol lookup while user is typing
function onTradeInput() {
  const sym = el('tr-sym').value.toUpperCase().trim();
  clearTimeout(_trTimer);
  _trTimer = setTimeout(() => {
    if (sym && sym !== S.trSym) { S.trSym = sym; loadTrP(sym); }
  }, 700);
}

async function loadTrP(sym) {
  if (!sym.includes('.')) sym += '.NS';
  const q = await fetchQ(sym);
  if (q) { S.trPrice = q.regularMarketPrice || 0; updTrSum(); }
}

function updTrSum() {
  const qty = parseInt(el('tr-qty').value) || 0;
  let sym   = el('tr-sym').value.toUpperCase().trim();
  if (!sym.includes('.')) sym += '.NS';
  const price = S.trPrice || S.cache[sym]?.d?.regularMarketPrice || 0;
  el('tr-p').textContent   = price ? INR(price) : '—';
  el('tr-tot').textContent = (qty && price) ? INR(qty * price) : '—';
  el('tr-cash').textContent = INRL(S.port.cash);
}

async function confirmTrade() {
  let sym = el('tr-sym').value.toUpperCase().trim();
  if (!sym.includes('.')) sym += '.NS';

  const qty = parseInt(el('tr-qty').value);
  if (!sym || !qty || qty <= 0) { toast('Enter a valid symbol and quantity', 'error'); return; }

  let price = S.trPrice || S.cache[sym]?.d?.regularMarketPrice;
  if (!price) {
    toast('Fetching price…', 'success');
    const q = await fetchQ(sym);
    if (!q) { toast('Could not get price for ' + sym, 'error'); return; }
    price     = q.regularMarketPrice;
    S.trPrice = price;
  }

  const total = price * qty;

  if (S.trMode === 'buy') {
    if (total > S.port.cash) { toast('Insufficient cash balance', 'error'); return; }
    S.port.cash -= total;
    if (!S.port.hold[sym]) S.port.hold[sym] = { sh: 0, avg: 0 };
    const h   = S.port.hold[sym];
    const newShares = h.sh + qty;
    h.avg = ((h.avg * h.sh) + (price * qty)) / newShares;
    h.sh  = newShares;
  } else {
    const h = S.port.hold[sym];
    if (!h || h.sh < qty) { toast(`Insufficient shares of ${sym.replace('.NS', '')}`, 'error'); return; }
    S.port.cash += total;
    h.sh -= qty;
    if (h.sh < 1) delete S.port.hold[sym];
  }

  S.port.txns.push({ t: S.trMode, sym, sh: qty, p: price, tot: total, d: new Date().toISOString() });
  await savePf();
  closeModal('mo-trade');
  toast(`✅ ${S.trMode === 'buy' ? 'Bought' : 'Sold'} ${qty} shares of ${sym.replace('.NS', '')} @ ${INR(price)}`, 'success');
  if (S.curTab === 'portfolio') loadPort();
  if (S.curTab === 'dashboard') updStats();
}

// ══ WATCHLIST ══
async function loadWL() {
  const ct = el('wl-cont');
  if (!S.wl.length) {
    ct.innerHTML = '<div class="empty" style="grid-column:1/-1"><div class="ei">👁</div><div class="et">Watchlist empty</div></div>';
    return;
  }

  // Show skeleton cards while loading
  ct.innerHTML = S.wl.map(() =>
    `<div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);padding:14px;pointer-events:none">
      <div class="shim" style="height:13px;width:60px;margin-bottom:7px"></div>
      <div class="shim" style="height:11px;width:100px"></div>
    </div>`
  ).join('');

  const quotes = await fetchMany(S.wl);
  const qmap   = {};
  quotes.forEach(q => { qmap[q.symbol] = q; });

  ct.innerHTML = S.wl.map(sym => {
    const q  = qmap[sym];
    const co = NSE7.find(c => c.sym === sym) || { color: 'var(--teal)', icon: '📊', label: sym.replace('.NS', ''), name: sym };

    if (!q) return `<div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--r);padding:14px;display:flex;align-items:center;justify-content:space-between">
      ${co.icon} <span style="font-family:var(--font-m);font-size:12px;font-weight:700">${co.label}</span>
      <span style="font-size:11px;color:var(--red)">Load failed</span>
      <button class="rm-btn" onclick="rmWL('${sym}')">✕</button>
    </div>`;

    const chg  = q.regularMarketChangePercent || 0;
    const isUp = chg >= 0;
    return `<div class="sk-card" style="border-top:2px solid ${co.color}30" onclick="viewStock('${sym}')">
      <div class="sk-av" style="background:${co.color}20;color:${co.color};border-color:${co.color}30">${co.icon}</div>
      <div class="sk-inf"><div class="sk-sym" style="color:${co.color}">${co.label}</div><div class="sk-nm">${q.shortName || co.name}</div></div>
      <div class="sk-pr-w"><div class="sk-pr">${INR(q.regularMarketPrice)}</div><div class="sk-ch ${isUp ? 'up' : 'down'}">${isUp ? '▲' : '▼'} ${Math.abs(chg).toFixed(2)}%</div></div>
      <button class="rm-btn" onclick="rmWL('${sym}');event.stopPropagation()">✕</button>
    </div>`;
  }).join('');
}
function addWL() {
  const inp = el('wl-inp');
  let sym = inp.value.toUpperCase().trim();
  inp.value = '';
  if (!sym) return;
  if (!sym.includes('.')) sym += '.NS';
  addWLsym(sym);
}

function addWLsym(sym) {
  if (!sym) return;
  if (S.wl.includes(sym)) { toast(`${sym.replace('.NS', '')} already in watchlist`, 'error'); return; }
  S.wl.push(sym);
  saveWl();
  toast(`Added ${sym.replace('.NS', '')} ✓`, 'success');
  if (S.curTab === 'watchlist') loadWL();
}

function rmWL(sym) {
  S.wl = S.wl.filter(s => s !== sym);
  saveWl();
  toast(`Removed ${sym.replace('.NS', '')}`, 'success');
  loadWL();
}

// ══ AI ANALYST ══
function loadAI() { /* API key lives server-side in ai.php — always ready */ }

async function sendMsg() {
  const inp = el('chat-inp');
  const msg = inp.value.trim();
  if (!msg) return;

  inp.value = '';
  inp.style.height = 'auto';
  addMsg('user', msg);

  const sendBtn = el('send-btn');
  sendBtn.disabled = true;

  // Show typing indicator
  const typingId = 'ty-' + Date.now();
  const typingEl = document.createElement('div');
  typingEl.id        = typingId;
  typingEl.className = 'msg';
  typingEl.innerHTML = `<div class="m-av ai">AI</div>
    <div class="m-cnt"><div class="typing">
      <div class="ty-d"></div><div class="ty-d"></div><div class="ty-d"></div>
    </div></div>`;
  el('chat-msgs').appendChild(typingEl);
  scrollChat();

  try {
    // Build a short portfolio context string to include with the request
    const holdingsSummary = Object.entries(S.port.hold)
      .map(([s, h]) => `${s.replace('.NS', '')}(${h.sh}sh@${INR(h.avg)})`)
      .join(', ') || 'None';
    const portCtx = `Cash: ${INRL(S.port.cash)} | Holdings: ${holdingsSummary}`;

    S.chat.push({ role: 'user', content: msg });

    // Route through PHP proxy so the Anthropic API key stays server-side
    const res  = await fetch('ai.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ mode: 'chat', messages: S.chat.slice(-20), portfolio: portCtx })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'AI request failed');

    const reply = data.text;
    S.chat.push({ role: 'assistant', content: reply });
    document.getElementById(typingId)?.remove();
    addMsg('ai', reply);
  } catch (e) {
    document.getElementById(typingId)?.remove();
    addMsg('ai', `❌ Error: ${e.message}`);
  } finally {
    sendBtn.disabled = false;
  }
}

function addMsg(role, text) {
  const container = el('chat-msgs');
  const div       = document.createElement('div');
  div.className   = `msg ${role}`;

  // Basic markdown → HTML conversion
  const formatted = text
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/g,     '<em>$1</em>')
    .replace(/\n\n/g,          '</p><p>')
    .replace(/\n/g,            '<br>');

  div.innerHTML = `<div class="m-av ${role}">${role === 'ai' ? 'AI' : '👤'}</div>
    <div class="m-cnt"><p>${formatted}</p></div>`;
  container.appendChild(div);
  scrollChat();
}

function scrollChat() {
  const c = el('chat-msgs');
  c.scrollTop = c.scrollHeight;
}

function suggest(text) {
  el('chat-inp').value = text;
  sendMsg();
}

function clearChat() {
  S.chat = [];
  el('chat-msgs').innerHTML = `<div class="msg">
    <div class="m-av ai">AI</div>
    <div class="m-cnt"><p>Chat cleared! Ask me anything about NSE stocks.</p></div>
  </div>`;
}

function aiAbout(sym) {
  tab('analyst');
  setTimeout(() => {
    el('chat-inp').value = `Analyse ${sym.replace('.NS', '')} — current share price, valuation, market cap, P/E, risks and outlook.`;
    sendMsg();
  }, 300);
}

// ══ SUGGESTIONS SYSTEM ══
// Theme definitions — only 3 clean options
const THEMES = {
  dark: {
    '--bg0': '#050a14', '--bg1': '#080f1e', '--bg2': '#0d1628',
    '--bg3': '#101c32', '--bg4': '#152238',
    '--text': '#dce8ff', '--text2': '#7090b8', '--text3': '#3d5a80',
    '--teal': '#00d4aa', '--blue': '#3d8ef5', '--green': '#00e396',
    '--red': '#ff4560', '--gold': '#ffc107',
    '--border': 'rgba(255,255,255,0.07)', '--border-b': 'rgba(61,142,245,0.2)',
    '--gl': '0 0 20px rgba(0,212,170,.15)'
  },
  white: {
    '--bg0': '#f0f4f8', '--bg1': '#ffffff', '--bg2': '#f8fafc',
    '--bg3': '#e8f0fe', '--bg4': '#d0dcee',
    '--text': '#1a2332', '--text2': '#4a6080', '--text3': '#8aa0b8',
    '--teal': '#008a72', '--blue': '#2563eb', '--green': '#16a34a',
    '--red': '#dc2626', '--gold': '#d97706',
    '--border': 'rgba(0,0,0,0.10)', '--border-b': 'rgba(37,99,235,0.3)',
    '--gl': '0 0 20px rgba(0,138,114,.12)'
  },
  blue: {
    '--bg0': '#020c1b', '--bg1': '#051120', '--bg2': '#071829',
    '--bg3': '#0a1e33', '--bg4': '#0d2640',
    '--text': '#cce0ff', '--text2': '#5580b8', '--text3': '#2a5080',
    '--teal': '#2563eb', '--blue': '#1d4ed8', '--green': '#00e396',
    '--red': '#ff4560', '--gold': '#fbbf24',
    '--border': 'rgba(37,99,235,0.15)', '--border-b': 'rgba(29,78,216,0.35)',
    '--gl': '0 0 22px rgba(37,99,235,.22)'
  }
};
const LAYOUTS={rounded:{'--r':'20px','--rs':'14px'},sharp:{'--r':'4px','--rs':'2px'},default:{'--r':'12px','--rs':'8px'}};

let S_curTheme='dark',S_curLayout='default';
const SUG_MAP={};

// Only 3 UI theme suggestions — keep it clean
const DEFAULT_UI_SUGS = [
  { id: 'u-dark',  icon: '🌙', title: 'Dark Mode',  desc: 'Deep space blues — the default look',         type: 'theme', value: 'dark'  },
  { id: 'u-white', icon: '🌞', title: 'White Mode',  desc: 'Clean bright white for daytime trading',      type: 'theme', value: 'white' },
  { id: 'u-blue',  icon: '💙', title: 'Blue Theme',  desc: 'Bold navy & electric blue accent palette',   type: 'theme', value: 'blue'  },
  { id: 'u-round', icon: '⭕', title: 'Ultra Rounded', desc: 'More rounded corners everywhere',           type: 'layout', value: 'rounded' },
  { id: 'u-sharp', icon: '◼',  title: 'Sharp Edges',  desc: 'Crisp minimal borders, no rounding',        type: 'layout', value: 'sharp'  },
];

function applyTheme(name) {
  // Support 'light' as alias for 'white' (for any saved settings backward compat)
  if (name === 'light') name = 'white';
  const t = THEMES[name];
  if (!t) return;
  const root = document.documentElement;
  Object.entries(t).forEach(([k, v]) => root.style.setProperty(k, v));
  S_curTheme = name;
  document.querySelectorAll('.sug-card[data-theme]').forEach(c => {
    c.classList.toggle('theme-active-ring', c.dataset.theme === name);
  });
}
function applyLayoutScheme(name) {
  const l = LAYOUTS[name];
  if (!l) return;
  const root = document.documentElement;
  Object.entries(l).forEach(([k, v]) => root.style.setProperty(k, v));
  S_curLayout = name;
}

function applySugById(id) {
  const sug = SUG_MAP[id];
  if (!sug) return;

  if (sug.type === 'theme') {
    applyTheme(sug.value);
    toast(`🎨 ${sug.value.charAt(0).toUpperCase() + sug.value.slice(1)} theme applied!`, 'success');

  } else if (sug.type === 'layout') {
    applyLayoutScheme(sug.value);
    toast('📐 Layout updated!', 'success');

  } else if (sug.type === 'watchlist') {
    addWLsym(sug.value);

  } else if (sug.type === 'trade') {
    const t = sug.value;
    openTrade(t.side, t.sym);
    if (t.qty) setTimeout(() => { el('tr-qty').value = t.qty; updTrSum(); }, 500);
    toast(`Trade pre-filled for ${t.sym.replace('.NS', '')} — confirm below`, 'success');
  }

  const btn = document.getElementById('sug-btn-' + id);
  if (btn) { btn.textContent = '✓ Applied'; btn.setAttribute('disabled', ''); }
}

function renderSugGrid(containerId, sugs) {
  el(containerId).innerHTML = `<div class="sug-grid">${sugs.map(s => {
    SUG_MAP[s.id] = s;
    let btnLabel = '▶ Apply';
    if (s.type === 'theme')     btnLabel = '🎨 Apply Theme';
    if (s.type === 'layout')    btnLabel = '📐 Apply Layout';
    if (s.type === 'watchlist') btnLabel = `👁 Watch ${(s.value || '').replace('.NS', '')}`;
    if (s.type === 'trade')     btnLabel = `${s.value?.side === 'buy' ? '💰 Buy' : '↓ Sell'} ${(s.value?.sym || '').replace('.NS', '')}`;

    const isActive = s.type === 'theme' && s.value === S_curTheme;
    return `<div class="sug-card" ${s.type === 'theme' ? `data-theme="${s.value}"` : ''}
      style="${isActive ? 'box-shadow:0 0 0 2px var(--teal);border-color:var(--teal)' : ''}">
      <div class="sug-ic">${s.icon}</div>
      <div class="sug-tt">${s.title}</div>
      <div class="sug-ds">${s.desc}</div>
      <button class="apply-btn" id="sug-btn-${s.id}" onclick="applySugById('${s.id}')" ${isActive ? 'disabled' : ''}>
        ${isActive ? '✓ Active' : btnLabel}
      </button>
    </div>`;
  }).join('')}</div>`;
}

function loadSuggestions() {
  if (!el('ui-sugs').children.length) renderSugGrid('ui-sugs', DEFAULT_UI_SUGS);
}

async function generateAISugs() {
  const btn = el('gen-sug-btn');
  btn.disabled    = true;
  btn.textContent = '⏳ Generating…';

  el('port-sugs').innerHTML = '<div class="loading"><div class="spin"></div>Analysing your portfolio…</div>';
  el('mkt-sugs').innerHTML  = '<div class="loading"><div class="spin"></div>Scanning NSE opportunities…</div>';
  el('port-sug-sub').textContent = 'Personalising…';
  el('mkt-sug-sub').textContent  = 'Scanning…';

  try {
    const holdingsText = Object.entries(S.port.hold)
      .map(([s, h]) => `${s.replace('.NS', '')}(${h.sh}sh@${INR(h.avg)})`)
      .join(', ') || 'None';
    const portCtx = `Cash: ${INRL(S.port.cash)} | Holdings: ${holdingsText}`;

    const resp = await fetch('ai.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ mode: 'suggest', portfolio: portCtx })
    });
    const data = await resp.json();
    if (!data.ok) throw new Error(data.error || 'AI request failed');

    const text   = data.text.trim().replace(/```json\n?/g, '').replace(/```\n?/g, '').trim();
    const parsed = JSON.parse(text);

    const portSugs = (parsed.portfolio || []).map(s => ({
      id: s.id, icon: s.icon || '💡', title: s.title, desc: s.desc,
      type: 'trade', value: { side: s.side, sym: s.sym, qty: s.qty }
    }));
    if (portSugs.length) {
      renderSugGrid('port-sugs', portSugs);
      el('port-sug-sub').textContent = `${portSugs.length} personalised suggestions`;
    } else {
      el('port-sugs').innerHTML = '<div style="padding:14px;color:var(--text2);font-size:13px">No portfolio suggestions generated.</div>';
    }

    const mktSugs = (parsed.market || []).map(s => ({
      id: s.id, icon: s.icon || '📊', title: s.title, desc: s.desc,
      type: 'watchlist', value: s.sym
    }));
    if (mktSugs.length) {
      renderSugGrid('mkt-sugs', mktSugs);
      el('mkt-sug-sub').textContent = `${mktSugs.length} NSE opportunities`;
    } else {
      el('mkt-sugs').innerHTML = '<div style="padding:14px;color:var(--text2);font-size:13px">No market suggestions generated.</div>';
    }

    toast('✨ AI suggestions ready!', 'success');
  } catch (e) {
    el('port-sugs').innerHTML = `<div style="padding:14px;color:var(--red);font-size:13px">❌ ${e.message}</div>`;
    el('mkt-sugs').innerHTML  = `<div style="padding:14px;color:var(--red);font-size:13px">❌ ${e.message}</div>`;
    toast('Failed to generate suggestions', 'error');
  } finally {
    btn.disabled    = false;
    btn.textContent = '🔄 Regenerate';
  }
}

// ══ SETTINGS ══
// Preference order: DB (via PHP) > localStorage > defaults
const _settingsDefaults = {
  theme:   'dark',
  layout:  'default',
  font:    '15',
  autoref: true,
  ticker:  true,
  compact: false,
  notif:   true,
  sound:   false
};

const APP_SETTINGS = Object.assign(
  {},
  _settingsDefaults,
  JSON.parse(localStorage.getItem('st_settings') || '{}'),
  window.__ST_SETTINGS || {}   // DB value overrides everything when logged in
);

async function saveSettings() {
  localStorage.setItem('st_settings', JSON.stringify(APP_SETTINGS));
  if (currentUser) {
    try {
      await fetch('settings.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(APP_SETTINGS)
      });
    } catch (e) { console.warn('Settings DB save failed:', e); }
  }
}

function syncSettingsUI() {
  const s  = APP_SETTINGS;
  const sv = (id, val) => {
    const el_ = el(id);
    if (!el_) return;
    el_.type === 'checkbox' ? (el_.checked = val) : (el_.value = val);
  };
  sv('set-theme',   s.theme);
  sv('set-layout',  s.layout);
  sv('set-font',    s.font);
  sv('set-autoref', s.autoref);
  sv('set-ticker',  s.ticker);
  sv('set-compact', s.compact);
  sv('set-notif',   s.notif);
  sv('set-sound',   s.sound);
}

function settingChanged(key, value) {
  APP_SETTINGS[key] = value;
  saveSettings();
  applySettingLive(key, value);
}

function applySettingLive(key, value) {
  if (key === 'theme') {
    applyTheme(value);
    toast(`🎨 ${value.charAt(0).toUpperCase() + value.slice(1)} theme applied!`, 'success');
  }
  if (key === 'layout') { applyLayoutScheme(value); toast('📐 Layout updated!', 'success'); }
  if (key === 'font')   { document.documentElement.style.fontSize = value + 'px'; }
  if (key === 'ticker') {
    const tb = document.querySelector('.ticker-b');
    if (tb) tb.style.display = value ? '' : 'none';
  }
  if (key === 'compact') {
    document.documentElement.classList.toggle('compact-mode', !!value);
  }
  if (key === 'autoref') {
    // Note: toggling autoref off only prevents the NEXT interval from being set.
    // A full page reload would be needed to stop a running interval — acceptable trade-off.
    toast(value ? '🔄 Auto-refresh enabled' : '⏸ Auto-refresh disabled', 'success');
  }
  if (key === 'sound' && value) {
    try {
      const ac  = new AudioContext();
      const osc = ac.createOscillator();
      osc.connect(ac.destination);
      osc.frequency.value = 880;
      osc.start();
      setTimeout(() => { osc.stop(); ac.close(); }, 80);
    } catch (e) {}
  }
}

function applyAllSettings() {
  const s = APP_SETTINGS;
  applyTheme(s.theme);
  applyLayoutScheme(s.layout);
  document.documentElement.style.fontSize = s.font + 'px';
  document.documentElement.classList.toggle('compact-mode', !!s.compact);
  const tb = document.querySelector('.ticker-b');
  if (tb && !s.ticker) tb.style.display = 'none';
}

async function resetPortfolio() {
  if (!confirm('Reset portfolio to ₹10,00,000 cash? This cannot be undone.')) return;
  S.port = { cash: 1000000, hold: {}, txns: [] };
  await savePf();
  updStats();
  toast('🔄 Portfolio reset to ₹10,00,000 cash', 'success');
  closeModal('mo-auth');
}

// ══ AUTH ══
// Prefer PHP session (server-side) and fall back to localStorage
let currentUser = window.__ST_USER
  ? window.__ST_USER
  : JSON.parse(localStorage.getItem('st_user') || 'null');

function openAuth(tab_ = 'login') {
  authTab(tab_);
  refreshLoginView();
  openModal('mo-auth');
}

function authTab(t) {
  ['login', 'signup', 'settings'].forEach(x => {
    el('at-' + x)?.classList.toggle('active', x === t);
    el('ap-' + x)?.classList.toggle('active', x === t);
  });
  if (t === 'settings') syncSettingsUI();
}

function refreshLoginView() {
  const loggedOut = el('lv-form');
  const loggedIn  = el('lv-logged');

  if (currentUser) {
    loggedOut.style.display = 'none';
    loggedIn.style.display  = 'block';
    el('lv-name').textContent  = currentUser.name;
    el('lv-email').textContent = currentUser.email;
  } else {
    loggedOut.style.display = 'block';
    loggedIn.style.display  = 'none';
  }
  updUserUI();
}

// Log in via PHP + MySQL
async function doLogin() {
  const email = el('l-email').value.trim();
  const pass  = el('l-pass').value;
  if (!email || !pass) { toast('Enter email and password', 'error'); return; }

  try {
    const res  = await fetch('auth.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ action: 'login', email, password: pass })
    });
    const data = await res.json();
    if (!data.ok) { toast(data.error || 'Login failed', 'error'); return; }

    currentUser = data.user;
    localStorage.setItem('st_user', JSON.stringify(data.user));

    // Sync portfolio + watchlist from DB
    if (data.portfolio) {
      S.port = { cash: data.portfolio.cash, hold: data.portfolio.hold || {}, txns: data.portfolio.txns || [] };
      localStorage.setItem('st_port', JSON.stringify(S.port));
    }
    if (data.watchlist && data.watchlist.length) {
      S.wl = data.watchlist;
      localStorage.setItem('st_wl', JSON.stringify(S.wl));
    }

    refreshLoginView();
    toast(`👋 Welcome back, ${data.user.name}!`, 'success');
    closeModal('mo-auth');
    updStats();
  } catch (err) {
    toast('Login error: ' + err.message, 'error');
  }
}

// Sign up via PHP + MySQL
async function doSignup() {
  const name  = el('s-name').value.trim();
  const email = el('s-email').value.trim();
  const pass  = el('s-pass').value;
  if (!name || !email || !pass) { toast('Fill in all fields', 'error'); return; }
  if (pass.length < 8) { toast('Password must be 8+ characters', 'error'); return; }

  try {
    const res  = await fetch('auth.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ action: 'signup', name, email, password: pass })
    });
    const data = await res.json();
    if (!data.ok) { toast(data.error || 'Signup failed', 'error'); return; }

    currentUser = data.user;
    localStorage.setItem('st_user', JSON.stringify(data.user));

    // Push current portfolio/watchlist to DB for the new account
    await savePf();
    await saveWl();

    refreshLoginView();
    toast(`🎉 Account created! Welcome, ${name}!`, 'success');
    closeModal('mo-auth');
  } catch (err) {
    toast('Signup error: ' + err.message, 'error');
  }
}

// Social OAuth (demo placeholder — hook up real OAuth in production)
function socialLogin(provider) {
  toast(`🔗 ${provider} OAuth is not configured yet. Use email/password.`, 'error');
}

// Log out via PHP session destroy
async function logOut() {
  try {
    await fetch('auth.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ action: 'logout' })
    });
  } catch (e) {}

  currentUser = null;
  localStorage.removeItem('st_user');
  refreshLoginView();
  toast('👋 Logged out', 'success');
  closeModal('mo-auth');
}

function updUserUI() {
  const isLoggedIn = !!currentUser;
  const initials   = isLoggedIn ? (currentUser.initials || currentUser.name[0].toUpperCase()) : '👤';
  const name       = isLoggedIn ? currentUser.name : '';

  // Topbar login / user area
  const topLoginBtn = el('top-login-btn');
  const topUserBar  = el('top-user-bar');
  if (topLoginBtn) topLoginBtn.style.display = isLoggedIn ? 'none' : '';
  if (topUserBar)  topUserBar.style.display  = isLoggedIn ? 'flex' : 'none';
  if (isLoggedIn) {
    const topAv   = el('top-av');
    const topName = el('top-uname');
    if (topAv)   topAv.textContent   = initials;
    if (topName) topName.textContent = name;
  }

  // Sidebar user area
  const sbLoginBtn = el('sb-login-btn');
  const sbUserBar  = el('sb-user-bar');
  if (sbLoginBtn) sbLoginBtn.style.display = isLoggedIn ? 'none' : '';
  if (sbUserBar)  sbUserBar.style.display  = isLoggedIn ? 'flex' : 'none';
  if (isLoggedIn) {
    const sbAv   = el('sb-av');
    const sbName = el('sb-uname');
    if (sbAv)   sbAv.textContent   = initials;
    if (sbName) sbName.textContent = name;
  }
}

// ══ MODAL HELPERS ══
function openModal(id) {
  document.getElementById(id).classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}
document.addEventListener('click', e => {
  document.querySelectorAll('.mo.open').forEach(m => {
    if (e.target === m) m.classList.remove('open');
  });
});

// ══ TOAST ══
function toast(msg, type = 'success') {
  // Remove any existing toast first
  document.querySelectorAll('.toast').forEach(t => t.remove());
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.innerHTML = `<span>${type === 'success' ? '✅' : '❌'}</span><span>${msg}</span>`;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3200);
}


// ══ PRICE ALERT / NOTIFICATION SYSTEM ══
// Alerts are stored in localStorage as an array of { sym, dir, price, triggered }

let priceAlerts = JSON.parse(localStorage.getItem('st_alerts') || '[]');

function saveAlerts() {
  localStorage.setItem('st_alerts', JSON.stringify(priceAlerts));
}

function openNotifPanel() {
  renderAlerts();
  document.getElementById('notif-panel').classList.add('open');
}

function closeNotifPanel() {
  document.getElementById('notif-panel').classList.remove('open');
}

// Close panel if user clicks outside of it
document.addEventListener('click', function(e) {
  const panel = document.getElementById('notif-panel');
  const bell  = document.getElementById('notif-bell-btn');
  if (panel && panel.classList.contains('open') && !panel.contains(e.target) && e.target !== bell && !bell.contains(e.target)) {
    closeNotifPanel();
  }
});

function addPriceAlert() {
  let sym   = (document.getElementById('na-sym').value || '').toUpperCase().trim();
  const dir   = document.getElementById('na-dir').value;
  const price = parseFloat(document.getElementById('na-price').value);

  if (!sym)           { toast('Enter a stock symbol', 'error'); return; }
  if (!sym.includes('.')) sym += '.NS';
  if (!price || price <= 0) { toast('Enter a valid target price', 'error'); return; }

  priceAlerts.push({ id: Date.now(), sym, dir, price, triggered: false });
  saveAlerts();

  // Clear inputs
  document.getElementById('na-sym').value   = '';
  document.getElementById('na-price').value = '';

  renderAlerts();
  toast(`🔔 Alert set: ${sym.replace('.NS','')} ${dir === 'above' ? '≥' : '≤'} ${INR(price)}`, 'success');
}

function removeAlert(id) {
  priceAlerts = priceAlerts.filter(a => a.id !== id);
  saveAlerts();
  renderAlerts();
  updateBadge();
}

function renderAlerts() {
  const list = document.getElementById('notif-list');
  if (!list) return;

  if (priceAlerts.length === 0) {
    list.innerHTML = `<div class="empty" style="padding:28px 14px">
      <div class="ei">🔔</div>
      <div class="et">No alerts set</div>
      <div class="ed">Add a stock symbol and target price below</div>
    </div>`;
    return;
  }

  list.innerHTML = priceAlerts.map(a => {
    const dirLabel = a.dir === 'above' ? '≥' : '≤';
    return `<div class="notif-item ${a.triggered ? 'triggered' : ''}">
      <div class="notif-sym">${a.sym.replace('.NS','')}</div>
      <div class="notif-cond">${dirLabel} ${INR(a.price)}</div>
      ${a.triggered ? '<div class="notif-trg-lbl">✓ TRIGGERED</div>' : ''}
      <button class="rm-btn" onclick="removeAlert(${a.id})">✕</button>
    </div>`;
  }).join('');
}

function updateBadge() {
  const active = priceAlerts.filter(a => !a.triggered).length;
  const badge  = document.getElementById('notif-badge');

  if (badge) {
    if (active > 0) { badge.style.display = 'flex'; badge.textContent = active; }
    else            { badge.style.display = 'none'; }
  }
}

// Check all active alerts against current live prices
function checkAlerts() {
  let triggered = false;
  priceAlerts.forEach(alert => {
    if (alert.triggered) return;
    const q = S.live[alert.sym];
    if (!q) return;
    const currentPrice = q.regularMarketPrice || 0;
    const hit = (alert.dir === 'above' && currentPrice >= alert.price)
             || (alert.dir === 'below' && currentPrice <= alert.price);
    if (hit) {
      alert.triggered = true;
      triggered = true;
      const label = alert.dir === 'above' ? '≥' : '≤';
      toast(`🔔 Alert! ${alert.sym.replace('.NS','')} is now ${INR(currentPrice)} (target ${label} ${INR(alert.price)})`, 'success');
    }
  });
  if (triggered) { saveAlerts(); updateBadge(); }
}

// Initialise badge count on page load
updateBadge();

// ══ MOBILE SIDEBAR ══
// On screens narrower than 560px the sidebar slides in as a drawer overlay

function openMobSidebar() {
  document.getElementById('sidebar').classList.add('mob-open');
  document.getElementById('sidebar-overlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeMobSidebar() {
  document.getElementById('sidebar').classList.remove('mob-open');
  document.getElementById('sidebar-overlay').classList.remove('open');
  document.body.style.overflow = '';
}

// Auto-close the drawer whenever a nav item is tapped on mobile
document.querySelectorAll('.nav-i').forEach(item => {
  item.addEventListener('click', () => {
    if (window.innerWidth <= 560) closeMobSidebar();
  });
});


async function init() {
  applyAllSettings();
  updUserUI();
  mktStatus();

  // Re-check market open/closed status every minute
  setInterval(mktStatus, 60000);

  // Greet users who were already logged in via PHP session
  if (currentUser && window.__ST_USER) {
    toast(`👋 Welcome back, ${currentUser.name}!`, 'success');
  }

  await loadDash();

  // Auto-refresh prices every 5 minutes — respects the autoref setting
  // and checks price alerts after each refresh
  if (APP_SETTINGS.autoref !== false) {
    setInterval(() => {
      NSE7.forEach(c => delete S.cache[c.sym]);
      fetchMany(NSE7.map(c => c.sym)).then(quotes => {
        if (quotes.length) {
          quotes.forEach(q => { S.live[q.symbol] = q; });
          updTicker(quotes);
          checkAlerts();
        }
      });
    }, 300000);
  }
}

init();
</script>
</body>
</html>
