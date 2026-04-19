<?php
/**
 * CT-OS | sync_entry_prices.php - PROFESSIONAL ARMOR VERSION (FINAL FIXED)
 * ----------------------------------------------------------------
 * ΣΚΟΠΟΣ: Συγχρονισμός τιμών/fees, ανάκτηση χαμένων trades (Recovery) 
 * και αρχειοθέτηση κλειστών (Cleanup). 
 * ΠΡΟΣΟΧΗ: ΠΟΤΕ δεν στέλνει εντολές κλεισίματος στη Binance.
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/functions.php';

// Ορισμός Timezone για ταύτιση με τη βάση δεδομένων
date_default_timezone_set('Europe/Athens');

$lock_file = __DIR__ . '/sync_prices.lock';
set_time_limit(300);

// 1. ΜΗΧΑΝΙΣΜΟΣ LOCK (Αποφυγή διπλής εκτέλεσης)
if (file_exists($lock_file) && (time() - filemtime($lock_file) < 300)) {
    die("[" . date("H:i:s") . "] Sync is already running.\n");
}
file_put_contents($lock_file, "running");
register_shutdown_function(function() use ($lock_file) {
    if (file_exists($lock_file)) unlink($lock_file);
});

function slog($msg) {
    echo "[" . date("H:i:s") . "] [PRO-SYNC] " . $msg . "\n";
}

/**
 * ΣΥΝΑΡΤΗΣΗ ΛΗΨΗΣ ΑΝΟΙΧΤΩΝ ΘΕΣΕΩΝ ΑΠΟ BINANCE
 */
if (!function_exists('getBinanceActivePositions')) {
    function getBinanceActivePositions($api_key, $api_secret, $mode) {
        $base_url = ($mode === 'LIVE') ? "https://fapi.binance.com" : "https://testnet.binancefuture.com";
        $endpoint = "/fapi/v2/positionRisk";
        $timestamp = number_format(microtime(true) * 1000, 0, '.', '');
        $query = "timestamp=" . $timestamp . "&recvWindow=20000";
        $signature = hash_hmac('sha256', $query, $api_secret);
        
        $ch = curl_init($base_url . $endpoint . "?" . $query . "&signature=" . $signature);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-MBX-APIKEY: ' . $api_key]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $data = json_decode($response, true);
        if ($httpCode !== 200 || !is_array($data) || isset($data['code'])) return null;
        
        $active = [];
        foreach ($data as $pos) {
            if (isset($pos['symbol']) && floatval($pos['positionAmt'] ?? 0) != 0) {
                $active[] = strtoupper($pos['symbol']);
            }
        }
        return $active;
    }
}

// Φόρτωση τιμών από το Cache (για υπολογισμό PnL στο Cleanup)
$cacheFile = __DIR__ . '/prices_cache.json';
$prices = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];

$script_start = time();
$max_run = 55; // Εκτέλεση για σχεδόν 1 λεπτό
$last_integrity_check = 0;

slog("🚀 STARTING PROFESSIONAL SYNC ENGINE...");

