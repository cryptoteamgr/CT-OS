<?php
/**
 * CT-OS | pair_scanner.php - Final Fixed Pro Version with Margin Guard
 */

// 1. LOCK FILE (Double run protection)
$lock_file = __DIR__ . '/scanner.lock';
set_time_limit(300); 

if (file_exists($lock_file) && (time() - filemtime($lock_file) < 300)) {
    die("Error: Scanner is already running.\n");
}
file_put_contents($lock_file, "running");
register_shutdown_function(function() use ($lock_file) {
    if (file_exists($lock_file)) unlink($lock_file);
});

// 2. REQUIRES & LOGS
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/functions.php';

// Function to fetch Binance testnet symbols
function getBinanceTestnetSymbols() {
    $base_url = "https://testnet.binancefuture.com";
    $endpoint = "/fapi/v1/exchangeInfo";
    
    $ch = curl_init($base_url . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return [];
    }
    
    $data = json_decode($response, true);
    if (!isset($data['symbols'])) {
        return [];
    }
    
    $symbols = [];
    foreach ($data['symbols'] as $symbol) {
        if ($symbol['contractType'] === 'PERPETUAL' && $symbol['status'] === 'TRADING') {
            $symbols[] = $symbol['symbol'];
        }
    }
    
    return $symbols;
}
require_once __DIR__ . '/bot_engine.php';
// ΑΥΤΟΜΑΤΟ ΚΑΘΑΡΙΣΜΑ LOG (Αν ξεπεράσει τα 2MB)
$log_path = __DIR__ . '/cron_log.txt';
if (file_exists($log_path) && filesize($log_path) > 2 * 1024 * 1024) {
    file_put_contents($log_path, "[" . date("Y-m-d H:i:s") . "] Log cleared to save space.\n");
}

if (!function_exists('tlog')) {
    function tlog($msg) {
        $formatted = "[" . date("H:i:s") . "] " . $msg . "\n";
        echo $formatted;
        file_put_contents(__DIR__ . '/cron_log.txt', $formatted, FILE_APPEND);
    }
}

