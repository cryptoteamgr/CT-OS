<?php
/**
 * CT-OS | Force Wallet Update
 * ----------------------------------------------------------------
 * ΣΚΟΠΟΣ: Χτυπάει την Binance και διορθώνει ΤΩΡΑ τα balance στην SQL.
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/functions.php';

echo "<h2>🚀 Starting Force Wallet Sync...</h2>";

try {
    // 1. Τραβάμε ΟΛΟΥΣ τους χρήστες που έχουν API Keys
    $stmt = $pdo->query("SELECT u.id, u.username, a.api_key, a.api_secret, a.account_type 
                         FROM users u 
                         JOIN api_keys a ON u.id = a.user_id 
                         WHERE a.is_active = 1");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $u) {
        $uID = $u['id'];
        $user = $u['username'];
        $mode = strtoupper(trim($u['account_type']));
        
        // Αποκρυπτογράφηση κλειδιών
        $k = decrypt_data($u['api_key']);
        $s = decrypt_data($u['api_secret']);

        // Επιλογή URL
        $baseUrl = ($mode === 'DEMO') ? "https://testnet.binancefuture.com" : "https://fapi.binance.com";
        
        // Binance Request
        $ts = number_format(microtime(true) * 1000, 0, '.', '');
        $sig = hash_hmac('sha256', "timestamp=$ts", $s);
        
        $ch = curl_init("$baseUrl/fapi/v2/account?timestamp=$ts&signature=$sig");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $k"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($res['totalMarginBalance'])) {
            $wallet = (float)$res['totalWalletBalance'];
            $equity = (float)$res['totalMarginBalance'];

            // UPDATE ΣΤΗ ΒΑΣΗ ΣΟΥ (Στα σωστά πεδία)
            $update = $pdo->prepare("UPDATE users SET last_balance = ?, last_equity = ? WHERE id = ?");
            $update->execute([$wallet, $equity, $uID]);

            echo "<div style='color:green;'>✅ Updated <b>$user</b> ($mode): Wallet: $$wallet | Equity: <b>$$equity</b></div>";
        } else {
            $err = $res['msg'] ?? 'Unknown API Error';
            echo "<div style='color:red;'>❌ Failed for <b>$user</b>: $err</div>";
        }
    }

    echo "<h3>Done! Check your Admin Monitor now.</h3>";

} catch (Exception $e) {
    die("Fatal Error: " . $e->getMessage());
}