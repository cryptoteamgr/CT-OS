<?php
/**
 * CT-OS | copyright by cryptoteam.gr - bot_engine.php
 * ----------------------------------------------------------------
 * Σκοπός: Η κεντρική μηχανή εκτέλεσης εντολών (Execution Engine) για το άνοιγμα και κλείσιμο θέσεων στην Binance.
 */

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') { 
    session_start(); 
}

require_once 'db_config.php';
require_once 'functions.php'; 

$is_cli = (php_sapi_name() === 'cli');

$action = '';
$mode   = 'DEMO';
$symbol = '';
$data   = [];
$user_id = null;
$k = ''; 
$s = ''; 

if (!$is_cli) {
    header('Content-Type: application/json');
    $user_id = $_SESSION['user_id'] ?? null;
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Session Expired. Please Login.']);
        exit;
    }

    $action = $data['action'] ?? '';
    $mode   = $data['mode'] ?? 'DEMO';
    $symbol = strtoupper(trim($data['symbol'] ?? ''));

} else {
    $action = $argv[1] ?? 'monitor'; 
    $symbol = strtoupper(trim($argv[2] ?? ''));
    $mode   = strtoupper(trim($argv[3] ?? 'DEMO'));
    // Στο CLI mode, το user_id πρέπει να περαστεί ως 4ο επιχείρημα αν απαιτείται
    $user_id = isset($argv[4]) ? (int)$argv[4] : null;
}

