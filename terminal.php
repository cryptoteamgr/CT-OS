<?php
/**
 * CT-OS | copyright by cryptoteam.gr - demo_trading.php
 * ----------------------------------------------------------------
 * Σκοπός: Το κεντρικό Terminal Interface για την παρακολούθηση και διαχείριση της στρατηγικής Statistical Arbitrage.
 */

// 1. Φόρτωση Πυρήνα
require_once 'auth_check.php'; // Διασφαλίζει session_start() και login
require_once 'db_config.php';
require_once 'functions.php';

// 2. Λήψη Στοιχείων Χρήστη από τη Βάση
$user_id = $_SESSION['user_id'];

try {
    // Χρησιμοποιούμε SELECT * για να φέρουμε όλες τις στήλες (Z-thresholds, TP/SL κλπ)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        die("User not found.");
    }

    // Ορίζουμε το τρέχον mode (αν είναι κενό στη βάση, default DEMO)
    $current_mode = !empty($user['bot_mode']) ? $user['bot_mode'] : 'DEMO';
    
    // Συγχρονίζουμε το session για να το βλέπουν και τα AJAX αρχεία
    $_SESSION['bot_mode'] = $current_mode;

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// 3. Προετοιμασία των API Keys για έλεγχο (χωρίς να τα εμφανίσουμε)
$stmtAPI = $pdo->prepare("SELECT id FROM api_keys WHERE user_id = ? AND account_type = ? AND is_active = 1");
$stmtAPI->execute([$user_id, $current_mode]);
$has_api = $stmtAPI->fetch();

