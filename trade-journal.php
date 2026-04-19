<?php
/**
 * CT-OS | copyright by cryptoteam.gr - trade-journal.php
 * ----------------------------------------------------------------
 * Σκοπός: Το "Performance Journal Pro" είναι το αναλυτικό κέντρο στατιστικών του trader. 
 * Παρακολουθεί την κερδοφορία (PnL), το Win Rate και τον συντελεστή κέρδους (Profit Factor), 
 * διαχωρίζοντας πλήρως τα δεδομένα μεταξύ LIVE και DEMO λογαριασμών.
 */
session_start();

// Security: Prevent direct access if not logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: login.php");
    exit;
}

// Error Reporting (Development only)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db_config.php';

// 1. ΠΡΩΤΑ ΟΡΙΖΟΥΜΕ ΤΟ MODE (DEMO / LIVE)
if (isset($_GET['mode'])) {
    $requested_mode = strtoupper($_GET['mode']);
    $_SESSION['journal_mode'] = ($requested_mode === 'LIVE') ? 'LIVE' : 'DEMO';
}
$current_mode = $_SESSION['journal_mode'] ?? 'LIVE';

// 2. ΤΩΡΑ ΕΚΤΕΛΟΥΜΕ ΤΑ QUERIES ΠΟΥ ΧΡΕΙΑΖΟΝΤΑΙ ΤΟ $current_mode
// --- ΛΗΨΗ ΑΝΟΙΧΤΩΝ ΕΝΤΟΛΩΝ (OPEN ORDERS) ---
$stmtOrders = $pdo->prepare("SELECT * FROM zEQZkBci_binance_orders WHERE user_id = ? AND account_type = ? ORDER BY created_at DESC");
$stmtOrders->execute([$user_id, $current_mode]);
$open_orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

// --- ΛΗΨΗ FUNDING FEES ---
$stmtFunding = $pdo->prepare("SELECT SUM(amount) as total_funding FROM zEQZkBci_binance_funding WHERE user_id = ? AND account_type = ?");
$stmtFunding->execute([$user_id, $current_mode]);
$total_funding = floatval($stmtFunding->fetch()['total_funding'] ?? 0);

// 3. ΑΡΧΙΚΟΠΟΙΗΣΗ ΜΕΤΑΒΛΗΤΩΝ ΣΤΑΤΙΣΤΙΚΗΣ
$stats = [
    'totalPnL' => 0,
    'wins' => 0,
    'losses' => 0,
    'breakeven' => 0,
    'sumWins' => 0,
    'sumLosses' => 0,
    'winRate' => 0,
    'rrRatio' => "0.00",
    'profitFactor' => "0.00"
];