try {
    if (empty($action)) {
        throw new Exception("No action specified.");
    }

    if ($user_id) {
        $stmtAPI = $pdo->prepare("SELECT api_key, api_secret FROM api_keys WHERE user_id = ? AND account_type = ? AND is_active = 1 LIMIT 1");
        $stmtAPI->execute([$user_id, $mode]);
        $api = $stmtAPI->fetch();

        if ($api) {
            $k = decrypt_data($api['api_key']);
            $s = decrypt_data($api['api_secret']);
        }
    }

    // --- ACTION: OPEN POSITION ---
    if ($action === 'open_position' && !$is_cli) {
        if (empty($symbol)) throw new Exception("Symbol is required.");
        if (!str_ends_with($symbol, 'USDT')) $symbol .= 'USDT';

        $side = strtoupper($data['side'] ?? 'BUY'); 
        $cost = floatval($data['cost'] ?? 10);
        $leverage = intval($data['leverage'] ?? 20);
        $posSide = ($side === 'BUY') ? 'LONG' : 'SHORT';

        // 1. REAL-TIME WALLET CHECK ΠΡΙΝ ΤΗΝ ΕΚΤΕΛΕΣΗ
        $accountInfo = getBinanceAccountInfo($k, $s, $mode);
        $real_wallet_balance = ($accountInfo !== null) ? $accountInfo['balance'] : 0;

        // 2. ΕΛΕΓΧΟΣ ΔΙΑΘΕΣΙΜΟΤΗΤΑΣ
        if ($real_wallet_balance <= 0) {
            echo json_encode(['success' => false, 'message' => "Insufficient Funds: Your Binance Wallet is empty ($0)."]);
            exit;
        }

        if ($cost > $real_wallet_balance) {
            echo json_encode(['success' => false, 'message' => "Insufficient Margin: You requested $$cost but you only have $$real_wallet_balance available."]);
            exit;
        }

        // 3. ΕΚΤΕΛΕΣΗ ΕΝΤΟΛΗΣ (Αφού περάσει τον έλεγχο)
        $trade = binance_market_order($k, $s, $symbol, $side, $leverage, $cost, false, $posSide, $mode, $user_id);
        
        if ($trade['success']) {
            // 1. Καταγραφή στην ιστορία με τα νέα πεδία (Order ID & Commission)
            $stmt = $pdo->prepare("INSERT INTO trades_history (user_id, symbol, side, qty, entry_price, binance_order_id, commission, mode, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->execute([
                $user_id, 
                $symbol, 
                $side, 
                $trade['qty'], 
                $trade['price'], 
                $trade['orderId'] ?? null, 
                $trade['commission'] ?? 0, 
                $mode
            ]);

            // 2. Εμπλουτισμένο JSON Response για το UI
            echo json_encode([
                'success' => true, 
                'message' => "SUCCESS: $side $symbol | Qty: {$trade['qty']} | Fee: " . number_format(($trade['commission'] ?? 0), 4) . " USDT",
                'details' => [
                    'orderId' => $trade['orderId'] ?? '',
                    'fee' => $trade['commission'] ?? 0
                ]
            ]);
        } else {
    $err_msg = "Binance Error: " . ($trade['msg'] ?? 'Unknown Error');
    if (function_exists('tlog')) tlog("❌ EXECUTION FAILED for $symbol: " . $err_msg);
    echo json_encode(['success' => false, 'message' => $err_msg]);
}
        exit;
    }
    // --- ACTION: CLOSE POSITION (STAT-ARB READY) ---
    if ($action === 'close_position') {
        if (empty($symbol)) throw new Exception("Symbol is required.");
        if (!str_ends_with($symbol, 'USDT')) $symbol .= 'USDT';

        $posSide = strtoupper($data['side'] ?? 'LONG'); 
        
        // 1. Λήψη θέσης από Binance
        usleep(300000); 
        $posData = binance_get_position($k, $s, $symbol, $mode);
        $qty = abs(floatval($posData['quantity'] ?? 0));

        if ($qty <= 0) {
            throw new Exception("Position for $symbol already closed on Binance.");
        }

        $closeSide = ($posSide === 'LONG') ? 'SELL' : 'BUY';
        $reason = $data['reason'] ?? 'AUTO CLOSE'; 

        // 2. Εκτέλεση Market Order
        $trade = binance_market_order($k, $s, $symbol, $closeSide, 0, $qty, true, $posSide, $mode, $user_id, $reason);
        
        if ($trade['success']) {
            $cleanSym = str_replace('USDT', '', $symbol);
            $exitPrice = $trade['price'] ?? 0;
            $exitComm  = $trade['commission'] ?? 0;

            // 3. ΕΝΗΜΕΡΩΣΗ ΒΑΣΗΣ (Smart Split A/B)
            // Ενημερώνουμε μόνο το σκέλος που έκλεισε. 
            // Το status γίνεται 'CLOSED' ΜΟΝΟ αν και τα δύο σκέλη έχουν πλέον exit_price.
            $updateDB = $pdo->prepare("
                UPDATE active_pairs 
                SET 
                    exit_price_a = CASE WHEN asset_a = ? THEN ? ELSE exit_price_a END,
                    exit_price_b = CASE WHEN asset_b = ? THEN ? ELSE exit_price_b END,
                    commission_a = CASE WHEN asset_a = ? THEN (commission_a + ?) ELSE commission_a END,
                    commission_b = CASE WHEN asset_b = ? THEN (commission_b + ?) ELSE commission_b END,
                    status = CASE 
                        WHEN (asset_a = ? AND exit_price_b > 0) OR (asset_b = ? AND exit_price_a > 0) 
                        THEN 'CLOSED' ELSE 'OPEN' END,
                    closed_at = CASE 
                        WHEN (asset_a = ? AND exit_price_b > 0) OR (asset_b = ? AND exit_price_a > 0) 
                        THEN NOW() ELSE NULL END
                WHERE (asset_a = ? OR asset_b = ?) 
                AND user_id = ? 
                AND status IN ('OPEN', 'CLOSING')
            ");
            
            $updateDB->execute([
                $cleanSym, $exitPrice, 
                $cleanSym, $exitPrice,
                $cleanSym, $exitComm,
                $cleanSym, $exitComm,
                $cleanSym, $cleanSym, // Για το status check
                $cleanSym, $cleanSym, // Για το closed_at check
                $cleanSym, $cleanSym, 
                $user_id
            ]);

            echo json_encode([
                'success' => true, 
                'message' => "CLOSED: $symbol | Price: $exitPrice",
                'data' => ['price' => $exitPrice, 'commission' => $exitComm]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => "Binance Error: " . ($trade['msg'] ?? 'Unknown')]);
        }
        exit;
    }

    if (!$is_cli) {
        throw new Exception("Invalid Engine Action.");
    }

} // Τέλος του Try
catch (Exception $e) {
    if (!$is_cli) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        echo "Engine Error: " . $e->getMessage() . "\n";
    }
}