<?php
/**
 * CT-OS | api_close_pair.php - FINAL VERSION (NO SLIPPAGE - FIXED JOURNAL)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start(); 

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'User';

if (!$user_id) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit;
}

require_once 'db_config.php';
require_once 'functions.php';

$data = json_decode(file_get_contents('php://input'), true);
$pair_id = isset($data['pair_id']) ? (int)$data['pair_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : null);

// DEBUG: Log script start
file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " Script started. Pair ID: $pair_id\n", FILE_APPEND);

if (!$pair_id) {
    file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " ERROR: Missing Pair ID\n", FILE_APPEND);
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Missing Pair ID']);
    exit;
}

try {
    file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " Trade $pair_id: Starting DB query\n", FILE_APPEND);
    
    $stmt = $pdo->prepare("SELECT * FROM active_pairs WHERE id = ? AND user_id = ? AND status = 'OPEN' LIMIT 1");
    $stmt->execute([$pair_id, $user_id]);
    $pair = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pair) {
        file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " Trade $pair_id: Trade not found\n", FILE_APPEND);
        throw new Exception('Trade not found.');
    }

    file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " Trade $pair_id: Found trade, updating to CLOSING\n", FILE_APPEND);
    
    $pdo->prepare("UPDATE active_pairs SET status = 'CLOSING' WHERE id = ?")->execute([$pair_id]);

    $symbolA = strtoupper(trim($pair['asset_a'])) . "USDT";
    $symbolB = strtoupper(trim($pair['asset_b'])) . "USDT";
    $mode    = strtoupper($pair['mode'] ?? 'DEMO');

    file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " Trade $pair_id: Getting API keys\n", FILE_APPEND);
    
    $stmtK = $pdo->prepare("SELECT api_key, api_secret FROM api_keys WHERE user_id = ? AND account_type = ? AND is_active = 1 LIMIT 1");
    $stmtK->execute([$user_id, $mode]);
    $ak = $stmtK->fetch();
    if (!$ak) throw new Exception('API Keys missing');

    $fK = decrypt_data($ak['api_key']);
    $fS = decrypt_data($ak['api_secret']);

    file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " Trade $pair_id: Getting positions\n", FILE_APPEND);
    
    $posA = binance_get_position($fK, $fS, $symbolA, $mode);
    $qtyA = abs(floatval($posA['quantity'] ?? 0));
    $posB = binance_get_position($fK, $fS, $symbolB, $mode);
    $qtyB = abs(floatval($posB['quantity'] ?? 0));

    file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " Trade $pair_id: Positions - QtyA=$qtyA, QtyB=$qtyB\n", FILE_APPEND);

    if ($qtyA <= 0 && $qtyB <= 0) {
        $pdo->prepare("UPDATE active_pairs SET status = 'CLOSED', closed_at = NOW(), notes = 'Sync Close' WHERE id = ?")->execute([$pair_id]);
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Already closed']);
        exit;
    }

    $sideA = ($pair['side_a'] === 'BUY') ? 'SELL' : 'BUY';
    $sideB = ($pair['side_b'] === 'BUY') ? 'SELL' : 'BUY';

    // Precision Fix
    $exCache = json_decode(@file_get_contents(__DIR__ . '/exchange_info_cache.json'), true);
    $stepA = 1; $stepB = 1; 
    if (isset($exCache['symbols'])) {
        foreach ($exCache['symbols'] as $s) {
            if ($s['symbol'] === $symbolA) { foreach ($s['filters'] as $f) { if ($f['filterType'] === 'LOT_SIZE') $stepA = $f['stepSize']; } }
            if ($s['symbol'] === $symbolB) { foreach ($s['filters'] as $f) { if ($f['filterType'] === 'LOT_SIZE') $stepB = $f['stepSize']; } }
        }
    }
    $qtyA = round_step($qtyA, $stepA); 
    $qtyB = round_step($qtyB, $stepB);

    file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " Trade $pair_id: About to execute orders. SideA=$sideA, SideB=$sideB, QtyA=$qtyA, QtyB=$qtyB\n", FILE_APPEND);

    // Execution
    $resA = binance_market_order($fK, $fS, $symbolA, $sideA, 0, $qtyA, true, ($pair['side_a'] === 'BUY' ? 'LONG' : 'SHORT'), $mode, $user_id, 'MANUAL_EXIT');
    
    file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " Trade $pair_id: Order A executed. Success=" . ($resA['success'] ? 'true' : 'false') . "\n", FILE_APPEND);
    
    usleep(250000);
    $resB = binance_market_order($fK, $fS, $symbolB, $sideB, 0, $qtyB, true, ($pair['side_b'] === 'BUY' ? 'LONG' : 'SHORT'), $mode, $user_id, 'MANUAL_EXIT');
    
    file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " Trade $pair_id: Order B executed. Success=" . ($resB['success'] ? 'true' : 'false') . "\n", FILE_APPEND);

    if ($resA['success'] && $resB['success']) {
        file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " Trade $pair_id: Both orders succeeded, starting PnL calculation\n", FILE_APPEND);
        
        $exitA = floatval($resA['price'] > 0 ? $resA['price'] : $pair['current_price_a']);
        $exitB = floatval($resB['price'] > 0 ? $resB['price'] : $pair['current_price_b']);
        
        file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " Trade $pair_id: Exit prices - A=$exitA, B=$exitB\n", FILE_APPEND);
        
        // 4-WAY FEES CALCULATION
        $entryFees = floatval($pair['commission_a']) + floatval($pair['commission_b']);
        $exitFees  = floatval($resA['commission'] ?? 0) + floatval($resB['commission'] ?? 0);
        $totalComm = $entryFees + $exitFees;

        $pnlA = ($exitA - (float)$pair['entry_price_a']) * $qtyA * ($pair['side_a'] === 'BUY' ? 1 : -1);
        $pnlB = ($exitB - (float)$pair['entry_price_b']) * $qtyB * ($pair['side_b'] === 'BUY' ? 1 : -1);
        
        // Get user leverage
        $levStmt = $pdo->prepare("SELECT leverage FROM users WHERE id = ? LIMIT 1");
        $levStmt->execute([$user_id]);
        $user_lev = $levStmt->fetchColumn() ?: 5;
        
        $grossPnl = ($pnlA + $pnlB) / $user_lev;
        $netPnl = $grossPnl - $totalComm;
        $durMin = round((time() - strtotime($pair['created_at'])) / 60);
        
        file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " Trade $pair_id: PnL calculated - Gross=$grossPnl, Net=$netPnl\n", FILE_APPEND);

        // DEBUG: Log success
        file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " Trade $pair_id: Orders succeeded. ExitA=$exitA, ExitB=$exitB, GrossPnL=$grossPnl\n", FILE_APPEND);

        // 1. UPDATE ACTIVE_PAIRS
        $pdo->prepare("UPDATE active_pairs SET status = 'CLOSED', closed_at = NOW(), exit_price_a = ?, exit_price_b = ?, final_pnl = ?, notes = 'Manual Close' WHERE id = ?")
            ->execute([$exitA, $exitB, round($grossPnl, 4), $pair_id]);

        // 2. INSERT TO JOURNAL (FIXED: 13 Fields - Removed Slippage & 1 Placeholder)
        $sqlJournal = "INSERT INTO zEQZkBci_trade_journal 
            (user_id, pair, account_type, 
             entry_price_a, entry_price_b, exit_price_a, exit_price_b, 
             gross_pnl, total_commission, net_pnl,
             duration_minutes, entry_z_score, exit_reason, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $pdo->prepare($sqlJournal)->execute([
            $user_id, 
            $pair['asset_a']."/".$pair['asset_b'], 
            $mode,
            $pair['entry_price_a'], 
            $pair['entry_price_b'], 
            $exitA, 
            $exitB,
            round($grossPnl, 4), 
            round($totalComm, 4), 
            round($netPnl, 4),
            (int)$durMin,
            (float)($pair['entry_z_score'] ?? 0),
            'MANUAL_EXIT'
        ]);

        // 3. TELEGRAM NOTIFICATION (4-WAY FEES)
        $emoji = ($netPnl >= 0) ? "✅" : "🔻";
        $mode_label = ($mode === 'LIVE') ? "🔵 <b>LIVE</b>" : "🟡 <b>DEMO</b>";
        
        $msg = "👤 {$emoji} <b>MANUAL EXIT COMPLETED</b>\n";
        $msg .= "📊 Pair: <code>{$pair['asset_a']}/{$pair['asset_b']}</code>\n";
        $msg .= "🏁 Reason: <b>USER INTERVENTION</b>\n";
        $msg .= "💰 GROSS PnL: <b>$" . number_format($grossPnl, 2) . "</b>\n";
        $msg .= "⛽ Total Fees (4x): <code>$" . number_format($totalComm, 4) . " USDT</code>\n";
        $msg .= "💵 <b>NET PnL: " . ($netPnl >= 0 ? "+" : "") . "$" . number_format($netPnl, 2) . "</b>\n";
        $msg .= "------------------------\n";
        $msg .= "👤 User: $username | {$mode_label}";
        
        sendTelegramNotification($msg, $user_id);

        ob_end_clean(); 
        echo json_encode(['success' => true, 'net_pnl' => round($netPnl, 2)]);
    } else {
        throw new Exception("Binance Error during execution.");
    }
} catch (Exception $e) {
    // DEBUG: Log exception
    file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " Trade $pair_id: Exception caught - " . $e->getMessage() . "\n", FILE_APPEND);
    
    if (isset($pdo) && isset($pair_id)) {
        // Check if positions are actually closed in Binance
        $stmtCheck = $pdo->prepare("SELECT asset_a, asset_b, mode FROM active_pairs WHERE id = ?");
        $stmtCheck->execute([$pair_id]);
        $tradeCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($tradeCheck) {
            $stmtK = $pdo->prepare("SELECT api_key, api_secret FROM api_keys WHERE user_id = ? AND account_type = ? AND is_active = 1 LIMIT 1");
            $stmtK->execute([$user_id, $tradeCheck['mode']]);
            $keys = $stmtK->fetch();
            
            if ($keys) {
                $fK = decrypt_data($keys['api_key']);
                $fS = decrypt_data($keys['api_secret']);
                
                $symA = strtoupper($tradeCheck['asset_a']) . "USDT";
                $symB = strtoupper($tradeCheck['asset_b']) . "USDT";
                
                $posA = binance_get_position($fK, $fS, $symA, $tradeCheck['mode']);
                $posB = binance_get_position($fK, $fS, $symB, $tradeCheck['mode']);
                
                $qtyA = abs(floatval($posA['quantity'] ?? 0));
                $qtyB = abs(floatval($posB['quantity'] ?? 0));
                
                // DEBUG: Log position check
                file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " Trade $pair_id: Position check - QtyA=$qtyA, QtyB=$qtyB\n", FILE_APPEND);
                
                // If positions are actually closed, try to complete the close
                if ($qtyA <= 0 && $qtyB <= 0) {
                    // Positions are closed - mark as CLOSED with error note
                    file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " Trade $pair_id: Positions closed, marking as CLOSED\n", FILE_APPEND);
                    $pdo->prepare("UPDATE active_pairs SET status = 'CLOSED', closed_at = NOW(), notes = CONCAT(COALESCE(notes, ''), ' | Close Error: " . addslashes($e->getMessage()) . "') WHERE id = ?")->execute([$pair_id]);
                    ob_end_clean();
                    echo json_encode(['success' => false, 'message' => 'Positions closed but DB update failed: ' . $e->getMessage()]);
                    exit;
                }
            }
        }
        
        // If positions are still open, rollback to OPEN
        file_put_contents(__DIR__ . '/debug_close.log', date('Y-m-d H:i:s') . " Trade $pair_id: Positions still open, rolling back to OPEN\n", FILE_APPEND);
        $pdo->prepare("UPDATE active_pairs SET status = 'OPEN' WHERE id = ?")->execute([$pair_id]);
    }
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>