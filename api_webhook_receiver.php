<?php
/**
 * CT-OS | copyright by cryptoteam.gr - webhook_receiver.php
 * ----------------------------------------------------------------
 * Σκοπός: Αυτοματοποιημένο κλείσιμο trades μέσω εξωτερικών σημάτων (π.χ. TradingView Alerts).
 */

require_once 'db_config.php';
require_once 'functions.php';

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// 1. Webhook Security Key (Άλλαξέ το με ένα δικό σου password)
$webhook_key = "MY_SECRET_KEY_123";

if (!$data || !isset($data['key']) || $data['key'] !== $webhook_key) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit;
}

$pair_id       = isset($data['pair_id']) ? (int)$data['pair_id'] : null;
$exit_price_a  = $data['exit_price_a'] ?? 0;
$exit_price_b  = $data['exit_price_b'] ?? 0;
$final_pnl     = $data['final_pnl'] ?? 0;

if (!$pair_id) {
    echo json_encode(["success" => false, "message" => "Missing Pair ID."]);
    exit;
}

try {
    // 2. Εύρεση του trade και των δεδομένων προμήθειας πριν το κλείσιμο
    // Προσθέτουμε τα πεδία commission_a και commission_b για σωστό PnL Sync
    $checkStmt = $pdo->prepare("
        SELECT user_id, asset_a, asset_b, mode, commission_a, commission_b 
        FROM active_pairs 
        WHERE id = ? AND status = 'OPEN' 
        LIMIT 1
    ");
    $checkStmt->execute([$pair_id]);
    $tradeData = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$tradeData) {
        echo json_encode(["success" => false, "message" => "Trade not found or already closed."]);
        exit;
    }

    $target_user_id = $tradeData['user_id'];
    $mode = $tradeData['mode'];

    // 3. Ενημέρωση της βάσης δεδομένων (active_pairs)
    $stmt = $pdo->prepare("UPDATE active_pairs SET 
                            status = 'CLOSED', 
                            exit_price_a = :pa, 
                            exit_price_b = :pb, 
                            final_pnl = :pnl,
                            closed_at = NOW(),
                            notes = 'Closed via External Webhook'
                           WHERE id = :id AND status = 'OPEN'");
    
    $stmt->execute([
        ':pa'  => $exit_price_a,
        ':pb'  => $exit_price_b,
        ':pnl' => $final_pnl,
        ':id'  => $pair_id
    ]);

    if ($stmt->rowCount() > 0) {
        
        if ($stmt->rowCount() > 0) {
        
        // 4. Εγγραφή στο Trade Journal (Ευθυγράμμιση πεδίων 7-7)
        $totalFees = floatval($tradeData['commission_a'] ?? 0) + floatval($tradeData['commission_b'] ?? 0);
        $gross_pnl = floatval($final_pnl);
        $net_pnl   = $gross_pnl - $totalFees;

        $journal = $pdo->prepare("
            INSERT INTO zEQZkBci_trade_journal 
            (user_id, account_type, pair, gross_pnl, total_commission, net_pnl, setup, notes, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'WEBHOOK_EXIT', 'External signal received', NOW())
        ");

        $journal->execute([
            $target_user_id, 
            $mode, 
            $tradeData['asset_a'] . "/" . $tradeData['asset_b'], 
            round($gross_pnl, 4), 
            round($totalFees, 4), 
            round($net_pnl, 4),
            'External Webhook'
        ]);

        // 5. Ειδοποίηση Telegram (Στοχευμένη στον κάτοχο του trade)
        $emoji = ($final_pnl >= 0) ? "✅" : "🔻";
        $msg = "{$emoji} <b>Trade Closed (Webhook)</b>\n";
        $msg .= "Pair: <code>{$tradeData['asset_a']}/{$tradeData['asset_b']}</code>\n";
        $msg .= "PnL: <b>$" . number_format($final_pnl, 2) . "</b>\n";
        $msg .= "Mode: " . ($mode === 'LIVE' ? "🔴 LIVE" : "🟡 DEMO");
        
        sendTelegramNotification($msg, $target_user_id);

        // 6. Ειδοποίηση στο Dashboard UI
        $logMsg = "Webhook Exit: " . $tradeData['asset_a'] . "/" . $tradeData['asset_b'] . " PnL: $" . $final_pnl;
        broadcastLog($pdo, 'SUCCESS', $logMsg, $target_user_id);

        echo json_encode(["success" => true, "message" => "Trade #$pair_id finalized via Webhook."]);
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "System Error: " . $e->getMessage()]);
}