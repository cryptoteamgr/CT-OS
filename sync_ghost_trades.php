<?php
/**
 * CT-OS | sync_ghost_trades.php
 * Επαναφέρει χαμένα trades από τη Binance στη βάση δεδομένων.
 */
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/functions.php';

$user_id = 7; // Το ID του χρήστη που θέλουμε να συγχρονίσουμε
$account_type = 'LIVE';

// 1. Λήψη API Keys
$stmt = $pdo->prepare("SELECT api_key, api_secret FROM api_keys WHERE user_id = ? AND account_type = ? AND is_active = 1");
$stmt->execute([$user_id, $account_type]);
$api = $stmt->fetch();

if (!$api) die("❌ No API Keys found for User $user_id");

$fK = decrypt_data($api['api_key']);
$fS = decrypt_data($api['api_secret']);

// 2. Λήψη ανοιχτών θέσεων από Binance
$url = "https://fapi.binance.com/fapi/v2/positionRisk";
$params = ['timestamp' => round(microtime(true) * 1000)];
$query = http_build_query($params);
$sig = hash_hmac('sha256', $query, $fS);

$ch = curl_init($url . '?' . $query . '&signature=' . $sig);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $fK"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = json_decode(curl_exec($ch), true);
curl_close($ch);

$binance_positions = [];
if (is_array($res)) {
    foreach ($res as $p) {
        $amount = floatval($p['positionAmt'] ?? 0);
        $markPrice = floatval($p['markPrice'] ?? 0);
        
        // Υπολογίζουμε την τρέχουσα αξία της θέσης σε USDT
        $position_value = abs($amount * $markPrice);

        /**
         * ΦΙΛΤΡΟ ΑΣΦΑΛΕΙΑΣ: 
         * 1. Το amount πρέπει να μην είναι μηδέν.
         * 2. Η αξία της θέσης πρέπει να είναι πάνω από 2 USDT (για να αγνοεί dust/υπολείμματα).
         * 3. Προαιρετικά ελέγχουμε αν το positionSide είναι αυτό που θέλουμε (LONG/SHORT).
         */
        if ($amount != 0 && $position_value > 2.0) {
            $binance_positions[$p['symbol']] = $p;
        }
    }
}

// 3. Λήψη ενεργών ζευγαριών από το Universe για να ξέρουμε ποια "ταιριάζουν"
$stmt = $pdo->query("SELECT asset_a, asset_b FROM pair_universe");
$universe = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "--- SYNCING USER $user_id ---\n";

foreach ($universe as $pair) {
    $symA = $pair['asset_a'] . "USDT";
    $symB = $pair['asset_b'] . "USDT";

    // Αν υπάρχουν και τα δύο σκέλη στη Binance
    if (isset($binance_positions[$symA]) && isset($binance_positions[$symB])) {
        
        // Έλεγχος αν υπάρχει ήδη στη βάση μας ως OPEN
        $check = $pdo->prepare("SELECT id FROM active_pairs WHERE user_id = ? AND asset_a = ? AND asset_b = ? AND status = 'OPEN'");
        $check->execute([$user_id, $pair['asset_a'], $pair['asset_b']]);
        
        if (!$check->fetch()) {
            echo "🔎 Found Ghost Pair: {$pair['asset_a']}/{$pair['asset_b']}. Injecting...\n";
            
            $posA = $binance_positions[$symA];
            $posB = $binance_positions[$symB];

            $sideA = (floatval($posA['positionAmt']) > 0) ? 'BUY' : 'SELL';
            $sideB = (floatval($posB['positionAmt']) > 0) ? 'BUY' : 'SELL';

            $sql = "INSERT INTO active_pairs (user_id, asset_a, asset_b, side_a, side_b, quantity_a, quantity_b, entry_price_a, entry_price_b, entry_z_score, beta_used, status, mode, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 2.0, 1.0, 'OPEN', ?, NOW())";
            
            $pdo->prepare($sql)->execute([
                $user_id, $pair['asset_a'], $pair['asset_b'], 
                $sideA, $sideB, 
                abs($posA['positionAmt']), abs($posB['positionAmt']),
                $posA['entryPrice'], $posB['entryPrice'],
                $account_type
            ]);
            echo "✅ Successfully restored {$pair['asset_a']}/{$pair['asset_b']}\n";
        }
    }
}
echo "--- SYNC COMPLETED ---";
?>