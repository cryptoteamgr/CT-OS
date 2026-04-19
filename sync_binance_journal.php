<?php
/**
 * CT-OS | sync_binance_journal.php (Complete Version)
 * Σκοπός: Αυτόματο τράβηγμα Open Orders, Trades, Funding και Transactions από Binance API.
 */
require_once 'db_config.php';
require_once 'functions.php';

// Παίρνουμε όλους τους χρήστες με ενεργά κλειδιά
$users = $pdo->query("SELECT DISTINCT user_id FROM api_keys WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);

foreach ($users as $uid) {
    foreach (['LIVE', 'DEMO'] as $mode) {
        $key_stmt = $pdo->prepare("SELECT api_key, api_secret FROM api_keys WHERE user_id = ? AND account_type = ? AND is_active = 1 LIMIT 1");
        $key_stmt->execute([$uid, $mode]);
        $keys = $key_stmt->fetch();

        if (!$keys) continue;

        $api_key = decrypt_data($keys['api_key']);
        $api_secret = decrypt_data($keys['api_secret']);
        $base_url = ($mode === 'LIVE') ? "https://fapi.binance.com" : "https://testnet.binancefuture.com";

        // Εκτέλεση Συγχρονισμών
        syncOpenOrders($uid, $mode, $api_key, $api_secret, $base_url);
        syncTradeHistory($uid, $mode, $api_key, $api_secret, $base_url);
        syncFundingFees($uid, $mode, $api_key, $api_secret, $base_url);
        syncAccountTransactions($uid, $mode, $api_key, $api_secret, $base_url);
    }
}

// --- ΣΥΝΑΡΤΗΣΗ ΓΙΑ OPEN ORDERS ---
function syncOpenOrders($uid, $mode, $key, $secret, $base) {
    global $pdo;
    $endpoint = "/fapi/v1/openOrders";
    $timestamp = number_format(microtime(true) * 1000, 0, '.', '');
    $query = "timestamp=" . $timestamp . "&recvWindow=10000";
    $signature = hash_hmac('sha256', $query, $secret);
    $url = $base . $endpoint . "?" . $query . "&signature=" . $signature;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-MBX-APIKEY: ' . $key]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $orders = json_decode($response, true);
        if (is_array($orders)) {
            $pdo->prepare("DELETE FROM zEQZkBci_binance_orders WHERE user_id = ? AND account_type = ?")
                ->execute([$uid, $mode]);

            $sql = "INSERT INTO zEQZkBci_binance_orders (user_id, order_id, symbol, side, type, price, quantity, status, account_type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?/1000))";
            $ins = $pdo->prepare($sql);
            foreach ($orders as $o) {
                $ins->execute([$uid, $o['orderId'], $o['symbol'], $o['side'], $o['type'], $o['price'], $o['origQty'], $o['status'], $mode, $o['time']]);
            }
        }
    }
}

// --- ΣΥΝΑΡΤΗΣΗ ΓΙΑ TRADE HISTORY ---
function syncTradeHistory($uid, $mode, $key, $secret, $base) {
    global $pdo;
    $endpoint = "/fapi/v1/userTrades";
    $timestamp = number_format(microtime(true) * 1000, 0, '.', '');
    $query = "limit=50&timestamp=" . $timestamp . "&recvWindow=10000";
    $signature = hash_hmac('sha256', $query, $secret);
    $url = $base . $endpoint . "?" . $query . "&signature=" . $signature;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-MBX-APIKEY: ' . $key]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $trades = json_decode($response, true);
        if (is_array($trades)) {
            $sql = "INSERT IGNORE INTO zEQZkBci_binance_trades (user_id, trade_id, order_id, symbol, side, price, qty, realized_pnl, commission, commission_asset, account_type, trade_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?/1000))";
            $ins = $pdo->prepare($sql);
            foreach ($trades as $t) {
                $ins->execute([$uid, $t['id'], $t['orderId'], $t['symbol'], $t['side'], $t['price'], $t['qty'], $t['realizedPnl'], $t['commission'], $t['commissionAsset'], $mode, $t['time']]);
            }
        }
    }
}

// --- ΣΥΝΑΡΤΗΣΗ ΓΙΑ FUNDING FEES ---
function syncFundingFees($uid, $mode, $key, $secret, $base) {
    global $pdo;
    $endpoint = "/fapi/v1/income";
    $timestamp = number_format(microtime(true) * 1000, 0, '.', '');
    $query = "incomeType=FUNDING_FEE&limit=30&timestamp=" . $timestamp . "&recvWindow=10000";
    $signature = hash_hmac('sha256', $query, $secret);
    $url = $base . $endpoint . "?" . $query . "&signature=" . $signature;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-MBX-APIKEY: ' . $key]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $income = json_decode($response, true);
        if (is_array($income)) {
            $sql = "INSERT IGNORE INTO zEQZkBci_binance_funding (user_id, symbol, amount, timestamp, account_type) VALUES (?, ?, ?, FROM_UNIXTIME(?/1000), ?)";
            $ins = $pdo->prepare($sql);
            foreach ($income as $i) {
                $ins->execute([$uid, $i['symbol'], $i['income'], $i['time'], $mode]);
            }
        }
    }
}

// --- ΣΥΝΑΡΤΗΣΗ ΓΙΑ TRANSACTIONS (DEPOSITS/WITHDRAWALS) ---
function syncAccountTransactions($uid, $mode, $key, $secret, $base) {
    global $pdo;
    $endpoint = "/fapi/v1/income"; // Χρησιμοποιούμε πάλι το income endpoint για Transfers
    $timestamp = number_format(microtime(true) * 1000, 0, '.', '');
    $query = "incomeType=TRANSFER&limit=20&timestamp=" . $timestamp . "&recvWindow=10000";
    $signature = hash_hmac('sha256', $query, $secret);
    $url = $base . $endpoint . "?" . $query . "&signature=" . $signature;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-MBX-APIKEY: ' . $key]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $income = json_decode($response, true);
        if (is_array($income)) {
            $sql = "INSERT IGNORE INTO zEQZkBci_binance_transactions (user_id, asset, amount, type, status, timestamp, account_type) VALUES (?, ?, ?, ?, ?, FROM_UNIXTIME(?/1000), ?)";
            $ins = $pdo->prepare($sql);
            foreach ($income as $i) {
                // Αν το income είναι θετικό είναι DEPOSIT, αν είναι αρνητικό είναι WITHDRAWAL
                $type = ($i['income'] > 0) ? 'DEPOSIT' : 'WITHDRAWAL';
                $status = 'COMPLETED';
                $ins->execute([$uid, $i['asset'], abs($i['income']), $type, $status, $i['time'], $mode]);
            }
        }
    }
}
?>