try {
    // SQL QUERY: Optimized fetch (FIXED)
    $stmt = $pdo->prepare("SELECT id, CONCAT(asset_a, '/', asset_b) as pair, entry_price_a, entry_price_b, exit_price_a, exit_price_b, final_pnl as gross_pnl, commission_a + commission_b as total_commission, final_pnl - (commission_a + commission_b) as net_pnl, TIMESTAMPDIFF(MINUTE, created_at, closed_at) as duration_minutes, mode as setup, notes, 'Closed' as exit_reason, created_at, closed_at
                           FROM active_pairs
                           WHERE user_id = :uid AND status = 'CLOSED' AND mode = :mode
                           ORDER BY closed_at DESC");
    $stmt->execute([':uid' => $user_id, ':mode' => $current_mode]);
    $db_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Υπολογισμός Στατιστικών
    foreach ($db_logs as $log) {
        // Χρησιμοποιούμε το net_pnl αν υπάρχει, αλλιώς το υπολογίζουμε
        $val = isset($log['net_pnl']) ? floatval($log['net_pnl']) : (floatval($log['pnl'] ?? 0) - floatval($log['total_commission'] ?? 0));
        $stats['totalPnL'] += $val;
        
        if ($val > 0) {
            $stats['wins']++;
            $stats['sumWins'] += $val;
        } elseif ($val < 0) {
            $stats['losses']++;
            $stats['sumLosses'] += abs($val);
        } else {
            $stats['breakeven']++;
        }
    }

    // Λήψη Transaction History (Deposits/Withdrawals)
    $stmtTrans = $pdo->prepare("SELECT * FROM zEQZkBci_binance_transactions WHERE user_id = ? AND account_type = ? ORDER BY timestamp DESC LIMIT 20");
    $stmtTrans->execute([$user_id, $current_mode]);
    $transactions = $stmtTrans->fetchAll(PDO::FETCH_ASSOC);
	
    // Προσθήκη Funding στο συνολικό PnL
    $stats['totalPnL'] += $total_funding;
    
    $activeTrades = $stats['wins'] + $stats['losses'];
    if ($activeTrades > 0) {
        $stats['winRate'] = round(($stats['wins'] / $activeTrades) * 100);
        $avgWin = ($stats['wins'] > 0) ? ($stats['sumWins'] / $stats['wins']) : 0;
        $avgLoss = ($stats['losses'] > 0) ? ($stats['sumLosses'] / $stats['losses']) : 0;
        
        if ($avgLoss > 0) {
            $stats['rrRatio'] = number_format($avgWin / $avgLoss, 2);
        } else {
            $stats['rrRatio'] = ($stats['wins'] > 0) ? '∞' : '0.00';
        }
        
        if ($stats['sumLosses'] > 0) {
            $stats['profitFactor'] = number_format($stats['sumWins'] / $stats['sumLosses'], 2);
        } else {
            $stats['profitFactor'] = ($stats['sumWins'] > 0) ? '∞' : '0.00';
        }
    }
// ΕΔΩ ΛΕΙΠΕΙ ΤΟ ΚΛΕΙΣΙΜΟ
} catch (Exception $e) {
    error_log("Journal Error: " . $e->getMessage());
    $db_logs = [];
    $binance_trades = [];
    $transactions = [];
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT-OS Journal | <?= $current_mode ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Inter:wght@400;900&display=swap" rel="stylesheet">
    <style>
        body { background-color: #020617; color: #f1f5f9; font-family: 'Inter', sans-serif; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .card { background: #0f172a; border: 1px solid #1e293b; border-radius: 1.25rem; padding: 1.5rem; transition: all 0.3s ease; }
        .card:hover { border-color: #3b82f6; box-shadow: 0 0 20px rgba(59, 130, 246, 0.1); }
        .mode-toggle { display: flex; background: #1e293b; padding: 4px; border-radius: 12px; border: 1px solid #334155; }
        .mode-btn { padding: 6px 16px; border-radius: 8px; font-size: 10px; font-weight: 800; transition: all 0.2s; text-transform: uppercase; }
        .active-demo { background: #10b981; color: white; box-shadow: 0 0 10px rgba(16, 185, 129, 0.4); }
        .active-live { background: #ef4444; color: white; box-shadow: 0 0 10px rgba(239, 68, 68, 0.4); }
    </style>
</head>
<body class="p-4 md:p-8">
    <div class="max-w-6xl mx-auto">
        
        <header class="mb-10 flex flex-wrap justify-between items-end gap-4">
            <div>
                <h1 class="text-3xl font-black italic text-blue-500 uppercase tracking-tighter">Performance Journal</h1>
                <p class="text-[10px] font-bold text-slate-500 tracking-widest uppercase italic">Session: <span class="<?= $current_mode == 'LIVE' ? 'text-red-500' : 'text-emerald-500' ?>"><?= $current_mode ?></span></p>
            </div>
            <div class="mode-toggle">
                <a href="?mode=DEMO" class="mode-btn <?= $current_mode == 'DEMO' ? 'active-demo' : 'text-slate-500' ?>">Demo</a>
                <a href="?mode=LIVE" class="mode-btn <?= $current_mode == 'LIVE' ? 'active-live' : 'text-slate-500' ?>">Live</a>
            </div>
            <div class="flex gap-2">
                <a href="master-dashboard.php" class="text-[10px] font-bold text-slate-400 border border-slate-700 px-4 py-2 rounded-lg hover:text-white transition-all uppercase">Back to OS</a>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-10">
            <div class="card flex flex-col justify-center items-center text-center border-l-4 <?= $stats['totalPnL'] >= 0 ? 'border-green-500' : 'border-red-500' ?>">
                <span class="text-[10px] font-black text-slate-500 uppercase mb-2 tracking-widest">Net Profit</span>
                <span class="text-3xl font-black mono <?= $stats['totalPnL'] >= 0 ? 'text-green-400' : 'text-red-500' ?>">
                    <?= number_format($stats['totalPnL'], 2) ?>$
                </span>
                <div class="text-[9px] text-slate-500 mt-1 uppercase font-bold italic">Inc. Funding: <?= number_format($total_funding, 2) ?>$</div>
            </div>
            <div class="card flex flex-col justify-center items-center text-center">
                <span class="text-[10px] font-black text-slate-500 uppercase mb-2 tracking-widest">Win Rate</span>
                <span class="text-3xl font-black mono text-blue-400"><?= $stats['winRate'] ?>%</span>
                <div class="mt-2 text-[10px] text-slate-400 font-bold uppercase tracking-tighter">
                    <?= $stats['wins'] ?>W - <?= $stats['losses'] ?>L - <?= $stats['breakeven'] ?>B
                </div>
            </div>
            <div class="card flex flex-col justify-center items-center text-center">
                <span class="text-[10px] font-black text-slate-500 uppercase mb-2 tracking-widest">Profit Factor</span>
                <span class="text-3xl font-black mono text-orange-400"><?= $stats['profitFactor'] ?></span>
                <div class="mt-2 text-[9px] text-slate-500 font-bold uppercase italic">Avg R:R: 1:<?= $stats['rrRatio'] ?></div>
            </div>
            <div class="card flex justify-center items-center py-2">
                <div style="width: 80px; height: 80px;">
                    <canvas id="winLossChart"></canvas>
                </div>
            </div>
        </div>

        <?php if(!empty($open_orders)): ?>
        <div class="mb-10">
            <h2 class="text-[10px] font-black uppercase text-blue-500 mb-4 tracking-widest flex items-center gap-2">
                <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                Binance Open Orders (<?= count($open_orders) ?>)
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <?php foreach($open_orders as $order): ?>
                <div class="card border-blue-500/20 bg-blue-500/5 p-4 relative overflow-hidden group">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-xs font-black text-white italic"><?= $order['symbol'] ?></span>
                        <span class="text-[9px] font-bold px-2 py-0.5 rounded <?= $order['side'] == 'BUY' ? 'bg-green-500/20 text-green-500' : 'bg-red-500/20 text-red-500' ?>"><?= $order['side'] ?></span>
                    </div>
                    <div class="text-[10px] mono text-slate-400">Price: <span class="text-white"><?= number_format($order['price'], 2) ?></span></div>
                    <div class="text-[10px] mono text-slate-400">Qty: <span class="text-white"><?= $order['quantity'] ?></span></div>
                    <div class="absolute -right-2 -bottom-2 opacity-5 text-4xl font-black italic group-hover:opacity-10 transition-all">ORDER</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="mb-4">
            <h2 class="text-[10px] font-black uppercase text-slate-500 tracking-widest italic">CT-OS Trade Journal</h2>
        </div>
        <div class="card overflow-hidden p-0 border-slate-800 mb-10">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
    <tr class="bg-slate-900/50 border-b border-slate-800 text-[10px] text-slate-500 uppercase font-black tracking-widest">
        <th class="p-4">Date / Dur</th>
       <th class="p-4">Pair</th>
        <th class="p-4 text-center">Reason</th>
        <th class="p-4 text-right">Gross PnL</th>
        <th class="p-4 text-right">Fees</th>
        <th class="p-4 text-right">Net PnL</th>
        <th class="p-4">Setup</th>
        <th class="p-4 text-center">Delete</th>
    </tr>
</thead>
                    <tbody class="divide-y divide-slate-800/50">
                        <?php if(empty($db_logs)): ?>
<tr><td colspan="8" class="p-10 text-center text-slate-600 italic uppercase text-xs font-bold tracking-widest">No <?= $current_mode ?> Trades found.</td></tr>
                        <?php else: ?>
                            <?php foreach($db_logs as $log): ?>
                            <tr class="hover:bg-slate-800/30 transition-colors group">
    <td class="p-4">
        <div class="text-[10px] mono text-slate-400"><?= date('d/m H:i', strtotime($log['closed_at'])) ?></div>
        <div class="text-[9px] text-slate-500 mono"><?= $log['duration_minutes'] ?> min</div>
    </td>
    <td class="p-4">
        <div class="text-xs font-black italic uppercase text-blue-100"><?= htmlspecialchars($log['pair']) ?></div>
        <div class="text-[8px] text-slate-500 mono tracking-tighter">
            In: <?= number_format($log['entry_price_a'] ?? 0, 4) ?> / <?= number_format($log['entry_price_b'] ?? 0, 4) ?>
        </div>
    </td>
    <td class="p-4 text-center">
        <span class="text-[9px] font-bold px-2 py-0.5 rounded <?= ($log['exit_reason'] == 'MANUAL_EXIT') ? 'bg-orange-500/20 text-orange-400' : 'bg-blue-500/20 text-blue-400' ?>">
            <?= htmlspecialchars($log['exit_reason'] ?? 'AUTO') ?>
        </span>
    </td>
    <td class="p-4 text-right text-xs mono font-bold text-slate-300">
        <?= number_format($log['gross_pnl'], 2) ?>$
    </td>
    <td class="p-4 text-right text-[10px] mono text-red-400/80">
        -<?= number_format($log['total_commission'], 2) ?>$
    </td>
    <td class="p-4 text-right">
        <div class="text-xs mono font-black <?= $log['net_pnl'] >= 0 ? 'text-green-400' : 'text-red-500' ?>">
            <?= ($log['net_pnl'] >= 0 ? '+' : '') . number_format($log['net_pnl'], 2) ?>$
        </div>
    </td>
    <td class="p-4 text-[10px] font-bold text-blue-500 uppercase italic">
        <?= htmlspecialchars($log['setup'] ?? 'N/A') ?>
        <div class="text-[8px] text-slate-500 lowercase normal-case"><?= htmlspecialchars($log['notes'] ?? '') ?></div>
    </td>
    <td class="p-4 text-center">
        <button onclick="deleteEntry(<?= $log['id'] ?>)" class="text-slate-700 hover:text-red-500 transition-all transform hover:scale-125">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
        </button>
    </td>
</tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>



        <div class="mb-4">
            <h2 class="text-[10px] font-black uppercase text-emerald-500 tracking-widest flex items-center gap-2 italic">
                <span class="w-2 h-2 bg-emerald-500 rounded-full"></span>
                Account Transactions (Deposits / Withdrawals)
            </h2>
        </div>
        <div class="card overflow-hidden p-0 border-slate-800 mb-10">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-[10px]">
                    <thead>
                        <tr class="bg-slate-900/50 border-b border-slate-800 text-slate-500 uppercase font-black">
                            <th class="p-4">Time</th>
                            <th class="p-4">Asset</th>
                            <th class="p-4">Type</th>
                            <th class="p-4 text-right">Amount</th>
                            <th class="p-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50 mono">
                        <?php if(empty($transactions)): ?>
                            <tr><td colspan="5" class="p-10 text-center text-slate-600 italic uppercase">No Transactions found.</td></tr>
                        <?php else: ?>
                            <?php foreach($transactions as $tr): ?>
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="p-4 text-slate-500"><?= date('d/m H:i', strtotime($tr['timestamp'])) ?></td>
                                <td class="p-4 font-black text-white italic uppercase"><?= $tr['asset'] ?></td>
                                <td class="p-4">
                                    <span class="px-2 py-0.5 rounded font-bold <?= $tr['type'] == 'DEPOSIT' ? 'bg-green-500/20 text-green-500' : 'bg-red-500/20 text-red-500' ?>">
                                        <?= $tr['type'] ?>
                                    </span>
                                </td>
                                <td class="p-4 text-right font-bold text-white"><?= number_format($tr['amount'], 4) ?></td>
                                <td class="p-4 text-center text-slate-500 uppercase font-bold"><?= $tr['status'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div> 

    <script>
    const wins = <?= (int)$stats['wins'] ?>;
    const losses = <?= (int)$stats['losses'] ?>;
    const be = <?= (int)$stats['breakeven'] ?>;
   
    if(wins > 0 || losses > 0 || be > 0) {
        new Chart(document.getElementById('winLossChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['W', 'L', 'B'],
                datasets: [{
                    data: [wins, losses, be],
                    backgroundColor: ['#4ade80', '#f87171', '#475569'],
                    borderColor: '#0f172a',
                    borderWidth: 2
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                cutout: '70%',
                responsive: true
            }
        });
    }

    async function deleteEntry(id) {
        if(!confirm("Delete this entry?")) return;
        const formData = new FormData();
        formData.append('delete_id', id);
        formData.append('target', 'active');
        const res = await fetch('delete-entry.php', { method: 'POST', body: formData });
        if(res.ok) location.reload();
    }
    </script>
</body>
</html>