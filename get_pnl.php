<?php
/**
 * CT-OS | copyright by cryptoteam.gr - get_pnl.php
 * ----------------------------------------------------------------
 * Σκοπός: Πλήρης υπολογισμός PnL και Equity για το Dashboard.
 * UPDATED: Real-time PnL calculation από τιμές cache (Zero-Lag Edition).
 */

session_start();
require_once 'db_config.php';
require_once 'functions.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$mode = isset($_GET['mode']) ? strtoupper($_GET['mode']) : 'LIVE';

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized Access']);
    exit;
}

try {
    // 1. ΑΝΑΚΤΗΣΗ ΣΤΑΤΙΣΤΙΚΩΝ ΧΡΗΣΤΗ
    $stmtUser = $pdo->prepare("SELECT last_balance, last_equity, last_maint_margin FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute([$user_id]);
    $uData = $stmtUser->fetch(PDO::FETCH_ASSOC);

    $total_wallet_balance = (float)($uData['last_balance'] ?? 0);
    $total_margin_balance = (float)($uData['last_equity'] ?? 0);
    $maint_margin         = (float)($uData['last_maint_margin'] ?? 0);

    // 0. ΦΟΡΤΩΣΗ ΤΡΕΧΟΥΣΩΝ ΤΙΜΩΝ ΓΙΑ REAL-TIME PNL
    $cacheFile = __DIR__ . '/prices_cache.json';
    $ticker_prices = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];

    // 2. ΛΗΨΗ ΑΝΟΙΧΤΩΝ ΘΕΣΕΩΝ
    $stmtPos = $pdo->prepare("
        SELECT id, asset_a, asset_b, side_a, side_b, quantity_a, quantity_b, 
               entry_price_a, entry_price_b, entry_z_score, beta_used, status, 
               commission_a, commission_b, leverage_used
        FROM active_pairs 
        WHERE user_id = ? AND status = 'OPEN' AND mode = ?
    ");
    $stmtPos->execute([$user_id, $mode]);
    $db_positions = $stmtPos->fetchAll(PDO::FETCH_ASSOC);

    $running_net_pnl = 0.0;
    $output_positions = [];

    foreach ($db_positions as $pos) {
        $cleanA = strtoupper(trim($pos['asset_a']));
        $cleanB = strtoupper(trim($pos['asset_b']));
        $symA = $cleanA . "USDT";
        $symB = $cleanB . "USDT";

        // Λήψη τρέχουσας τιμής από το Cache ή fallback στην τιμή εισόδου
        $currentPriceA = isset($ticker_prices[$symA]) ? (float)$ticker_prices[$symA] : (float)$pos['entry_price_a'];
        $currentPriceB = isset($ticker_prices[$symB]) ? (float)$ticker_prices[$symB] : (float)$pos['entry_price_b'];

        // --- REAL-TIME PNL CALCULATION ---
        $leverage_used = (int)($pos['leverage_used'] ?? 5);
        $pnlA = (strtoupper($pos['side_a']) === 'BUY') 
            ? ($currentPriceA - (float)$pos['entry_price_a']) * (float)$pos['quantity_a']
            : ((float)$pos['entry_price_a'] - $currentPriceA) * (float)$pos['quantity_a'];

        $pnlB = (strtoupper($pos['side_b']) === 'BUY') 
            ? ($currentPriceB - (float)$pos['entry_price_b']) * (float)$pos['quantity_b']
            : ((float)$pos['entry_price_b'] - $currentPriceB) * (float)$pos['quantity_b'];
        
        // Divide by leverage since quantities are already leverage-multiplied
        $pnlA = $pnlA / $leverage_used;
        $pnlB = $pnlB / $leverage_used;

        // Αφαίρεση προμηθειών από το PnL της θέσης
        $entryFees = (float)($pos['commission_a'] ?? 0) + (float)($pos['commission_b'] ?? 0);
        $pnl_total = ($pnlA + $pnlB) - $entryFees;
        
        $running_net_pnl += $pnl_total;

        // Λήψη Z-Score & Beta από το Universe για το UI
        $stmtZ = $pdo->prepare("SELECT last_z_score, last_beta, last_update FROM pair_universe WHERE (asset_a = ? AND asset_b = ?) OR (asset_a = ? AND asset_b = ?) LIMIT 1");
        $stmtZ->execute([$cleanA, $cleanB, $cleanB, $cleanA]);
        $market_data = $stmtZ->fetch(PDO::FETCH_ASSOC);
        
        $live_z = (float)($market_data['last_z_score'] ?? $pos['entry_z_score']);

        // Υπολογισμός τρέχουσας αξίας σε $ για τις μπάρες (Beta Weighting) με κεντρικές συναρτήσεις
        $beta_weighting = calculateBetaWeighting($pos['quantity_a'], $pos['quantity_b'], $currentPriceA, $currentPriceB, $pos['leverage_used']);
        $val_a = $beta_weighting['val_a'];
        $val_b = $beta_weighting['val_b'];

        $output_positions[] = [
            'id'              => (int)$pos['id'],
            'asset_a'         => $cleanA,
            'asset_b'         => $cleanB,
            'side_a'          => $pos['side_a'],
            'side_b'          => $pos['side_b'],
            'quantity_a'      => (float)$pos['quantity_a'],
            'quantity_b'      => (float)$pos['quantity_b'],
            'val_a'           => $val_a, 
            'val_b'           => $val_b, 
            'entry_price_a'   => (float)$pos['entry_price_a'],
            'entry_price_b'   => (float)$pos['entry_price_b'],
            
            // --- ΝΕΕΣ ΠΡΟΣΘΗΚΕΣ ΓΙΑ ΤΟ LIVE RATIO ---
            'current_price_a' => (float)$currentPriceA,
            'current_price_b' => (float)$currentPriceB,
            // ---------------------------------------

            'entry_z_score'   => round((float)$pos['entry_z_score'], 2),
            'current_z'       => round($live_z, 2),
            'beta'            => round((float)($market_data['last_beta'] ?? $pos['beta_used']), 2),
            'last_seen'       => isset($market_data['last_update']) ? date('H:i:s', strtotime($market_data['last_update'])) : 'N/A',
            'pnl'             => (float)round($pnl_total, 2),
            'commission_a'    => (float)$pos['commission_a'],
            'commission_b'    => (float)$pos['commission_b'],
            'status'          => $pos['status']
        ];
    }

    // 3. ΤΕΛΙΚΟ JSON
    $res = [
        'success'      => true,
        'balance'      => (float)round($total_wallet_balance, 2),
        'available'    => (float)round($total_wallet_balance, 2), 
        'equity'       => (float)round($total_margin_balance, 2),
        'total_upnl'   => (float)round($running_net_pnl, 2),
        'upnl_percent' => ($total_wallet_balance > 0) ? (float)round(($running_net_pnl / $total_wallet_balance) * 100, 2) : 0.00,
        'margin_ratio' => ($total_margin_balance > 0) ? (float)round(($maint_margin / $total_margin_balance) * 100, 2) : 0.00,
        'positions'    => $output_positions,
        'server_time'  => date('H:i:s')
    ];

    if (ob_get_length()) ob_clean();
    echo json_encode($res);

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;