// Αν δεν έχει κλειδιά για το συγκεκριμένο mode, θα βγάλουμε προειδοποίηση αργότερα στο UI
$api_warning = !$has_api ? true : false;
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <link rel="icon" href="data:,">
	<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT-OS v13.0 | STRATEGY ENGINE</title>
    
    <link href="https://unpkg.com/tailwindcss@^2/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/simple-statistics@7.8.0/dist/simple-statistics.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=JetBrains+Mono:wght@500;800&swap" rel="stylesheet">
    
    <style>
        /* 1. Global Variables & Reset */
        :root { 
            --bg: #020617; 
            --panel: #0f172a; 
            --border: #1e293b; 
            --live-red: #ef4444;
            --demo-green: #22c55e;
        }
        
        body { 
            background-color: var(--bg); 
            color: #e2e8f0; 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            padding: 0; /* Το padding θα μπει στο main container */
            overflow-x: hidden; 
        }

        /* 2. UI Components */
        .card-pro { 
            background: var(--panel); 
            border: 1px solid var(--border); 
            border-radius: 1rem; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            position: relative; 
        }
        
        /* State Indicators */
        .trade-win { border: 2px solid var(--demo-green) !important; box-shadow: 0 0 25px rgba(34, 197, 94, 0.15); }
        .trade-loss { border: 2px solid var(--live-red) !important; box-shadow: 0 0 25px rgba(239, 68, 68, 0.15); }
        
        /* Form Elements */
        input, select { 
            background: #020617 !important; 
            border: 1px solid #334155; 
            border-radius: 6px; 
            color: #fff; 
            font-family: 'JetBrains Mono'; 
            font-size: 11px; 
            text-align: center; 
            padding: 8px; 
            width: 100%; 
            cursor: help; 
        }

        /* 3. Specialized Elements */
        /* Αυξημένο ύψος για καλύτερο έλεγχο του Universe */
        .scanner-card { 
            height: 400px; 
            overflow-y: auto; 
            scrollbar-width: thin; 
            scrollbar-color: #3b82f6 transparent; 
        }
        
        .stat-value { font-family: 'JetBrains Mono'; font-weight: 800; }
        .active-cat { background: #3b82f6 !important; color: white !important; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4); }

        /* Beta Bars & Progress - Fixed Height for Dollars display */
        .beta-bar-container { 
            width: 100%; 
            height: 26px; /* Ελαφρώς μεγαλύτερο για να χωράνε τα $ */
            background: #020617; 
            border-radius: 6px; 
            overflow: hidden; 
            display: flex; 
            border: 1px solid #1e293b; 
            margin-top: 4px; 
        }
        
        /* Στο style tag σου */
.beta-bar-long { 
    background-color: #22c55e !important; /* Έντονο Πράσινο Tailwind */
    height: 100%; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    color: #000; 
    font-weight: 900;
}

.beta-bar-short { 
    background-color: #ef4444 !important; /* Έντονο Κόκκινο Tailwind */
    height: 100%; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    color: #fff; 
    font-weight: 900;
}

        .z-progress-bg { width: 100%; height: 4px; background: #1e293b; border-radius: 2px; margin-top: 4px; overflow: hidden; position: relative; }
        .z-progress-fill { height: 100%; transition: all 0.4s ease; width: 0%; }

        /* 4. Animations */
        @keyframes flash-red { 0%, 100% { background: #0f172a; } 50% { background: #450a0a; } }
        @keyframes flash-green { 0%, 100% { background: #0f172a; } 50% { background: #064e3b; } }
        .alert-extreme-high { animation: flash-red 1.5s infinite; }
        .alert-extreme-low { animation: flash-green 1.5s infinite; }
        @keyframes slideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }

        /* 5. Notifications & Toasts - Optimized */
        #notification-wrapper {
            position: fixed; 
            top: 20px; 
            right: 20px; 
            z-index: 9999;
            width: 340px; 
            display: flex; 
            flex-direction: column;
            max-height: 80vh;
            pointer-events: none; /* Επιτρέπει κλικ στα κουμπιά από κάτω αν δεν υπάρχει toast */
        }

        /* Το notif-header αφαιρέθηκε από εδώ καθώς καταργήθηκε από το HTML */

        #notification-container { 
            overflow-y: auto; 
            display: flex; 
            flex-direction: column; 
            gap: 8px; 
            padding: 10px; 
            max-height: 600px; /* Αυξημένο ύψος για να χωράνε περισσότερα toasts */
            pointer-events: auto; /* Επιτρέπει να κλείνεις τα ίδια τα toasts */
        }
        
        .toast { width: 100%; padding: 12px; border-radius: 8px; animation: slideIn 0.3s ease-out; border-left: 5px solid; display: flex; justify-content: space-between; }
        .toast-profit { border-color: var(--demo-green); background: rgba(6, 78, 59, 0.95); color: #fff; }
        .toast-loss { border-color: var(--live-red); background: rgba(69, 10, 10, 0.95); color: #fff; }

        /* 6. Interaction Elements */
        .pair-row { cursor: pointer; transition: 0.2s; padding: 8px 12px; border-radius: 6px; border-bottom: 1px solid rgba(255,255,255,0.03); }
        .pair-row:hover { background: rgba(59, 130, 246, 0.15); transform: translateX(5px); }

        /* Toggle Switches */
        .switch-bot { position: relative; display: inline-block; width: 44px; height: 20px; vertical-align: middle; }
        .switch-bot input { opacity: 0; width: 0; height: 0; }
        .slider-bot { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #1e293b; transition: .4s; border-radius: 20px; border: 1px solid #334155; }
        .slider-bot:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 2px; background-color: white; transition: .4s; border-radius: 50%; }
        
        input:checked + .slider-bot { background-color: #3b82f6; }
        input:checked + .slider-bot:before { transform: translateX(24px); }
        input:checked + .slider-bot.green { background-color: var(--demo-green); }
        
        button, .pair-row, .switch-bot { cursor: pointer; }

        /* Custom Scrollbar for Logs & Scanner */
        .scrollbar-thin::-webkit-scrollbar { width: 4px; }
        .scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; }
        .scrollbar-thin::-webkit-scrollbar-thumb:hover { background: #3b82f6; }
    </style>
</head>
<body onclick="initAudio()">

<div id="global-lock-notice" style="position: fixed; top: 0; left: 0; width: 100%; background: #ef4444; color: white; text-align: center; font-weight: 900; z-index: 99999; padding: 5px; font-size: 12px; display: none;">
    ⚠️ RISK MANAGEMENT LOCKDOWN ACTIVE
</div>

<div id="notification-wrapper">
    <div id="notification-container"></div>
</div>

<header class="max-w-[1880px] mx-auto flex flex-wrap justify-between items-center mb-6 p-5 card-pro border-l-8 <?= $current_mode === 'LIVE' ? 'border-red-600' : 'border-emerald-500' ?> shadow-2xl">
    <div class="flex items-center gap-6">
        <div class="flex items-center gap-4 px-4 py-2 bg-slate-900/80 rounded-xl border border-slate-800">
            <div class="flex flex-col">
                <span class="text-[9px] font-black text-slate-500 uppercase tracking-tighter">System Environment</span>
                <span id="mode-label" class="text-[11px] font-black <?= $current_mode === 'LIVE' ? 'text-red-500' : 'text-emerald-500' ?> uppercase">
                    ● <?= $current_mode ?> MODE
                </span>
            </div>
            <label class="switch-bot">
                <input type="checkbox" id="mode-switch" <?= $current_mode === 'LIVE' ? 'checked' : '' ?> 
                       onchange="playClickSound(); updateSystemMode(this.checked ? 'LIVE' : 'DEMO')">
                <span class="slider-bot <?= $current_mode === 'LIVE' ? 'orange' : 'green' ?>"></span>
            </label>
        </div>
    </div>

    <div class="text-center">
        <div class="text-[12px] text-blue-400 font-black font-mono uppercase tracking-[0.4em] animate-pulse">CT-OS beta | CORE ENGINE</div>
        <div class="text-[9px] text-slate-500 font-bold uppercase mt-1 italic">Status: <span class="text-emerald-500">Always Active</span></div>
    </div>

    <div class="flex flex-col items-end">
        <span class="text-[9px] text-slate-500 font-black uppercase italic tracking-widest">Master Operator</span>
        <span class="text-[11px] text-white font-bold"><?= htmlspecialchars($user['username']) ?></span>
    </div>
</header>

<div id="strategy-panel" class="max-w-[1880px] mx-auto mb-6 grid grid-cols-1 md:grid-cols-8 gap-4 bg-slate-900/60 p-5 rounded-2xl border border-slate-800 shadow-xl">
    <div class="flex flex-col gap-1">
        <label class="text-[10px] font-black text-slate-500 uppercase italic">Capital ($)</label>
        <input type="number" id="def-cap" value="<?= $user['capital_per_trade'] ?>" 
               onchange="updateBackendSetting('capital_per_trade', this.value)" class="bg-slate-950 border-slate-700">
    </div>

    <div class="flex flex-col gap-1 border-l border-slate-700 pl-4">
        <label class="text-[10px] font-black text-yellow-500 uppercase italic">Max Trades</label>
        <input type="number" id="def-max-trades" value="<?= $user['max_open_trades'] ?? 3 ?>" 
               onchange="updateBackendSetting('max_open_trades', this.value)" class="bg-slate-950 border-slate-700">
    </div>

    <div class="flex flex-col gap-1 border-l border-slate-700 pl-4">
        <label class="text-[10px] font-black text-blue-500 uppercase italic">Leverage (x)</label>
        <input type="number" id="def-lev" value="<?= $user['leverage'] ?>" 
               onchange="updateBackendSetting('leverage', this.value)" class="bg-slate-950 border-slate-700">
    </div>

    <div class="flex flex-col gap-1 border-l border-slate-700 pl-4">
        <label class="text-[10px] font-black text-orange-400 uppercase italic">Entry Z-Score</label>
        <input type="number" step="0.1" id="def-z-entry" value="<?= $user['z_threshold'] ?>" 
               onchange="updateBackendSetting('z_threshold', this.value)" class="bg-slate-950 border-slate-700">
    </div>

    <div class="flex flex-col gap-1 border-l border-slate-700 pl-4">
        <label class="text-[10px] font-black text-green-500 uppercase italic">TP ($)</label>
        <input type="number" id="def-tp" value="<?= $user['tp_dollar'] ?>" 
               onchange="updateBackendSetting('master_tp', this.value)" class="bg-slate-950 border-slate-700">
    </div>

    <div class="flex flex-col gap-1">
        <label class="text-[10px] font-black text-green-500 uppercase italic">TP (Z-Score)</label>
        <input type="number" step="0.1" id="def-z-tp" value="<?= $user['z_exit_threshold'] ?>" 
               onchange="updateBackendSetting('z_exit_threshold', this.value)" class="bg-slate-950 border-slate-700">
    </div>

    <div class="flex flex-col gap-1 border-l border-slate-700 pl-4">
        <label class="text-[10px] font-black text-red-500 uppercase italic">SL ($)</label>
        <input type="number" id="def-sl" value="<?= $user['sl_dollar'] ?>" 
               onchange="updateBackendSetting('master_sl', this.value)" class="bg-slate-950 border-slate-700">
    </div>

    <div class="flex flex-col gap-1 border-l border-slate-700 pl-4">
        <label class="text-[10px] font-black text-red-500 uppercase italic">SL (Z-Score)</label>
        <input type="number" step="0.1" id="def-z-sl" value="<?= $user['sl_zscore'] ?? 70 ?>" 
               onchange="updateBackendSetting('exit_sl_z', this.value)" class="bg-slate-950 border-slate-700">
    </div>
	</div>

<div class="max-w-[1880px] mx-auto grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="card-pro p-6 flex flex-col items-center justify-center bg-slate-900/40">
        <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Total Equity</span>
        <div id="ui-equity" class="text-3xl stat-value">$0.00</div>
        <div class="text-[9px] text-slate-500 mt-2 uppercase">Available: <span id="ui-balance" class="text-blue-400">$0.00</span></div>
    </div>

    <div class="card-pro p-6 flex flex-col items-center justify-center border-b-4 border-blue-500">
        <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Unrealized PnL</span>
        <div id="ui-pnl" class="text-3xl stat-value">$0.00</div>
        <div id="ui-pnl-percent" class="text-[10px] font-bold text-slate-400 flex flex-col items-center">0.00%</div>
    </div>

    <div class="card-pro p-6 flex flex-col items-center justify-center">
        <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Active Hedges</span>
        <div id="ui-active-count" class="text-4xl stat-value text-white">0</div>
        <div class="text-[9px] text-emerald-500 mt-2 font-bold uppercase animate-pulse">Monitoring Live</div>
    </div>

    <div class="card-pro p-6 flex flex-col items-center justify-center <?= ($user['bot_status'] ?? 'OFF') === 'ON' ? 'border-emerald-500/50' : 'border-red-500/50' ?>">
        <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Bot Autopilot</span>
        <label class="switch-bot">
            <input type="checkbox" id="bot-master-switch" <?= ($user['bot_status'] ?? 'OFF') === 'ON' ? 'checked' : '' ?> onchange="toggleBotStatus(this.checked)">
            <span class="slider-bot"></span>
        </label>
        <span id="bot-status-text" class="text-[10px] font-black mt-2 <?= ($user['bot_status'] ?? 'OFF') === 'ON' ? 'text-emerald-500' : 'text-red-500' ?>">
            <?= htmlspecialchars($user['bot_status'] ?? 'OFF') ?>
        </span>
    </div> 
</div>

<div class="max-w-[1880px] mx-auto grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 animate-fade-in">
    <div class="card-pro p-6 flex flex-col items-center justify-center border-b-4 border-emerald-500 bg-slate-900/40">
        <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Net Profit (Realized)</span>
        <div id="ui-net-profit" class="text-3xl stat-value text-emerald-500">$0.00</div>
        <div class="text-[9px] text-slate-500 mt-2 uppercase italic tracking-tighter">Clear Profit After Fees</div>
    </div>

    <div class="card-pro p-6 flex flex-col items-center justify-center border-b-4 border-yellow-600 bg-slate-900/40">
        <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Total Fees Paid</span>
        <div id="ui-total-fees" class="text-3xl stat-value text-yellow-500">$0.00</div>
        <div class="text-[9px] text-slate-500 mt-2 uppercase italic tracking-tighter">Exchange Commissions</div>
    </div>

    <div class="card-pro p-6 flex flex-col items-center justify-center bg-slate-900/40">
        <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Gross Profit</span>
        <div id="ui-gross-profit" class="text-3xl stat-value text-white">$0.00</div>
        <div class="text-[9px] text-slate-500 mt-2 uppercase italic tracking-tighter">Profit Before Fees</div>
    </div>

    <div class="card-pro p-6 flex flex-col items-center justify-center bg-slate-900/40">
        <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Closed Trades</span>
        <div id="ui-total-trades" class="text-3xl stat-value text-blue-400">0</div>
        <div class="text-[9px] text-slate-500 mt-2 uppercase italic tracking-tighter">Journal Records</div>
    </div>
</div>

<div class="max-w-[1880px] mx-auto mb-8">
    <div class="card-pro p-8 bg-slate-900/40 overflow-hidden border-t-4 border-blue-500/30 shadow-2xl">
        <div class="flex justify-between items-center mb-8">
            <div class="flex items-center gap-4">
                <div class="w-3 h-3 bg-blue-500 rounded-full animate-ping"></div>
                <h3 class="text-xl font-black uppercase tracking-[0.2em] text-white">Live Active Positions</h3>
            </div>
            <span id="pos-count-badge" class="bg-blue-600/20 text-blue-400 text-xs px-5 py-2 rounded-full font-black border border-blue-500/30 uppercase tracking-widest">
                0 POSITIONS
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-xs uppercase text-slate-500 border-b-2 border-slate-800">
    <th class="pb-6 font-black tracking-widest pl-4 text-[10px] uppercase italic">Pair / Mode</th>
<th class="pb-6 font-black tracking-widest text-blue-400 text-[10px] uppercase italic">Beta (β)</th> 
<th class="pb-6 font-black tracking-widest text-[10px] uppercase italic">Side A</th>
<th class="pb-6 font-black tracking-widest text-[10px] uppercase italic">Side B</th>
<th class="pb-6 font-black text-center tracking-widest text-[10px] uppercase italic">Beta Weighting ($)</th>
<th class="pb-6 font-black text-center tracking-widest text-slate-500 text-[10px] uppercase italic">Fees Paid</th>

<th class="pb-6 font-black text-center tracking-widest text-emerald-500 text-[10px] uppercase italic">Entry Ratio</th>
<th class="pb-6 font-black text-center tracking-widest text-blue-400 text-[10px] uppercase italic">Live Ratio</th>

<th class="pb-6 font-black text-center tracking-widest text-[10px] uppercase italic text-slate-400">Sync</th> 
<th class="pb-6 font-black text-center tracking-widest text-[10px] uppercase italic">Entry Z</th>
<th class="pb-6 font-black text-center tracking-widest text-[10px] uppercase italic text-blue-400">Live Z</th>
<th class="pb-6 font-black text-right tracking-widest text-[10px] uppercase italic">PnL ($)</th>
<th class="pb-6 font-black text-right tracking-widest whitespace-nowrap pr-4 pl-6 text-[10px] uppercase italic">Actions</th>
</tr>
                </thead>
                <tbody id="active-positions-body" class="divide-y divide-slate-800/50">
                    <tr><td colspan="9" class="py-20 text-center text-slate-600 text-sm italic font-mono tracking-widest">[ SYSTEM ] Scanning for active hedges...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="max-w-[1880px] mx-auto mb-8">
    <div class="card-pro p-6 bg-slate-900/40 border-l-4 border-blue-500 shadow-2xl">
        <div class="flex justify-between items-center mb-4 border-b border-slate-800 pb-4">
            <div class="flex flex-col">
                <h3 class="text-sm font-black uppercase tracking-widest text-blue-400 italic">Statistical Scanner v2.0</h3>
                <div class="flex items-center gap-2">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span class="text-[10px] text-slate-300 uppercase font-bold tracking-tighter">Z-Score Engine Active</span>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <input type="text" id="scanner-search" placeholder="FILTER PAIRS..." 
                       class="w-32 bg-slate-950 border-slate-800 text-[10px] px-3 py-1 rounded focus:border-blue-500 outline-none"
                       onkeyup="filterScanner()">
                <span class="text-[10px] text-blue-400 font-mono bg-blue-950/30 px-3 py-1.5 rounded border border-blue-500/30" id="scan-timer">READY TO SCAN</span>
            </div>
        </div>
        <div id="scanner-results" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-3 min-h-[400px] max-h-[400px] overflow-y-auto pr-2 scrollbar-thin">
             <div class="col-span-full flex flex-col justify-center items-center opacity-50 py-20">
                <div class="w-8 h-8 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mb-4"></div>
                <span class="text-[10px] text-slate-500 uppercase font-black tracking-widest">Establishing Market Link...</span>
            </div>
        </div>
    </div>
</div>

<script>
function updateSystemMode(mode) {
    const label = document.getElementById('mode-label');
    const header = document.querySelector('header');
    if (label) {
        label.innerHTML = `● ${mode} MODE`;
        label.className = `text-[11px] font-black uppercase ${mode === 'LIVE' ? 'text-red-500' : 'text-emerald-500'}`;
    }
    if (header) {
        header.className = `max-w-[1880px] mx-auto flex flex-wrap justify-between items-center mb-6 p-5 card-pro border-l-8 ${mode === 'LIVE' ? 'border-red-600' : 'border-emerald-500'} shadow-2xl`;
    }
    fetch('api_update_settings.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ key: 'bot_mode', value: mode })
    });
}

function playClickSound() { console.log("Mode Switched"); }

async function updateDashboard() {
    try {
        // Use session mode instead of UI switch for consistency
        const mode = '<?php echo $current_mode; ?>';

        // 1. Stats
        const statsRes = await fetch(`api_get_journal_stats.php?mode=${mode}`);
        const stats = await statsRes.json();
        
        if (stats.success) {
            // Ενημέρωση Net Profit
            if (document.getElementById('ui-net-profit')) {
                document.getElementById('ui-net-profit').innerText = '$' + stats.net_profit.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
            
            // Ενημέρωση Total Fees
            if (document.getElementById('ui-total-fees')) {
                document.getElementById('ui-total-fees').innerText = '$' + stats.total_fees.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
            
            // Ενημέρωση Gross Profit (ΠΡΟΣΘΗΚΗ)
            if (document.getElementById('ui-gross-profit')) {
                document.getElementById('ui-gross-profit').innerText = '$' + stats.gross_profit.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }

            // Ενημέρωση Closed Trades Count
            if (document.getElementById('ui-total-trades')) {
                document.getElementById('ui-total-trades').innerText = stats.total_trades;
            }
        }

        // 2. PnL & Positions
        const response = await fetch(`get_pnl.php?mode=${mode}`);
        const data = await response.json();

        if (data.success) {
            // Equity
            const equityVal = parseFloat(data.equity || 0);
            const equityElem = document.getElementById('ui-equity');
            if (equityElem) {
                equityElem.innerText = '$' + equityVal.toLocaleString(undefined, {minimumFractionDigits: 2});
                equityElem.style.color = (equityVal >= parseFloat(data.balance)) ? '#22c55e' : '#ef4444';
            }

            // Available (Διόρθωση)
            const balanceElem = document.getElementById('ui-balance');
            if (balanceElem) {
                balanceElem.innerText = '$' + parseFloat(data.available || 0).toLocaleString(undefined, {minimumFractionDigits: 2});
            }

            // Unrealized & Risk (Margin Ratio)
const pnlElem = document.getElementById('ui-pnl');
const pnlVal = parseFloat(data.total_upnl || 0);
const marginRatio = parseFloat(data.margin_ratio || 0); // Λήψη του ρίσκου

if (pnlElem) {
    pnlElem.innerText = (pnlVal >= 0 ? '+$' : '-$') + Math.abs(pnlVal).toFixed(2);
    pnlElem.style.color = pnlVal >= 0 ? '#22c55e' : '#ef4444';
}

// Ενημέρωση της μικρής γραμμής κάτω από το PnL
const ratioElem = document.getElementById('ui-pnl-percent'); 
if (ratioElem) {
    // Θα δείχνει π.χ.: PNL: 0.50% | RISK: 1.20%
    ratioElem.innerHTML = `PNL: ${parseFloat(data.upnl_percent || 0).toFixed(2)}% | RISK: ${marginRatio.toFixed(2)}%`;
    
    // Αλλαγή χρώματος αν το ρίσκο είναι υψηλό (πάνω από 5%)
    ratioElem.style.color = marginRatio > 5 ? '#facc15' : '#94a3b8'; 
}

            // Table
            const tbody = document.getElementById('active-positions-body');
            const activeCountElem = document.getElementById('ui-active-count');
            
            if (data.positions && Array.isArray(data.positions) && data.positions.length > 0) {
                if (activeCountElem) activeCountElem.innerText = data.positions.length;
                
                // Update the main counter badge as well
                const counterBadge = document.getElementById('pos-count-badge');
                if (counterBadge) counterBadge.innerText = `${data.positions.length} POSITIONS`;
                
                tbody.innerHTML = data.positions.map(pos => {
    const pnlColor = parseFloat(pos.pnl) >= 0 ? '#22c55e' : '#ef4444';
    
    const vA = parseFloat(pos.val_a || 0);
    const vB = parseFloat(pos.val_b || 0);
    const totalVal = vA + vB;
    const weightA = totalVal > 0 ? (vA / totalVal) * 100 : 50;

    // ΥΠΟΛΟΓΙΣΜΟΣ RATIOS
const entryRatioNum = parseFloat(pos.entry_price_a) / parseFloat(pos.entry_price_b);
const liveRatioNum = parseFloat(pos.current_price_a) / parseFloat(pos.current_price_b);

// Λογική Χρώματος: Αν η διαφορά είναι ελάχιστη (σύγκλιση), γίνεται κίτρινο/χρυσό
const ratioDiff = Math.abs(entryRatioNum - liveRatioNum);
const ratioColorClass = ratioDiff < 0.00010 ? 'text-yellow-400 font-black scale-110' : 'text-blue-400 font-bold';

return `
<tr class="text-[13px] font-mono hover:bg-slate-800/40 border-b border-slate-800/50">
    <td class="py-6 pl-4 text-white font-black">${pos.asset_a}/${pos.asset_b}</td>
    <td class="py-6 text-blue-400 font-bold">β:${pos.beta}</td>
    <td class="py-6">${pos.side_a}</td>
    <td class="py-6">${pos.side_b}</td>
    <td class="py-6">
        <div class="beta-bar-container">
            <div class="beta-bar-long" style="width: ${weightA}%">$${vA.toFixed(1)}</div>
            <div class="beta-bar-short" style="width: ${100-weightA}%">$${vB.toFixed(1)}</div>
        </div>
    </td>
    <td class="py-6 text-center text-yellow-500">$${(parseFloat(pos.commission_a||0) + parseFloat(pos.commission_b||0)).toFixed(2)}</td>
    
    <td class="py-6 text-center text-emerald-500 font-bold">${entryRatioNum.toFixed(5)}</td>
    <td class="py-6 text-center ${ratioColorClass} animate-pulse transition-all">${liveRatioNum.toFixed(5)}</td>
    
    <td class="py-6 text-center text-slate-500 text-[10px] font-bold">${pos.last_seen}</td>
    <td class="py-6 text-center text-slate-400">${pos.entry_z_score}</td>
    <td class="py-6 text-center text-blue-400 font-black">${pos.current_z || '---'}</td>
    <td class="py-6 text-right">
        <div class="flex flex-col items-end">
            <span class="font-black text-base" style="color: ${pnlColor}">${parseFloat(pos.pnl).toFixed(2)}$</span>
            <div class="flex gap-2 mt-1">
                <span class="text-[10px]" style="color: ${parseFloat(pos.binance_pnl_a || 0) >= 0 ? '#22c55e' : '#ef4444'}">${pos.asset_a}USDT: ${parseFloat(pos.binance_pnl_a || 0).toFixed(2)}$</span>
                <span class="text-[10px]" style="color: ${parseFloat(pos.binance_pnl_b || 0) >= 0 ? '#22c55e' : '#ef4444'}">${pos.asset_b}USDT: ${parseFloat(pos.binance_pnl_b || 0).toFixed(2)}$</span>
            </div>
        </div>
    </td>
    <td class="py-6 text-right pr-6">
        <button onclick="closePosition(${pos.id})" class="bg-red-600 text-white text-[10px] px-4 py-1 rounded font-bold shadow-lg">CLOSE</button>
    </td>
</tr>`;
}).join('');
            } else {
    if (activeCountElem) activeCountElem.innerText = "0";
    tbody.innerHTML = '<tr><td colspan="11" class="text-center py-20 text-slate-600 italic">No active positions.</td></tr>';
}
        }
    } catch (error) { console.error("Dashboard Error:", error); }
}

async function updateScannerUI() {
    try {
        const response = await fetch('api_get_scanner.php');
        const data = await response.json();
        
        if (data.success && data.pairs) {
            const container = document.getElementById('scanner-results');
            const now = new Date(); // Η τρέχουσα ώρα του υπολογιστή σου

            container.innerHTML = data.pairs.map(pair => {
                const zVal = parseFloat(pair.z_score) || 0;
                
                // Υπολογισμός Margin βάσει του Capital και Leverage που έχει πάνω το Panel
                const userCapital = parseFloat(document.getElementById('def-cap').value) || 0;
                const userLev = parseFloat(document.getElementById('def-lev').value) || 10;
                const pairBeta = Math.abs(parseFloat(pair.beta)) || 1.2;
                
                // Exposure = Capital A + (Capital A * Beta)
                const estExposure = userCapital + (userCapital * pairBeta);
                const estMargin = estExposure / userLev;

                // 1. Υπολογισμός αν τα δεδομένα είναι "μπαγιάτικα"
                const [hours, minutes, seconds] = pair.last_update.split(':');
                const updateTime = new Date();
                updateTime.setHours(hours, minutes, seconds);
                
                const diffSeconds = Math.floor((now - updateTime) / 1000);
                
                // 2. Οπτική προειδοποίηση
                let zColor = 'text-blue-400';
                let statusTag = '';
                let cardOpacity = 'opacity-100';

                if (Math.abs(zVal) >= 2.0) {
                    zColor = 'text-red-500 font-black animate-pulse';
                }

                if (diffSeconds > 180 || pair.last_update === 'N/A') {
                    zColor = 'text-slate-600 font-normal'; 
                    cardOpacity = 'opacity-50'; 
                    statusTag = '<span class="text-[8px] text-red-600 font-black uppercase ml-2">● OFFLINE</span>';
                }

                const iconA = `https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/${pair.asset_a.toLowerCase()}.png`;
                const iconB = `https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/${pair.asset_b.toLowerCase()}.png`;

                return `
                    <div class="p-3 bg-slate-900/60 border border-slate-800 rounded flex flex-col mb-2 ${cardOpacity} transition-all hover:border-blue-500/50">
                        <div class="flex justify-between items-center w-full">
                            <div class="flex items-center gap-2">
                                <div class="flex -space-x-2">
                                    <img src="${iconA}" class="w-5 h-5 rounded-full border border-slate-800" onerror="this.src='https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/generic.png'">
                                    <img src="${iconB}" class="w-5 h-5 rounded-full border border-slate-800" onerror="this.src='https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/generic.png'">
                                </div>
                                <span class="text-[10px] font-black text-white uppercase">${pair.asset_a}/${pair.asset_b}</span>
                            </div>
                            <span class="text-[11px] font-mono ${zColor}">Z: ${zVal.toFixed(2)}</span>
                        </div>

                        <div class="flex justify-between items-center mt-2 bg-black/40 p-1.5 rounded border border-white/5">
                            <div class="flex flex-col">
                                <span class="text-[7px] text-slate-500 uppercase font-bold tracking-tighter">Est. Margin</span>
                                <span class="text-[10px] text-emerald-400 font-black">$${estMargin.toFixed(2)}</span>
                            </div>
                            <div class="flex flex-col text-right">
                                <span class="text-[7px] text-slate-500 uppercase font-bold tracking-tighter">Current Beta</span>
                                <span class="text-[10px] text-blue-400 font-black">${pairBeta.toFixed(2)}</span>
                            </div>
                        </div>

                        <div class="flex justify-between items-center mt-1 border-t border-white/5 pt-1">
                            <span class="text-[8px] text-slate-500 font-bold uppercase tracking-tighter">
                                <i class="far fa-clock mr-1"></i> Sync: ${pair.last_update} ${statusTag}
                            </span>
                            <span class="text-[8px] font-bold uppercase tracking-tighter ${pair.is_cointegrated ? 'text-emerald-400' : 'text-red-400'}">
                                ${pair.is_cointegrated ? '✓ COINTEGRATED' : '✗ NOT COINTEGRATED'}
                            </span>
                        </div>
                    </div>
`;
                }).join('');
            }
        } catch (e) { console.error("Scanner UI Error:", e); }
    }

function startDashboardPulse() {
    updateDashboard(); updateScannerUI(); updateLogsUI();
    setInterval(() => { updateDashboard(); updateScannerUI(); updateLogsUI(); }, 10000);
}

document.addEventListener('DOMContentLoaded', startDashboardPulse);

async function closePosition(positionId) {
    if (!confirm('⚠️ Close position?')) return;
    
    // ΕΝΗΜΕΡΩΜΕΝΗ ΚΛΗΣΗ ΓΙΑ ΤΟ NEO api_close_pair.php
    try {
        const response = await fetch('api_close_pair.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pair_id: positionId }) // Στέλνουμε το ID ως JSON
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast("POSITION CLOSED SUCCESS", "success");
            updateDashboard(); // Ανανέωση του πίνακα θέσεων αμέσως
        } else {
            // Εμφάνιση του συγκεκριμένου σφάλματος από την Binance αν υπάρχει
            showToast("ERROR: " + (result.message || "Unknown Error"), "loss");
            console.error("Close Error Details:", result.message);
        }
    } catch (error) {
        showToast("CONNECTION ERROR", "loss");
        console.error("Fetch Error:", error);
    }
}

async function updateLogsUI() {
    try {
        const response = await fetch('api_fetch_broadcast.php'); 
        if (!response.ok) return;
        const data = await response.json();
        if (data && data.notifs && data.notifs.length > 0) {
            data.notifs.forEach(notif => {
                if (window.lastLogId !== notif.id) {
                    window.lastLogId = notif.id;
                    showToast(notif.message, notif.type === 'SUCCESS' ? 'success' : 'loss');
                }
            });
        }
    } catch (e) { /* ignore */ }
}
function toggleBotStatus(status) {
    const val = status ? 'ON' : 'OFF';
    const statusText = document.getElementById('bot-status-text');
    if (statusText) {
        statusText.innerText = val;
        statusText.className = `text-[10px] font-black mt-2 ${status ? 'text-emerald-500' : 'text-red-500'}`;
    }
    
    fetch('api_update_settings.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ key: 'bot_status', value: val })
    }).then(res => res.json())
      .then(data => {
          if(data.success) showToast(`BOT IS NOW ${val}`, status ? 'success' : 'loss');
      });
}
function filterScanner() {
    const query = document.getElementById('scanner-search').value.toUpperCase();
    const resultsContainer = document.getElementById('scanner-results');
    const items = resultsContainer.querySelectorAll('div[class*="bg-slate-900"]');
    items.forEach(item => {
        const text = item.innerText.toUpperCase();
        item.style.display = text.includes(query) ? 'flex' : 'none';
    });
}
function initAudio() { console.log("Audio Init"); }

function showToast(message, type) {
    const container = document.getElementById('notification-container');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `toast ${type === 'success' ? 'toast-profit' : 'toast-loss'} mb-2`;
    toast.innerHTML = `<span class="text-[10px] font-black uppercase tracking-tighter">${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

async function updateBackendSetting(key, value) {
    let dbKey = key;
    if (key === 'master_tp') dbKey = 'tp_dollar';
    if (key === 'master_sl') dbKey = 'sl_dollar';

    try {
        const response = await fetch('api_update_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ key: dbKey, value: value }) 
        });
        const result = await response.json();
        
        if (result.success) {
            // Αν η ρύθμιση που αλλάξαμε επηρεάζει το Margin (Capital ή Leverage)
            if (result.est_margin && (dbKey === 'capital_per_trade' || dbKey === 'leverage')) {
                showToast(`CONFIRMED: ${dbKey.toUpperCase()} | NEW MARGIN: $${result.est_margin}`, 'success');
            } else {
                showToast(`UPDATED: ${dbKey.toUpperCase()}`, 'success');
            }
            
            // Προαιρετικά: Αν θέλεις να ανανεώνεται ο Scanner αμέσως μόλις αλλάξεις το Capital
            if (dbKey === 'capital_per_trade' || dbKey === 'leverage') {
                updateScannerUI();
            }
        }
    } catch (e) {
        showToast("CONNECTION ERROR", "loss");
    }
}

</script>