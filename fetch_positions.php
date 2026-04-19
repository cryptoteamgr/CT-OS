<?php
/**
 * CT-OS | copyright by cryptoteam.gr - fetch_positions.php (Turbo SQL Edition)
 */
session_start();
$user_id = $_SESSION['user_id'] ?? null;
session_write_close(); 

require_once 'db_config.php';
require_once 'functions.php';

if (!$user_id) {
    exit("<tr><td colspan='11' class='p-5 text-center text-red-500'>Unauthorized access.</td></tr>");
}

$mode = (isset($_GET['mode']) && $_GET['mode'] === 'LIVE') ? 'LIVE' : 'DEMO';

try {
    // 1. Γρήγορο Query στη βάση αντί για CURL στη Binance
    $pdo->query("SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");
    
    // Φέρνουμε τις ανοιχτές θέσεις του χρήστη από τον πίνακα active_pairs
    $stmt = $pdo->prepare("SELECT * FROM active_pairs WHERE user_id = ? AND mode = ? AND status = 'OPEN' ORDER BY created_at DESC");
    $stmt->execute([$user_id, $mode]);
    $open_trades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Λήψη τρεχουσών τιμών από το cache για υπολογισμό PnL
    $cacheFile = __DIR__ . '/prices_cache.json';
    $prices = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];

    if (empty($open_trades)): ?>
        <tr>
            <td colspan="11" class="p-16 text-center text-slate-600 text-xs italic uppercase font-black tracking-[0.2em]">
                <div class="flex flex-col items-center gap-2">
                    <span class="text-2xl opacity-20">??</span>
                    No active <?= $mode ?> positions detected in database
                </div>
            </td>
        </tr>
    <?php else: 
         
        foreach($open_trades as $t): 
    // Τραβάμε το Beta και το χρόνο από το Universe
    $stmtB = $pdo->prepare("SELECT last_beta, last_update FROM pair_universe WHERE (asset_a = ? AND asset_b = ?) OR (asset_a = ? AND asset_b = ?) LIMIT 1");
    $stmtB->execute([$t['asset_a'], $t['asset_b'], $t['asset_b'], $t['asset_a']]);
    $m_data = $stmtB->fetch();
    
    $beta = round((float)($m_data['last_beta'] ?? 1.0), 2);
    $time = isset($m_data['last_update']) ? date('H:i:s', strtotime($m_data['last_update'])) : '--:--';

    // Στέλνουμε τις τιμές στη συνάρτηση
    renderTradeRow($t['asset_a'], $t['side_a'], $t['quantity_a'], $t['entry_price_a'], $t['leverage'], $prices, $mode, $beta, $time);
    renderTradeRow($t['asset_b'], $t['side_b'], $t['quantity_b'], $t['entry_price_b'], $t['leverage'], $prices, $mode, $beta, $time);
endforeach;
    endif;

} catch (Exception $e) {
    echo "<tr><td colspan='11' class='p-5 text-center text-red-500'>Error: " . $e->getMessage() . "</td></tr>";
}

// Συνάρτηση για να σχεδιάζει τις γραμμές (Helper)
function renderTradeRow($asset, $side, $qty, $entry, $lev, $prices, $mode, $beta = 1.0, $time = '--:--') {
    $sym = $asset . "USDT";
    $mark = $prices[$sym] ?? $entry;
    $pnl = (strtoupper($side) === 'BUY') ? ($mark - $entry) * $qty : ($entry - $mark) * $qty;
    $pnlColor = $pnl >= 0 ? 'text-emerald-400' : 'text-red-500';
    ?>
    <tr class="hover:bg-white/[0.04] transition-colors border-b border-white/5 group">
        <td class="p-4">
            <div class="flex flex-col cursor-pointer">
                <span class="font-black text-white italic">
                    <?= $sym ?> <span class="text-blue-400 text-[10px] ml-1">β:<?= $beta ?></span>
                </span>
                <span class="<?= strtoupper($side) === 'BUY' ? 'text-emerald-500' : 'text-red-500' ?> text-[9px] font-black uppercase">
                    <?= $lev ?>x . <?= strtoupper($side) === 'BUY' ? 'LONG' : 'SHORT' ?> . <span class="text-slate-500"><?= $time ?></span>
                </span>
            </div>
        </td>
        <td class="p-3 mono text-xs text-slate-300 font-bold"><?= $qty ?></td>
        <td class="p-3 mono text-xs text-slate-500">$<?= number_format($entry, 4) ?></td>
        <td class="p-3 mono text-xs font-black text-white">$<?= number_format($mark, 4) ?></td>
        <td class="p-3 <?= $pnlColor ?> font-black mono text-sm">$<?= number_format($pnl, 2) ?></td>
        <td class="p-4 text-right">
            <button onclick="closePosition('<?= $sym ?>', '<?= $side ?>')" class="bg-red-500/10 text-red-500 border border-red-500/30 px-4 py-1.5 rounded text-[10px] font-black uppercase italic tracking-tighter">EXIT</button>
        </td>
    </tr>
    <?php
}