function broadcastLog($pdo, $type, $message, $user_id = 0) {
    try {
        $stmt = $pdo->prepare("INSERT INTO system_notifications (user_id, type, message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, strtoupper($type), $message]);
    } catch (Exception $e) { }
}

// --- START FAST LOOP ---
$script_start = time();
$max_execution_time = 50; 
tlog("🚀 FAST SCANNER STARTING (Pro Mode)");

while (time() - $script_start < $max_execution_time) {

    // 1. REFRESH DATA FROM DB
    try {
        $stmtUsers = $pdo->prepare("SELECT * FROM users WHERE bot_status = 'ON'");
        $stmtUsers->execute();
        $active_users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

        // Φιλτράρουμε: Cointegrated και έγκυρο Beta (p_value check removed)
        $sqlPairs = "SELECT * FROM pair_universe 
                     WHERE is_active = 1 
                     AND is_cointegrated = 1 
                     AND last_beta > 0 
                     AND (last_error_at IS NULL OR last_error_at < DATE_SUB(NOW(), INTERVAL 4 HOUR)) 
                     ORDER BY last_update ASC, p_value ASC 
                     LIMIT 100";
        
        $stmtPairs = $pdo->prepare($sqlPairs);
        $stmtPairs->execute();
        $pairs = $stmtPairs->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        tlog("❌ DB Error: " . $e->getMessage());
        sleep(2); 
        continue;
    }

    if (!$active_users || empty($pairs)) {
        // Αν δεν υπάρχουν cointegrated pairs, ενημερώνουμε το log μια φορά και περιμένουμε
        // tlog("info: No cointegrated pairs found in universe. Waiting...");
        usleep(2000000); 
        continue;
    }

    // 2. CACHE DATA
    $exCacheFile = __DIR__ . '/exchange_info_cache.json';
    $exchange_info = json_decode(@file_get_contents($exCacheFile), true);
    $precisions = [];
    if (isset($exchange_info['symbols'])) {
        foreach ($exchange_info['symbols'] as $s) { $precisions[$s['symbol']] = $s; }
    }

    $cacheFile = __DIR__ . '/prices_cache.json';
    $live_prices = json_decode(@file_get_contents($cacheFile), true);

    if (is_array($live_prices)) {
        foreach ($active_users as $user) {
            $user_id = $user['id'];
            $username = $user['username'];
            $account_type = strtoupper($user['bot_mode'] ?? 'DEMO'); 
            $capital_per_trade = floatval($user['capital_per_trade'] ?? 100);
            $user_lev = intval($user['leverage'] ?? 10);
            
            // Το αρχικό threshold του χρήστη από το Panel
            $base_z_threshold = floatval($user['z_threshold'] ?? 2.0);
            
            $maxLimit = intval($user['max_open_trades'] ?? 3);

            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM active_pairs WHERE user_id = ? AND status = 'OPEN'");
            $stmtCount->execute([$user_id]);
            $currentOpen = (int)$stmtCount->fetchColumn();

            foreach ($pairs as $p) {

                // --- ΟΡΙΣΜΟΣ BETA ΓΙΑ ΤΟ ΣΥΓΚΕΚΡΙΜΕΝΟ ΖΕΥΓΑΡΙ ($p) ---
                $beta = (float)($p['last_beta'] ?? 0);

                // Στατιστικός κόφτης: Αν το beta είναι 0 ή αρνητικό, το προσπερνάμε
                if ($beta <= 0.0001) {
                    continue; 
                }

                // 1. ΔΥΝΑΜΙΚΟ Z-THRESHOLD (PRO LOGIC)
                // Αν το correlation είναι κορυφαίο (>0.95) μπαίνουμε πιο νωρίς (-10%)
                // Αν το correlation είναι χαμηλό (<0.85) ζητάμε μεγαλύτερη απόκλιση (+15%) για ασφάλεια
                $current_corr = (float)($p['correlation'] ?? 0);
                if ($current_corr >= 0.95) {
                    $z_threshold = $base_z_threshold * 0.90; 
                } elseif ($current_corr < 0.85) {
                    $z_threshold = $base_z_threshold * 1.15;
                } else {
                    $z_threshold = $base_z_threshold;
                }

                // 2. Έλεγχος αν ο χρήστης είναι γεμάτος
                if ($currentOpen >= $maxLimit) {
                    $pdo->prepare("UPDATE pair_universe SET last_update = NOW() WHERE id = ?")->execute([$p['id']]);
                    continue; 
                }

                // 2. ANTI-LOOP COOLDOWN: Έλεγχος αν το ίδιο ζευγάρι έκλεισε πρόσφατα (τελευταία 5 λεπτά)
                $stmtCooldown = $pdo->prepare("SELECT id FROM active_pairs WHERE user_id = ? AND asset_a = ? AND asset_b = ? AND status = 'CLOSED' AND closed_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE) LIMIT 1");
                $stmtCooldown->execute([$user_id, $p['asset_a'], $p['asset_b']]);
                if ($stmtCooldown->fetch()) {
                    continue; 
                }

                // Ορισμός Συμβόλων και Τιμών
                $symbolA = strtoupper(trim($p['asset_a'])) . "USDT";
                $symbolB = strtoupper(trim($p['asset_b'])) . "USDT";
                $priceA  = $live_prices[$symbolA] ?? 0;
                $priceB  = $live_prices[$symbolB] ?? 0;
                
                // Ανάκτηση Στατιστικών από τη βάση (υπολογισμένα από το cron_cointegration.php)
                $z_score = (float)($p['last_z_score'] ?? 0);
                $beta    = (float)($p['last_beta'] ?? 0);

                // --- Ο ΝΕΟΣ ΣΤΑΤΙΣΤΙΚΟΣ ΚΟΦΤΗΣ BETA ---
                // Αν το beta είναι 0 ή αρνητικό, το ζευγάρι είναι ακατάλληλο για trading
                if ($beta <= 0.0001) {
                    continue; 
                }

                // 2. ΕΛΕΓΧΟΣ STALE DATA (Αν τα δεδομένα είναι πάνω από 1 ώρα παλιά, προσπέρασε)
                $last_upd_time = strtotime($p['last_update']);
                if (time() - $last_upd_time > 3600) {
                    continue;
                }

                // Έλεγχος αν υπάρχουν τιμές
                if ($priceA <= 0 || $priceB <= 0) continue;

                // --- ΚΕΝΤΡΙΚΟΣ ΕΛΕΓΧΟΣ ΣΗΜΑΤΟΣ (Z-SCORE & BETA) ΜΕ VOLATILITY GUARD ---
                if (abs($z_score) >= $z_threshold) {

                    // 1. VOLATILITY GUARD (PRO FEATURE)
                    // Υπολογίζουμε τη μεταβολή από την τιμή που έχει ήδη η βάση (last_z_score)
                    $prev_z = (float)($p['last_z_score'] ?? 0);
                    $z_delta = abs($z_score - $prev_z);

                    // Αν η μεταβολή είναι ακαριαία και μεγάλη (> 1.2 μονάδες Z), θεωρείται "βρώμικο" spike
                    if ($z_delta > 1.2 && $prev_z != 0) {
                        tlog("⚠️ VOLATILITY ALERT: {$p['asset_a']}/{$p['asset_b']} - Violent Move (ΔZ: ".round($z_delta,2)."). Waiting for stability.");
                        // Ενημερώνουμε το last_update για να μην κολλήσει ο aggregator, αλλά κάνουμε continue (skip trade)
                        $pdo->prepare("UPDATE pair_universe SET last_update = NOW() WHERE id = ?")->execute([$p['id']]);
                        continue;
                    }

// 2. ΣΤΑΤΙΣΤΙΚΟΣ ΚΟΦΤΗΣ BETA (Hedge Ratio Guard)
                    // Επιτρέπουμε πλέον όλα τα cointegrated pairs, αρκεί το beta να μην είναι μηδενικό
                    // ή αρνητικό (το οποίο έχει ήδη φιλτραριστεί από το cron_cointegration.php)
                    if ($beta < 0.0001) {
                        tlog("⚠️ SKIP: {$p['asset_a']}/{$p['asset_b']} - Invalid Beta ($beta).");
                        $pdo->prepare("UPDATE pair_universe SET last_update = NOW() WHERE id = ?")->execute([$p['id']]);
                        continue;
                    }

                    tlog("🚀 SIGNAL: {$p['asset_a']}/{$p['asset_b']} | Z: $z_score | Beta: $beta | ΔZ: ".round($z_delta,2));

                    // 4. ΕΛΕΓΧΟΣ ΑΝ ΤΟ ΖΕΥΓΑΡΙ Η ΤΑ ΝΟΜΙΣΜΑΤΑ ΕΙΝΑΙ ΗΔΗ ΑΝΟΙΧΤΑ
                    $check = $pdo->prepare("SELECT id FROM active_pairs WHERE user_id = ? AND status = 'OPEN' AND (asset_a IN (?, ?) OR asset_b IN (?, ?))");
                    $check->execute([$user_id, $p['asset_a'], $p['asset_b'], $p['asset_a'], $p['asset_b']]);
                    if ($check->fetch()) {
                         tlog("ℹ️ SKIP: {$p['asset_a']}/{$p['asset_b']} - One asset already in open trade."); 
                         $pdo->prepare("UPDATE pair_universe SET last_update = NOW() WHERE id = ?")->execute([$p['id']]);
                         continue;
                    }

                    // 5. ΕΛΕΓΧΟΣ DEMO SYMBOLS (Dynamic check with cache)
                    if ($account_type === 'DEMO') {
                        $demo_symbols_cache = __DIR__ . '/demo_symbols_cache.json';
                        $cache_time = 3600; // 1 hour
                        
                        // Check cache
                        if (file_exists($demo_symbols_cache) && (time() - filemtime($demo_symbols_cache) < $cache_time)) {
                            $demo_available_symbols = json_decode(file_get_contents($demo_symbols_cache), true);
                        } else {
                            // Fetch from Binance testnet API
                            $demo_available_symbols = getBinanceTestnetSymbols();
                            file_put_contents($demo_symbols_cache, json_encode($demo_available_symbols));
                            tlog("🔄 Updated DEMO symbols cache: " . count($demo_available_symbols) . " symbols");
                        }
                        
                        $symbolA = strtoupper($p['asset_a']) . 'USDT';
                        $symbolB = strtoupper($p['asset_b']) . 'USDT';
                        
                        if (!in_array($symbolA, $demo_available_symbols) || !in_array($symbolB, $demo_available_symbols)) {
                            $missing_symbol = !in_array($symbolA, $demo_available_symbols) ? $symbolA : $symbolB;
                            tlog("⚠️ SKIP: {$p['asset_a']}/{$p['asset_b']} - $missing_symbol not available in DEMO testnet (exists in LIVE).");
                            $pdo->prepare("UPDATE pair_universe SET last_update = NOW() WHERE id = ?")->execute([$p['id']]);
                            continue;
                        }
                    }

                    // EXECUTION ENGINE
                    $sideA = ($z_score > 0) ? 'SELL' : 'BUY';
                    $sideB = ($z_score > 0) ? 'BUY' : 'SELL';
                    $posSideA = ($sideA === 'BUY') ? 'LONG' : 'SHORT';
                    $posSideB = ($sideB === 'BUY') ? 'LONG' : 'SHORT';

                    // 1. ΑΝΑΚΤΗΣΗ API KEYS (ΠΡΕΠΕΙ ΝΑ ΓΙΝΕΙ ΠΡΙΝ ΤΟ WALLET CHECK)
                    $stmtK = $pdo->prepare("SELECT api_key, api_secret FROM api_keys WHERE user_id=? AND account_type=? AND is_active=1 LIMIT 1");
                    $stmtK->execute([$user_id, $account_type]);
                    $ak = $stmtK->fetch();
                    if (!$ak) {
                         $pdo->prepare("UPDATE pair_universe SET last_update = NOW() WHERE id = ?")->execute([$p['id']]);
                         continue;
                    }
                    
                    $fK = decrypt_data($ak['api_key']); 
                    $fS = decrypt_data($ak['api_secret']);

                    // 2. Λήψη πραγματικού διαθέσιμου υπολοίπου από Binance (Real-time Wallet Check)
                    $accountInfo = getBinanceAccountInfo($fK, $fS, $account_type);
                    $real_wallet_balance = ($accountInfo !== null) ? $accountInfo['balance'] : 0;

                    // 3. Ορισμός του Margin προς χρήση:
                    // Χρησιμοποιούμε το 95% του Wallet για να μένει πάντα "αέρας" για fees
                    $margin_to_use = min($capital_per_trade, $real_wallet_balance * 0.95);

                    // 6% buffer (0.94) αντί για 3% για να περνάνε σίγουρα οι εντολές  
                    $safe_buying_power = ($margin_to_use * $user_lev) * 0.94;

                    tlog("💰 Wallet Check [{$username}]: Wallet Available: $$real_wallet_balance | Panel Capital: $$capital_per_trade | Using Margin: $$margin_to_use");
                    
                    // --- ΥΠΟΛΟΓΙΣΜΟΣ WEIGHTS ΜΕ SAFETY FLOOR ---
                    $rawWeightA = 1 / (1 + $beta);
                    $weightA = max(0.30, min(0.70, $rawWeightA)); 
                    $weightB = 1 - $weightA;

                    tlog(" Weights [{$username}]: Beta: $beta | WeightA: " . round($weightA, 2) . " | WeightB: " . round($weightB, 2));

                    // Precision Logic with Default Steps
                    $stepA = 1; $stepB = 1; $minQtyA = 0; $minQtyB = 0;
                    
                    // Default precision for common assets
                    $default_steps = [
                        'BTCUSDT' => 0.001,
                        'ETHUSDT' => 0.001,
                        'LINKUSDT' => 0.01,
                        'STXUSDT' => 1,
                        'TIAUSDT' => 1,
                        'ADAUSDT' => 1,
                        'DOTUSDT' => 0.1,
                        'AVAXUSDT' => 1,
                        'ATOMUSDT' => 0.01,
                        'UNIUSDT' => 1,
                        'SOLUSDT' => 0.01,
                        'HBARUSDT' => 1,
                        'ICPUSDT' => 1,
                        'OPUSDT' => 0.1
                    ];
                    
                    if (isset($precisions[$symbolA]) && isset($precisions[$symbolA]['filters'])) {
                        foreach ($precisions[$symbolA]['filters'] as $f) {
                            if ($f['filterType'] === 'LOT_SIZE') { 
                                $stepA = floatval($f['stepSize']); 
                                $minQtyA = floatval($f['minQty']); 
                            }
                        }
                    } else {
                        $stepA = $default_steps[$symbolA] ?? 0.001;
                    }
                    
                    if (isset($precisions[$symbolB]) && isset($precisions[$symbolB]['filters'])) {
                        foreach ($precisions[$symbolB]['filters'] as $f) {
                            if ($f['filterType'] === 'LOT_SIZE') { 
                                $stepB = floatval($f['stepSize']); 
                                $minQtyB = floatval($f['minQty']); 
                            }
                        }
                    } else {
                        $stepB = $default_steps[$symbolB] ?? 0.001;
                    }

                    // 1. Υπολογισμός αξίας βάσει Beta
                    $qtyA_val = ($safe_buying_power * $weightA) / $priceA;
                    $qtyB_val = ($safe_buying_power * $weightB) / $priceB;

                    // 2. Στρογγυλοποίηση βάσει Binance StepSize
                    $qtyA_str = round_step($qtyA_val, $stepA);
                    $qtyB_str = round_step($qtyB_val, $stepB);

                    // 3. Έλεγχος ελάχιστης ποσότητας
                    if ($qtyA_str < $minQtyA) $qtyA_str = $minQtyA;
                    if ($qtyB_str < $minQtyB) $qtyB_str = $minQtyB;

                    // 4. Τελική αξία σε δολάρια (Notional)
                    $valA = $qtyA_str * $priceA;
                    $valB = $qtyB_str * $priceB;
                    $total_notional = $valA + $valB;

                    // --- LIQUIDITY GUARD (PRO FEATURE) ---
                    // Ελέγχουμε αν η θέση μας είναι πολύ μεγάλη για τη ρευστότητα του asset.
                    // Χρησιμοποιούμε το quoteVolume (24h τζίρος) από το cache.
                    $volA = floatval($precisions[$symbolA]['quoteVolume'] ?? 1000000); 
                    $volB = floatval($precisions[$symbolB]['quoteVolume'] ?? 1000000);

                    // Αν το trade μας ξεπερνά το 0.01% του ημερήσιου όγκου, ακυρώνουμε για αποφυγή slippage.
                    $vol_limit = 0.0001; 
                    if (($valA > $volA * $vol_limit) || ($valB > $volB * $vol_limit)) {
                        tlog("⚠️ LIQUIDITY ALERT: {$p['asset_a']}/{$p['asset_b']} - Low 24h Volume (A: $".round($volA/1000)."k, B: $".round($volB/1000)."k). Skipping to prevent slippage.");
                        $pdo->prepare("UPDATE pair_universe SET last_update = NOW() WHERE id = ?")->execute([$p['id']]);
                        continue;
                    }

                    // 7. ΕΛΕΓΧΟΣ MIN NOTIONAL (Binance Safety)
                    if ($valA < 5.5 || $valB < 5.5) { // Αυξήσαμε σε 5.5 για "αέρα"
                        tlog("⚠️ SKIP: {$p['asset_a']}/{$p['asset_b']} - Below Binance Min Notional ($" . round($valA,1) . "/$" . round($valB,1) . ")");
                        $pdo->prepare("UPDATE pair_universe SET last_update = NOW() WHERE id = ?")->execute([$p['id']]);
                        continue; 
                    }

                   
                    // --- ΤΕΛΟΣ BLOCK ΥΠΟΛΟΓΙΣΜΩΝ ---

                    // --- 1. ANTI-FEE PROTECTION (Ο ΚΟΦΤΗΣ) ---
                    $stmt_safety = $pdo->prepare("SELECT COUNT(*) FROM zEQZkBci_binance_trades WHERE user_id = ? AND trade_time > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
                    $stmt_safety->execute([$user_id]);
                    if ($stmt_safety->fetchColumn() >= 8) {
                        tlog("🛑 SAFETY LOCK [{$username}]: Too many trades detected. Skipping to prevent fee drain.");
                        continue; 
                    }

                    // --- 2. PRE-FLIGHT VALIDATION (ΕΛΕΓΧΟΣ ΠΡΙΝ ΤΗΝ ΕΝΤΟΛΗ) ---
                    $minNotionalLimit = 6.0; // Η Binance θέλει > 5$, βάζουμε 6$ για σιγουριά
                    if ($valA < $minNotionalLimit || $valB < $minNotionalLimit) {
                        tlog("⚠️ SKIP: Pair {$p['asset_a']}/{$p['asset_b']} below Min Notional (A:$$valA, B:$$valB). No trade opened.");
                        $pdo->prepare("UPDATE pair_universe SET last_update = NOW() WHERE id = ?")->execute([$p['id']]);
                        continue;
                    }

                    // 3. ΕΠΙΒΟΛΗ LEVERAGE
                    binance_set_leverage($fK, $fS, $symbolA, $user_lev, $account_type);
                    binance_set_leverage($fK, $fS, $symbolB, $user_lev, $account_type);

                    // 4. PRE-FLIGHT PRECISION CHECK (ΠΡΟΛΗΨΗ PARTIAL TRADES)
                    // Τσεκάρουμε αν η ποσότητα είναι συμβατή με το step size για AMBON assets
                    $testQtyA = round_step($qtyA_val, $stepA);
                    $testQtyB = round_step($qtyB_val, $stepB);
                    
                    if ($testQtyA <= 0 || $testQtyB <= 0) {
                        tlog("⚠️ SKIP: Pair {$p['asset_a']}/{$p['asset_b']} - Precision check failed (A: $testQtyA, B: $testQtyB). No trade opened.");
                        $pdo->prepare("UPDATE pair_universe SET last_update = NOW() WHERE id = ?")->execute([$p['id']]);
                        continue;
                    }

                    // 5. ΕΚΤΕΛΕΣΗ ΕΝΤΟΛΩΝ
                    tlog("🚀 OPENING: Asset A ({$symbolA}) for {$username}...");
                    $resA = binance_market_order($fK, $fS, $symbolA, $sideA, $user_lev, $qtyA_str, false, $posSideA, $account_type, $user_id, 'SYSTEM', $z_score);
                    
                    if ($resA['success']) {
                        usleep(1000000); // 1 δευτερόλεπτο αναμονή για το API
                        tlog("🚀 OPENING: Asset B ({$symbolB}) for {$username}...");
                        $resB = binance_market_order($fK, $fS, $symbolB, $sideB, $user_lev, $qtyB_str, false, $posSideB, $account_type, $user_id, 'SYSTEM', $z_score);
                        
                        if ($resB['success']) {
                            // --- 1. ΔΙΟΡΘΩΣΗ FEES (WAIT & FETCH) ---
                            // Περιμένουμε 1.2 δευτερόλεπτα για να προλάβει η Binance να καταγράψει τα trades στο API
                            usleep(1200000); 

                            // Τραβάμε τα ΠΡΑΓΜΑΤΙΚΑ fees χρησιμοποιώντας τα API Keys του χρήστη
                            $tradeDataA = getBinanceTradeData($symbolA, $fK, $fS, $account_type);
                            $tradeDataB = getBinanceTradeData($symbolB, $fK, $fS, $account_type);

                            $realFeeA = $tradeDataA ? floatval($tradeDataA['commission']) : 0;
                            $realFeeB = $tradeDataB ? floatval($tradeDataB['commission']) : 0;
                            $total_fees = $realFeeA + $realFeeB;

                            // --- DUPLICATE CHECK (Race condition fix) ---
                            $stmtDup = $pdo->prepare("SELECT id FROM active_pairs WHERE user_id = ? AND asset_a = ? AND asset_b = ? AND status = 'OPEN' LIMIT 1");
                            $stmtDup->execute([$user_id, $p['asset_a'], $p['asset_b']]);
                            if ($stmtDup->fetch()) {
                                tlog("⚠️ SKIP DUPLICATE: {$p['asset_a']}/{$p['asset_b']} already open for {$username}. Skipping to prevent duplicate.");
                                continue;
                            }

                            // --- 2. ΕΓΓΡΑΦΗ ΣΤΗ ΒΑΣΗ ΔΕΔΟΜΕΝΩΝ (ΠΛΗΡΕΣ ΖΕΥΓΑΡΙ ΜΕ ΣΩΣΤΑ FEES) ---
                            $sqlIn = "INSERT INTO active_pairs (user_id, asset_a, asset_b, side_a, side_b, quantity_a, quantity_b, entry_price_a, entry_price_b, commission_a, commission_b, binance_order_id_a, binance_order_id_b, entry_z_score, beta_used, status, mode, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'OPEN', ?, NOW())";
                            $pdo->prepare($sqlIn)->execute([
                                $user_id, $p['asset_a'], $p['asset_b'], $sideA, $sideB, 
                                $resA['qty'], $resB['qty'], $resA['price'], $resB['price'], 
                                $realFeeA, $realFeeB, 
                                $resA['orderId'], $resB['orderId'], 
                                round($z_score, 4), round($beta, 4), $account_type
                            ]);

                            // --- 3. ΑΠΟΣΤΟΛΗ ΤΕΛΙΚΟΥ ΜΗΝΥΜΑΤΟΣ ΣΤΟ TELEGRAM ---
                            $total_notional = ($resA['qty'] * $resA['price']) + ($resB['qty'] * $resB['price']);
                            $mode_label = ($account_type === 'LIVE') ? "🔵 <b>LIVE</b>" : "🟡 <b>DEMO</b>";

                            $msg = "🚀 <b>NEW PAIR POSITION OPENED</b>\n";
                            $msg .= "📊 Pair: <code>{$p['asset_a']} / {$p['asset_b']}</code>\n";
                            $msg .= "🎯 Entry Z-Score: <b>" . round($z_score, 2) . "</b>\n";
                            $msg .= "⚖️ Hedge Ratio (Beta): <b>" . round($beta, 3) . "</b>\n"; // Η ΝΕΑ ΓΡΑΜΜΗ ΕΔΩ
                            $msg .= "⛽ Entry Fee: <b>$" . number_format($total_fees, 4) . " USDT</b>\n";
                            $msg .= "👤 User: {$username} | {$mode_label}\n";
                            $msg .= "------------------------\n";
                            $msg .= "💰 Total Investment: <b>$" . number_format($total_notional, 2) . "</b>";
                            
                            sendTelegramNotification($msg, $user_id);
                            tlog("✅ SUCCESS: Full Hedge opened for {$p['asset_a']}/{$p['asset_b']} | Fees: $" . number_format($total_fees, 4));
                            $currentOpen++;

                        } else {
                            // --- ΑΠΟΤΥΧΙΑ ΣΤΟ Β: ΚΑΤΑΓΡΑΦΗ ΣΦΑΛΜΑΤΟΣ & LOCK ---
                            tlog("🚨 ALERT: Asset B failed! Asset A stays OPEN. Pair locked.");
                            broadcastLog($pdo, 'CRITICAL', "Hedge Failure: Only {$p['asset_a']} is open. {$p['asset_b']} failed. Pair DISABLED for 4h.", $user_id);
                            
                            $stmtLock = $pdo->prepare("UPDATE pair_universe SET last_error_at = NOW(), last_update = NOW() WHERE id = ?");
                            $stmtLock->execute([$p['id']]);

                            $sqlErr = "INSERT INTO active_pairs (user_id, asset_a, asset_b, side_a, side_b, quantity_a, quantity_b, entry_price_a, entry_price_b, commission_a, commission_b, binance_order_id_a, binance_order_id_b, entry_z_score, status, mode, notes, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'OPEN',?,'LEG_B_FAILED_LOCKED',NOW())";
                            $pdo->prepare($sqlErr)->execute([
                                $user_id, $p['asset_a'], $p['asset_b'], $sideA, NULL, $resA['qty'], NULL, $resA['price'], NULL, ($resA['commission'] ?? 0), NULL, $resA['orderId'], NULL, round($z_score, 4), $account_type
                            ]);
                        }
                    } else {
                        tlog("❌ FAILED: Asset A rejected. No trade started.");
                    }

                    // Ενημέρωση χρόνου τελευταίου ελέγχου (Εντός του IF του σήματος)
                    $pdo->prepare("UPDATE pair_universe SET last_update = NOW() WHERE id = ?")->execute([$p['id']]);

                } else {
                    // Αν δεν υπάρχει σήμα Z-Score (abs(z) < z_threshold)
                    $pdo->prepare("UPDATE pair_universe SET last_update = NOW() WHERE id = ?")->execute([$p['id']]);
                }
            } // Τέλος foreach ($pairs)
        } // Τέλος foreach ($active_users)
    } // Τέλος if (is_array($live_prices))
    usleep(500000); 
} // Τέλος while fast loop

if (file_exists($lock_file)) unlink($lock_file);
tlog("🏁 FAST SCANNER CYCLE COMPLETED.");
?>