while (time() - $script_start < $max_run) {
    
    // 2. ΕΝΗΜΕΡΩΣΗ ΤΙΜΩΝ ΕΙΣΟΔΟΥ & FEES (Για νέα trades που δεν έχουν ακόμα δεδομένα)
    $stmt = $pdo->query("SELECT * FROM active_pairs WHERE status = 'OPEN' AND (entry_price_a <= 0 OR commission_a <= 0)");
    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pending as $p) {
        $mode = strtoupper($p['mode']);
        $k_stmt = $pdo->prepare("SELECT api_key, api_secret FROM api_keys WHERE user_id = ? AND account_type = ? AND is_active = 1 LIMIT 1");
        $k_stmt->execute([$p['user_id'], $mode]);
        $keys = $k_stmt->fetch();
        if (!$keys) continue;

        $fK = decrypt_data($keys['api_key']);
        $fS = decrypt_data($keys['api_secret']);

        $resA = getBinanceTradeData($p['asset_a'], $fK, $fS, $mode);
        usleep(300000); // 0.3s delay για αποφυγή Rate Limits
        $resB = getBinanceTradeData($p['asset_b'], $fK, $fS, $mode);

        if (is_array($resA) && is_array($resB) && isset($resA['price']) && $resA['price'] > 0) {
            $upd = $pdo->prepare("UPDATE active_pairs SET entry_price_a = ?, entry_price_b = ?, commission_a = ?, commission_b = ? WHERE id = ?");
            $upd->execute([ 
                (float)$resA['price'], (float)$resB['price'], 
                (float)($resA['commission'] ?? 0), (float)($resB['commission'] ?? 0), 
                $p['id'] 
            ]);
            slog("✅ UPDATED PRICES: {$p['asset_a']}/{$p['asset_b']} for User #{$p['user_id']}");
        }
    }

    // 3. INTEGRITY CHECK & RECOVERY (Κάθε 45 δευτερόλεπτα)
    if (time() - $last_integrity_check > 45) {
        slog("🔍 Running Integrity Check...");
        $stmtUsers = $pdo->query("SELECT user_id, account_type as mode, api_key, api_secret FROM api_keys WHERE is_active = 1");
        $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as $u) {
            $uMode = strtoupper($u['mode']);
            $binance_positions = getBinanceActivePositions(decrypt_data($u['api_key']), decrypt_data($u['api_secret']), $uMode);
            
            // Ασφάλεια: Αν το API δεν απαντήσει, δεν κάνουμε καμία αλλαγή
            if ($binance_positions === null) {
                slog("⚠️ API Timeout/Error for User #{$u['user_id']}. Skipping.");
                continue; 
            }

            // --- A. RECOVERY LOGIC (Ανάσταση trades που είναι CLOSED στην SQL αλλά OPEN στη Binance) ---
            // 🔥 FIX: Only recover if BOTH assets are present (prevent partial trade recovery)
            $sql_closed_trades = $pdo->prepare("SELECT id, asset_a, asset_b FROM active_pairs WHERE user_id = ? AND status = 'CLOSED' AND mode = ? AND (closed_at > DATE_SUB(NOW(), INTERVAL 48 HOUR) OR closed_at IS NULL) ORDER BY id DESC");
            $sql_closed_trades->execute([$u['user_id'], $uMode]);
            $closed_trades = $sql_closed_trades->fetchAll(PDO::FETCH_ASSOC);

            foreach ($closed_trades as $trade) {
                $symA = strtoupper($trade['asset_a'] . "USDT");
                $symB = strtoupper($trade['asset_b'] . "USDT");

                // Only recover if BOTH assets are present on Binance AND not already in use by other open trades
                if (in_array($symA, $binance_positions) && in_array($symB, $binance_positions)) {
                    // Check if assets are already in use by other open trades (asset overlap check)
                    $overlap_check = $pdo->prepare("SELECT id FROM active_pairs WHERE user_id = ? AND status = 'OPEN' AND (asset_a IN (?, ?) OR asset_b IN (?, ?)) AND id != ?");
                    $overlap_check->execute([$u['user_id'], $trade['asset_a'], $trade['asset_b'], $trade['asset_a'], $trade['asset_b'], $trade['id']]);
                    if ($overlap_check->fetch()) {
                        slog("⚠️ SKIP RECOVERY: {$trade['asset_a']}/{$trade['asset_b']} - Assets already in use by other open trade.");
                        continue;
                    }
                    
                    // Check if already exists as OPEN
                    $check = $pdo->prepare("SELECT id FROM active_pairs WHERE user_id = ? AND asset_a = ? AND asset_b = ? AND status = 'OPEN' AND mode = ? LIMIT 1");
                    $check->execute([$u['user_id'], $trade['asset_a'], $trade['asset_b'], $uMode]);

                    if ($check->rowCount() == 0) {
                        $recover = $pdo->prepare("UPDATE active_pairs SET status = 'OPEN', notes = 'Auto-Recovered by Sync' 
                                                  WHERE id = ?");
                        $recover->execute([$trade['id']]);
                        if ($recover->rowCount() > 0) {
                            slog("🔄 RECOVERED: {$trade['asset_a']}/{$trade['asset_b']} for User #{$u['user_id']} (Both assets found on Binance)");
                        }
                    }
                }
            }

            // --- B. CLEANUP LOGIC (Κλείσιμο στην SQL αν το trade λείπει από τη Binance) ---
            $sql_active = $pdo->prepare("SELECT * FROM active_pairs WHERE user_id = ? AND status = 'OPEN' AND mode = ?");
            $sql_active->execute([$u['user_id'], $uMode]);
            $trades = $sql_active->fetchAll(PDO::FETCH_ASSOC);

            foreach ($trades as $t) {
                // 🔥 ΔΙΚΛΕΙΔΑ ΑΣΦΑΛΕΙΑΣ: 5-λεπτο Buffer για αποφυγή Race Condition
                $created_time = strtotime($t['created_at']);
                if ((time() - $created_time) < 300) {
                    continue; // Μην αγγίζεις trades νεότερα των 5 λεπτών
                }

                $symA = strtoupper($t['asset_a'] . "USDT");
                $symB = strtoupper($t['asset_b'] . "USDT");

                // Πρέπει τουλάχιστον ένα asset να λείπει για να κλείσει το trade (partial trade cleanup)
                if (!in_array($symA, $binance_positions) || !in_array($symB, $binance_positions)) {
                    
                    // Get user leverage
                    $levStmt = $pdo->prepare("SELECT leverage FROM users WHERE id = ? LIMIT 1");
                    $levStmt->execute([$t['user_id']]);
                    $userLev = $levStmt->fetchColumn() ?: 5;
                    
                    // Υπολογισμός τελικού PnL από τις τελευταίες γνωστές τιμές cache
                    $exitA = $prices[$symA] ?? (float)$t['entry_price_a'];
                    $exitB = $prices[$symB] ?? (float)$t['entry_price_b'];
                    
                    $pnlA = (strtoupper($t['side_a']) === 'BUY') ? ($exitA - (float)$t['entry_price_a']) * (float)$t['quantity_a'] : ((float)$t['entry_price_a'] - $exitA) * (float)$t['quantity_a'];
                    $pnlB = (strtoupper($t['side_b']) === 'BUY') ? ($exitB - (float)$t['entry_price_b']) * (float)$t['quantity_b'] : ((float)$t['entry_price_b'] - $exitB) * (float)$t['quantity_b'];
                    
                    $finalPnl = round(($pnlA + $pnlB) / $userLev, 4);
                    $totalFees = floatval($t['commission_a'] ?? 0) + floatval($t['commission_b'] ?? 0);

                    slog("🧹 CLEANUP: Trade #{$t['id']} ({$t['asset_a']}/{$t['asset_b']}) archived. Not found on Binance.");
                    
                    // Ενημέρωση SQL
                    $pdo->prepare("UPDATE active_pairs SET status = 'CLOSED', closed_at = NOW(), final_pnl = ?, notes = 'Sync Cleanup: Missing on Binance' WHERE id = ?")
                        ->execute([$finalPnl, $t['id']]);

                    // Εγγραφή στο Journal για ιστορικότητα
                    $pdo->prepare("INSERT INTO zEQZkBci_trade_journal (user_id, account_type, pair, pnl, total_commission, setup, notes, created_at) VALUES (?, ?, ?, ?, ?, 'CLEANUP', 'Auto-archived by Integrity Check', NOW())")
                        ->execute([
                            $u['user_id'], 
                            $uMode, 
                            "{$t['asset_a']}/{$t['asset_b']}", 
                            $finalPnl, 
                            $totalFees
                        ]);
                }
            }
        }
        $last_integrity_check = time();
    }
    
    usleep(2000000); // Αναμονή 2 δευτερολέπτων πριν τον επόμενο κύκλο
}

slog("🏁 CYCLE COMPLETED.");