<?php
/**
 * CT-OS | copyright by cryptoteam.gr - api_monitor.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); 

header('Content-Type: application/json');
session_start();

require_once 'db_config.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized Access']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // 0. Ταχύτητα SQL
    $pdo->query("SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");

    // 1. ΑΝΑΚΤΗΣΗ ΣΤΑΤΙΣΤΙΚΩΝ ΛΟΓΑΡΙΑΣΜΟΥ
    $stmtUser = $pdo->prepare("SELECT last_balance, last_equity, last_maint_margin FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute([$user_id]);
    $uData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    // 2. ΕΛΕΓΧΟΣ CACHE ΤΙΜΩΝ
    $cacheFile = __DIR__ . '/prices_cache.json';
    $live_prices = [];
    $is_stale = false;
    $last_sync_time = 0;

    if (file_exists($cacheFile)) {
        $last_sync_time = filemtime($cacheFile);
        if ((time() - $last_sync_time) > 35) { $is_stale = true; }
        
        $json_raw = @file_get_contents($cacheFile);
        $live_prices = json_decode($json_raw, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || empty($live_prices)) {
            usleep(150000); 
            $live_prices = json_decode(@file_get_contents($cacheFile), true) ?: [];
        }
    }

    // 3. ΛΗΨΗ TRADES
    $stmt = $pdo->prepare("
        SELECT 
            ap.*, 
            u.tp_dollar as master_tp,
            u.sl_dollar as master_sl,
            pu.last_z_score as univ_z,
            pu.last_beta as univ_beta
        FROM active_pairs ap
        JOIN users u ON ap.user_id = u.id
        LEFT JOIN pair_universe pu ON (
            (ap.asset_a = pu.asset_a AND ap.asset_b = pu.asset_b) OR 
            (ap.asset_a = pu.asset_b AND ap.asset_b = pu.asset_a)
        )
        WHERE ap.user_id = :uid AND ap.status = 'OPEN' 
        ORDER BY ap.created_at DESC
    ");
    $stmt->execute([':uid' => $user_id]);
    $active_trades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_upnl = 0;
    $trades_output = [];

    foreach ($active_trades as $trade) {
        $symbolA = strtoupper(trim($trade['asset_a'])) . "USDT";
        $symbolB = strtoupper(trim($trade['asset_b'])) . "USDT";
        
        $currentPriceA = $live_prices[$symbolA] ?? (float)($trade['entry_price_a']);
        $currentPriceB = $live_prices[$symbolB] ?? (float)($trade['entry_price_b']);

        $qtyA = abs((float)$trade['quantity_a']);
        $qtyB = abs((float)$trade['quantity_b']);
        $entA = (float)$trade['entry_price_a'];
        $entB = (float)$trade['entry_price_b'];

        // 4. ΥΠΟΛΟΓΙΣΜΟΣ P&L
        $pnlA = ($trade['side_a'] === 'BUY') ? ($currentPriceA - $entA) * $qtyA : ($entA - $currentPriceA) * $qtyA;
        $pnlB = ($trade['side_b'] === 'BUY') ? ($currentPriceB - $entB) * $qtyB : ($entB - $currentPriceB) * $qtyB;
        
        $gross_pnl = $pnlA + $pnlB;
        $entryFees = (float)($trade['commission_a'] ?? 0) + (float)($trade['commission_b'] ?? 0);
        $net_pnl = $gross_pnl - $entryFees;
        $total_upnl += $net_pnl;

        $current_z = ($trade['univ_z'] !== null) ? (float)$trade['univ_z'] : (float)$trade['entry_z_score'];

        $trades_output[] = [
            'id'             => (int)$trade['id'],
            'pair_label'     => $trade['asset_a'] . " / " . $trade['asset_b'],
            'price_a'        => $currentPriceA,
            'price_b'        => $currentPriceB,
            'entry_price_a'  => $entA,
            'entry_price_b'  => $entB,
            'side_a'         => $trade['side_a'], // BUY or SELL
            'side_b'         => $trade['side_b'],
            'current_z'      => round($current_z, 2), 
            'beta'           => round((float)($trade['univ_beta'] ?? $trade['beta_used']), 2),
            'gross_pnl'      => (float)round($gross_pnl, 2),
            'net_pnl'        => (float)round($net_pnl, 2),
            'fees'           => (float)round($entryFees, 2),
            'created_at'     => date('d/m H:i', strtotime($trade['created_at']))
        ];
    }

    echo json_encode([
        'system_status' => [
            'is_stale' => $is_stale,
            'last_sync' => date('H:i:s', $last_sync_time)
        ],
        'account' => [
            'equity'       => (float)($uData['last_equity'] ?? 0),
            'balance'      => (float)($uData['last_balance'] ?? 0),
            'total_upnl'   => (float)round($total_upnl, 2),
            'margin_ratio' => ($uData['last_equity'] > 0) ? round(($uData['last_maint_margin'] / $uData['last_equity']) * 100, 2) : 0
        ],
        'trades' => $trades_output
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Monitor Sync Failed', 'details' => $e->getMessage()]);
}