<?php
/**
 * CT-OS | binance_bot.php - Connectivity Fix
 */

require_once 'auth_check.php';
require_once 'db_config.php';
require_once 'functions.php'; 

$user_id = $_SESSION['user_id'];

function getStatusOnly($pdo, $user_id, $type) {
    $default = ['status' => 'OFFLINE', 'balance' => '0.00', 'pnl' => '0.00', 'color' => 'slate', 'bg' => 'bg-slate-500/10', 'border' => 'border-slate-500/20'];
    
    $stmt = $pdo->prepare("SELECT api_key, api_secret FROM api_keys WHERE user_id = ? AND account_type = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$user_id, $type]);
    $api = $stmt->fetch();

    if (!$api) return $default;

    $key = decrypt_data($api['api_key']);
    $secret = decrypt_data($api['api_secret']);
    $base_url = ($type === 'DEMO') ? "https://testnet.binancefuture.com" : "https://fapi.binance.com";

    $timestamp = number_format(round(microtime(true) * 1000), 0, '.', '');
    $query = "timestamp=" . $timestamp;
    $signature = hash_hmac('sha256', $query, $secret);
    
    $ch = curl_init($base_url . "/fapi/v2/account?" . $query . "&signature=" . $signature);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-MBX-APIKEY: ' . $key]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Αυξημένο timeout για σταθερότητα
    $res = curl_exec($ch);
    $data = json_decode($res, true);
    curl_close($ch);

    if (isset($data['totalMarginBalance'])) {
        return [
            'status' => 'ONLINE',
            'balance' => number_format($data['totalMarginBalance'], 2),
            'pnl' => number_format($data['totalUnrealizedProfit'], 2),
            'color' => ($type === 'LIVE') ? 'emerald' : 'yellow',
            'bg' => ($type === 'LIVE') ? 'bg-emerald-500/10' : 'bg-yellow-500/10',
            'border' => ($type === 'LIVE') ? 'border-emerald-500/20' : 'border-yellow-500/20'
        ];
    }
    
    return ['status' => 'AUTH_ERROR', 'balance' => '0.00', 'pnl' => '0.00', 'color' => 'red', 'bg' => 'bg-red-500/10', 'border' => 'border-red-500/20'];
}

$live = getStatusOnly($pdo, $user_id, 'LIVE');
$demo = getStatusOnly($pdo, $user_id, 'DEMO');
?>

<!DOCTYPE html>
<html lang="el" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=JetBrains+Mono:wght@500;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #020617; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .glass-card { 
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .glow-emerald { box-shadow: 0 0 40px -10px rgba(16, 185, 129, 0.15); }
        .glow-red { box-shadow: 0 0 40px -10px rgba(239, 68, 68, 0.15); }
    </style>
</head>
<body class="p-4 md:p-12 text-slate-200">

    <div class="max-w-4xl mx-auto">
        
        <header class="flex flex-col md:flex-row justify-between items-center mb-16 gap-6">
            <div class="text-center md:text-left">
                <div class="flex items-center gap-3 justify-center md:justify-start">
                    <div class="w-3 h-3 bg-yellow-500 rounded-full animate-pulse"></div>
                    <h1 class="text-3xl font-[900] italic uppercase tracking-tighter text-white">
                        CORE_<span class="text-yellow-500 text-shadow-glow">LINK</span>
                    </h1>
                </div>
                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.4em] mt-1 ml-6">Binance Infrastructure v3.1</p>
            </div>
            
            <button onclick="location.reload()" class="group flex items-center gap-3 bg-white/5 hover:bg-white/10 border border-white/10 px-6 py-3 rounded-2xl transition-all active:scale-95">
                <span class="text-xs font-black tracking-widest uppercase">Sync Assets</span>
                <svg class="w-4 h-4 group-hover:rotate-180 transition-transform duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            </button>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            
            <div class="glass-card p-8 rounded-[2.5rem] relative overflow-hidden transition-transform hover:scale-[1.02] <?= $live['status'] == 'ONLINE' ? 'glow-emerald' : 'glow-red' ?>">
                <div class="flex justify-between items-center mb-8">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                        <span class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Mainnet</span>
                    </div>
                    <div class="px-3 py-1 rounded-lg <?= $live['bg'] ?> text-<?= $live['color'] == 'emerald' ? 'emerald' : ($live['color'] == 'red' ? 'red' : 'slate') ?>-400 text-[10px] font-black border <?= $live['border'] ?>">
    <?= $live['status'] ?>
</div>
                </div>
                
                <div class="mb-6">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Total Margin Balance</p>
                    <div class="text-5xl font-[900] mono tracking-tighter text-white">$<?= $live['balance'] ?></div>
                </div>

                <div class="flex items-center gap-4 pt-6 border-t border-white/5">
                    <div>
                        <p class="text-[9px] font-bold text-slate-500 uppercase">Unrealized PnL</p>
                        <p class="text-lg mono font-bold <?= floatval($live['pnl']) >= 0 ? 'text-emerald-400' : 'text-red-400' ?>">
                            <?= floatval($live['pnl']) >= 0 ? '+' : '' ?><?= $live['pnl'] ?> <span class="text-[10px]">USDT</span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="glass-card p-8 rounded-[2.5rem] relative overflow-hidden transition-transform hover:scale-[1.02] border-yellow-500/10">
                <div class="flex justify-between items-center mb-8">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-yellow-500"></div>
                        <span class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Testnet</span>
                    </div>
<div class="px-3 py-1 rounded-lg <?= $demo['bg'] ?> 
    text-<?= $demo['color'] == 'yellow' ? 'yellow' : ($demo['color'] == 'red' ? 'red' : 'slate') ?>-400 
    text-[10px] font-black border <?= $demo['border'] ?>">
    <?= $demo['status'] ?>
</div>
                </div>
                
                <div class="mb-6">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1 text-yellow-500/50">Total Margin Balance</p>
                    <div class="text-5xl font-[900] mono tracking-tighter text-yellow-500/80">$<?= $demo['balance'] ?></div>
                </div>

                <div class="flex items-center gap-4 pt-6 border-t border-white/5">
                    <div>
                        <p class="text-[9px] font-bold text-slate-500 uppercase">Unrealized PnL</p>
                        <p class="text-lg mono font-bold <?= floatval($demo['pnl']) >= 0 ? 'text-emerald-400' : 'text-red-400' ?>">
                            <?= floatval($demo['pnl']) >= 0 ? '+' : '' ?><?= $demo['pnl'] ?> <span class="text-[10px]">USDT</span>
                        </p>
                    </div>
                </div>
            </div>

        </div>

        <div class="mt-16 group">
            <a href="https://accounts.binance.com/en/register?ref=C1T0JB20" target="_blank" class="relative flex items-center justify-between bg-yellow-500 hover:bg-yellow-400 text-black p-2 pr-8 rounded-3xl transition-all duration-300 transform group-hover:-translate-y-1">
                <div class="bg-black/10 p-4 rounded-2xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <span class="text-sm font-[900] uppercase tracking-widest">Create Binance Account</span>
                <svg class="w-5 h-5 animate-bounce-x" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
            </a>
        </div>

    </div>

</body>
</html>