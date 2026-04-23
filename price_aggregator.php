<?php
/**
 * CT-OS | price_aggregator.php
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/functions.php';

// 1. LOCK FILE (Προστασία από διπλή εκτέλεση)
$lock_file = __DIR__ . '/aggregator.lock';
if (file_exists($lock_file) && (time() - filemtime($lock_file) < 60)) { 
    die("Error: Aggregator is already running.\n"); 
}
file_put_contents($lock_file, "running");

try {
    // 2. ΤΡΑΒΑΜΕ ΤΙΣ ΤΙΜΕΣ ΚΑΙ ΤΟ VOLUME (Public API)
    // Χρησιμοποιούμε το ticker/24hr για να παίρνουμε και τιμή και Volume για τον Liquidity Guard
    $ch = curl_init("https://fapi.binance.com/fapi/v1/ticker/24hr");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $tickerData = json_decode($response, true);
    curl_close($ch);

    $prices = [];
    $exchange_info = ['symbols' => []];

    if ($tickerData && is_array($tickerData)) {
        foreach ($tickerData as $t) {
            $symbol = $t['symbol'];
            $price = floatval($t['lastPrice']);
            $volume = floatval($t['quoteVolume']); // 24h τζίρος σε USDT
            
            $prices[$symbol] = $price;
            
            // Φτιάχνουμε ένα cache συμβατό με τον Scanner για Liquidity Check
            $exchange_info['symbols'][] = [
                'symbol' => $symbol,
                'lastPrice' => $price,
                'quoteVolume' => $volume
            ];
        }
        
        // Αποθήκευση Cache Τιμών
        file_put_contents(__DIR__ . '/prices_cache.json', json_encode($prices));
        // Αποθήκευση Cache Volume (για τον Scanner)
        file_put_contents(__DIR__ . '/exchange_info_cache.json', json_encode($exchange_info));
        
    } else {
        throw new Exception("Binance API Connectivity Error (Ticker).");
    }

    // 3. ΕΝΗΜΕΡΩΣΗ WALLETS & MARGIN (Με καθυστέρηση Anti-Ban)
    $sql = "SELECT u.id, u.username, u.bot_mode, a.api_key, a.api_secret, a.account_type 
            FROM users u 
            JOIN api_keys a ON u.id = a.user_id 
            WHERE a.is_active = 1 
            AND a.account_type = u.bot_mode 
            AND u.bot_status = 'ON'";

    $users = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $u) {
        $k = decrypt_data($u['api_key']);
        $s = decrypt_data($u['api_secret']);
        
        // Χρήση της κεντρικής function από το functions.php για ομοιομορφία
        $accData = getBinanceAccountInfo($k, $s, $u['bot_mode']);

        if ($accData) {
            $stmt = $pdo->prepare("UPDATE users SET last_balance = ?, last_equity = ?, last_maint_margin = ?, last_sync_at = NOW() WHERE id = ?");
            $stmt->execute([
                $accData['balance'], 
                $accData['equity'], 
                $accData['maintMargin'], 
                $u['id']
            ]);
            // ΔΙΟΡΘΩΣΗ ΓΡΑΜΜΗΣ 79: Αφαίρεση του προβληματικού $ και χρήση τελείας
            echo "[" . date("H:i:s") . "] Wallet Sync: " . $u['username'] . " | Equity: $" . $accData['equity'] . "\n";
        }
        
        usleep(300000); // 0.3 δευτ. παύση ανά χρήστη
    }

    // 4. ΕΝΗΜΕΡΩΣΗ ASSET HISTORY (Batch)
    $stmtPairs = $pdo->query("SELECT DISTINCT asset_a, asset_b FROM pair_universe WHERE is_active = 1");
    $activeAssets = [];
    foreach ($stmtPairs->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $activeAssets[] = strtoupper($row['asset_a']);
        $activeAssets[] = strtoupper($row['asset_b']);
    }
    $uniqueAssets = array_unique($activeAssets);

    if (!empty($uniqueAssets)) {
        $pdo->beginTransaction();
        $insH = $pdo->prepare("INSERT INTO asset_history (asset, price, timestamp) VALUES (?, ?, NOW())");
        foreach ($uniqueAssets as $asset) {
            $sym = $asset . "USDT";
            if (isset($prices[$sym])) {
                $insH->execute([$asset, $prices[$sym]]);
            }
        }
        $pdo->commit();
    }

    // 5. ENHMERWSH VOLUME STO PAIR_UNIVERSE (Liquidity Guard data)
    $stmtPairs = $pdo->query("SELECT id, asset_a, asset_b FROM pair_universe WHERE is_active = 1");
    $updVolume = $pdo->prepare("UPDATE pair_universe SET volume_a = ?, volume_b = ?, last_update = NOW() WHERE id = ?");
    
    foreach ($stmtPairs->fetchAll(PDO::FETCH_ASSOC) as $pair) {
        $symbolA = strtoupper($pair['asset_a']) . "USDT";
        $symbolB = strtoupper($pair['asset_b']) . "USDT";
        
        $volumeA = 0;
        $volumeB = 0;
        
        // Find volume data from tickerData
        if ($tickerData && is_array($tickerData)) {
            foreach ($tickerData as $t) {
                if ($t['symbol'] === $symbolA) {
                    $volumeA = floatval($t['quoteVolume']);
                    echo "[" . date("H:i:s") . "] DEBUG: Found volume for {$symbolA}: {$volumeA}\n";
                }
                if ($t['symbol'] === $symbolB) {
                    $volumeB = floatval($t['quoteVolume']);
                    echo "[" . date("H:i:s") . "] DEBUG: Found volume for {$symbolB}: {$volumeB}\n";
                }
            }
        } else {
            echo "[" . date("H:i:s") . "] DEBUG: No tickerData available for volume update\n";
        }
        
        $updVolume->execute([$volumeA, $volumeB, $pair['id']]);
    }

    // 6. YPOLOGISMOS LIVE PNL (Real-time update gia to UI)
    $openTrades = $pdo->query("SELECT ap.*, u.leverage FROM active_pairs ap JOIN users u ON ap.user_id = u.id WHERE ap.status = 'OPEN'")->fetchAll(PDO::FETCH_ASSOC);
    $updPnl = $pdo->prepare("UPDATE active_pairs SET current_pnl = ? WHERE id = ?");

    foreach ($openTrades as $pos) {
        $sA = strtoupper($pos['asset_a'] . "USDT");
        $sB = strtoupper($pos['asset_b'] . "USDT");

        if (isset($prices[$sA], $prices[$sB])) {
            $pnlA = ($pos['side_a'] === 'BUY' ? $prices[$sA] - $pos['entry_price_a'] : $pos['entry_price_a'] - $prices[$sA]) * $pos['quantity_a'];
            $pnlB = ($pos['side_b'] === 'BUY' ? $prices[$sB] - $pos['entry_price_b'] : $pos['entry_price_b'] - $prices[$sB]) * $pos['quantity_b'];
            
            $totalFees = floatval($pos['commission_a'] ?? 0) + floatval($pos['commission_b'] ?? 0);
            $netPnL = round(($pnlA + $pnlB) - $totalFees, 4);
            
            $updPnl->execute([$netPnL, $pos['id']]);
        }
    }

    // Καθαρισμός παλαιών δεδομένων (Housekeeping)
    $pdo->query("DELETE FROM asset_history WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    
    // 7. Z-SCORE CALCULATION (Merged from force_zscore_sync.php)
    function calculateZScore($ratios) {
        $n = count($ratios);
        if ($n < 30) return 0;
        $mean = array_sum($ratios) / $n;
        $variance = 0;
        foreach ($ratios as $r) { $variance += pow($r - $mean, 2); }
        $std_dev = sqrt($variance / $n);
        if ($std_dev < 0.000000001) return 0;
        return (end($ratios) - $mean) / $std_dev;
    }
    
    function getDetailedHistory($pdo, $symbol) {
        $stmt = $pdo->prepare("
            SELECT timestamp, price 
            FROM asset_history 
            WHERE asset = ? 
            AND id IN (
                SELECT MAX(id) 
                FROM asset_history 
                WHERE asset = ? 
                GROUP BY DATE(timestamp), HOUR(timestamp)
            )
            ORDER BY timestamp DESC 
            LIMIT 500
        ");
        $stmt->execute([strtoupper($symbol), strtoupper($symbol)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $stmtPairs = $pdo->query("SELECT id, asset_a, asset_b FROM pair_universe WHERE is_active = 1");
    $updZScore = $pdo->prepare("UPDATE pair_universe SET last_z_score = ?, last_beta = ?, last_update = NOW() WHERE id = ?");
    
    foreach ($stmtPairs->fetchAll(PDO::FETCH_ASSOC) as $pair) {
        $a = strtoupper($pair['asset_a']);
        $b = strtoupper($pair['asset_b']);
        
        $dataA = getDetailedHistory($pdo, $a);
        $dataB = getDetailedHistory($pdo, $b);
        
        if (count($dataA) > 30 && count($dataB) > 30) {
            $hA_prices = array_reverse(array_column($dataA, 'price'));
            $hB_prices = array_reverse(array_column($dataB, 'price'));
            
            $ratios = [];
            $len = min(count($hA_prices), count($hB_prices));
            for ($i = 0; $i < $len; $i++) {
                if ($hB_prices[$i] > 0) $ratios[] = (float)$hA_prices[$i] / (float)$hB_prices[$i];
            }
            
            $real_z = round(calculateZScore($ratios), 4);
            $real_beta = round(calculate_beta($hA_prices, $hB_prices), 4);
            
            $updZScore->execute([$real_z, $real_beta, $pair['id']]);
        }
    }
    
    echo "[" . date("H:i:s") . "] Z-Score Calculation Completed.\n";
    echo "[" . date("H:i:s") . "] Aggregator Cycle Completed Successfully.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo "[" . date("H:i:s") . "] CRITICAL ERROR: " . $e->getMessage() . "\n";
}

// Απελευθέρωση Lock
if (file_exists($lock_file)) unlink($lock_file);