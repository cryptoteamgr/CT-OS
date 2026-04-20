<?php
/**
 * CT-OS | copyright by cryptoteam.gr - functions.php
 * ----------------------------------------------------------------
 * Σκοπός: Ο κεντρικός πυρήνας (Master Kernel) του συστήματος. Περιέχει τη μαθηματική λογική, 
 * την ασφάλεια κρυπτογράφησης και την επικοινωνία με το Binance API.
 */
date_default_timezone_set('Europe/Athens');
// --- 0. LOGGING ENGINE (ΚΡΙΣΙΜΟ ΓΙΑ DEBUGGING) ---
if (!function_exists('tlog')) {
    function tlog($msg) {
        $formatted = "[" . date("H:i:s") . "] " . $msg . "\n";
        echo $formatted; // Εμφάνιση στο SSH
        file_put_contents(__DIR__ . '/cron_log.txt', $formatted, FILE_APPEND); // Εγγραφή στο αρχείο
    }
}

// 1. ΣΤΑΤΙΣΤΙΚΕΣ ΣΥΝΑΡΤΗΣΕΙΣ
if (!function_exists('calculate_mean')) {
    function calculate_mean($data) { return empty($data) ? 0 : array_sum($data) / count($data); }
}

if (!function_exists('calculate_sd')) {
    function calculate_sd($data) {
        $count = count($data);
        if ($count <= 1) return 0;
        $mean = calculate_mean($data);
        $variance = 0.0;
        foreach ($data as $val) { $variance += pow(((float)$val - $mean), 2); }
        return sqrt($variance / $count);
    }
}

// --- ΚΕΝΤΡΙΚΟΙ ΥΠΟΛΟΓΙΣΜΟΙ ΒΕΤΑ WEIGHTING ---
if (!function_exists('calculateBetaWeighting')) {
    function calculateBetaWeighting($quantity_a, $quantity_b, $price_a, $price_b, $leverage) {
        $val_a = round($quantity_a * $price_a * $leverage, 2);
        $val_b = round($quantity_b * $price_b * $leverage, 2);
        $total = $val_a + $val_b;
        
        return [
            'val_a' => $val_a,
            'val_b' => $val_b,
            'total' => $total,
            'leverage' => $leverage
        ];
    }
}

if (!function_exists('calculatePnL')) {
    function calculatePnL($quantity_a, $quantity_b, $entry_price_a, $entry_price_b, $current_price_a, $current_price_b, $side_a, $side_b, $leverage = 1) {
        $pnl_a = (strtoupper($side_a) === 'BUY') 
            ? ($current_price_a - $entry_price_a) * $quantity_a * $leverage
            : ($entry_price_a - $current_price_a) * $quantity_a * $leverage;
        
        $pnl_b = (strtoupper($side_b) === 'BUY') 
            ? ($current_price_b - $entry_price_b) * $quantity_b * $leverage
            : ($entry_price_b - $current_price_b) * $quantity_b * $leverage;
        
        return round($pnl_a + $pnl_b, 4);
    }
}

if (!function_exists('calculateExposure')) {
    function calculateExposure($capital, $leverage) {
        return $capital * $leverage;
    }
}

if (!function_exists('calculateQuantityFromCapital')) {
    function calculateQuantityFromCapital($capital, $leverage, $price_a, $price_b, $beta) {
        $exposure = calculateExposure($capital, $leverage);
        
        // Υπολογισμός weights με beta
        $weight_a = 1 / (1 + $beta);
        $weight_b = $beta / (1 + $beta);
        
        // Quantity για κάθε asset
        $qty_a = ($exposure * $weight_a) / $price_a;
        $qty_b = ($exposure * $weight_b) / $price_b;
        
        return [
            'quantity_a' => round($qty_a, 8),
            'quantity_b' => round($qty_b, 8),
            'weight_a' => round($weight_a, 4),
            'weight_b' => round($weight_b, 4)
        ];
    }
}

if (!function_exists('calculate_zscore')) {
    function calculate_zscore($curr, $hist) {
        // Ασφάλεια: Αν έχουμε λιγότερα από 30 δείγματα, το Z-Score είναι αναξιόπιστο
        if (count((array)$hist) < 30) return 0; 
        
        $m = calculate_mean($hist); $s = calculate_sd($hist);
        return ($s == 0) ? 0 : ($curr - $m) / $s;
    }
}

if (!function_exists('calculate_beta')) {
    function calculate_beta($dataA, $dataB) {
        $returnsA = []; $returnsB = [];
        // Εφόσον τα data είναι ήδη arrays με τιμές (closes), παίρνουμε το count απευθείας
        $len = min(count((array)$dataA), count((array)$dataB));
if ($len < 2) return 1.0;
        
        for ($i = 1; $i < $len; $i++) {
            $prevA = floatval($dataA[$i-1]); // ΔΙΟΡΘΩΣΗ: Όχι [$i-1][4]
            $prevB = floatval($dataB[$i-1]); // ΔΙΟΡΘΩΣΗ: Όχι [$i-1][4]
            
            if ($prevA > 0 && $prevB > 0) {
                $returnsA[] = (floatval($dataA[$i]) - $prevA) / $prevA;
                $returnsB[] = (floatval($dataB[$i]) - $prevB) / $prevB;
            }
        }
        
        if (empty($returnsA) || empty($returnsB)) return 1.0;
        
        $meanA = calculate_mean($returnsA);
        $meanB = calculate_mean($returnsB);
        $num = 0; $den = 0;
        
        for ($i = 0; $i < count($returnsA); $i++) {
            $num += ($returnsA[$i] - $meanA) * ($returnsB[$i] - $meanB);
            $den += pow(($returnsB[$i] - $meanB), 2);
        }
        
        // Επιστρέφουμε το Beta (συσχέτιση μεταβλητότητας)
        return ($den != 0) ? abs($num / $den) : 1.0;
    }
}

// 2. SECURITY & ENCRYPTION
if (!function_exists('decrypt_data')) {
    function decrypt_data($data) {
        if (empty($data)) return "";
        $key = getenv('ENCRYPTION_KEY') ?: 'zEQZkBci_algo_secure_key';
        $method = "aes-256-cbc";
        $data = base64_decode($data);
        $iv_length = openssl_cipher_iv_length($method);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        $decrypted = openssl_decrypt($encrypted, $method, $key, 0, $iv);
        return $decrypted ?: $data;
    }
}

if (!function_exists('get_binance_error_msg')) {
    function get_binance_error_msg($code) {
        $errors = [
            "-1013" => "Invalid Quantity (Filter Failure: LOT_SIZE). Check your capital/leverage.",
            "-2010" => "Insufficient Balance. You don't have enough USDT for this trade.",
            "-2022" => "ReduceOnly Rejected. Position already closed or quantity mismatch.",
            "-2011" => "Cancel Rejected. Order not found.",
            "-1111" => "Precision Overlap. Check Step Size in functions.php.",
            "-1001" => "Internal Service Error. Binance is lagging.",
            "-4046" => "No Need to Unhedge. Position is already in the correct state.",
            "-1102" => "Mandatory parameter 'quantity' was not sent or is invalid."
        ];
        return $errors[(string)$code] ?? "API Error: Consult Binance Documentation.";
    }
}

if (!function_exists('encrypt_data')) {
    function encrypt_data($data) {
        if (empty($data)) return "";
        $key = getenv('ENCRYPTION_KEY') ?: 'zEQZkBci_algo_secure_key';
        $method = "aes-256-cbc";
        $iv_length = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($iv_length);
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
        return base64_encode($iv . $encrypted) ?: $data;
    }
}

// 3. BINANCE EXECUTION ENGINE (9 Parameters with Precision Fix)
if (!function_exists('round_step')) {
    function round_step($value, $stepSize) {
        $stepSize = (float)$stepSize;
        if ($stepSize <= 0) return (string)$value;
        
        // Μετατροπή σε string για να αποφύγουμε το Scientific Notation (π.χ. 1e-5)
        $stepSizeStr = sprintf('%.8f', $stepSize);
        $stepSizeClean = rtrim($stepSizeStr, '0');
        
        $pos = strpos($stepSizeClean, '.');
        $precision = ($pos !== false) ? strlen(substr($stepSizeClean, $pos + 1)) : 0;
        
        // Στρογγυλοποίηση στο πλησιέστερο stepSize με χρήση floor(round()) για ακρίβεια
        $enforced = floor(round($value / $stepSize, 10)) * $stepSize;
        
        $result = number_format($enforced, $precision, '.', '');
        
        // Ασφάλεια: Αν η στρογγυλοποίηση έβγαλε 0, επιστρέφουμε 0 (δεν θέλουμε να αναγκάσουμε min step)
        if ((float)$result <= 0) {
            return '0';
        }
        
        return $result;
    }
} 

if (!function_exists('binance_market_order')) {
    function binance_market_order($key, $sec, $symbol, $side, $lev, $qty_or_cost, $is_close, $posSide, $mode = 'DEMO', $user_id = null, $reason = 'SYSTEM', $current_zscore = null) {
        global $pdo;
        $base = ($mode === 'LIVE') ? "https://fapi.binance.com" : "https://testnet.binancefuture.com";
        
        // 1. ΑΝΑΚΤΗΣΗ ΜΟΝΟ ΤΟΥ USERNAME (Για το TLOG)
        $username = 'Unknown';
        if ($user_id) {
            $stmtU = $pdo->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
            $stmtU->execute([$user_id]);
            $username = $stmtU->fetchColumn() ?: 'Unknown';
        }

        // --- ΔΙΟΡΘΩΣΗ PRECISION (Margin Fix) ---
        // Κόβουμε τα δεκαδικά στα 2 ή 3 ψηφία (floor) για να μην υπερβαίνουμε ΠΟΤΕ το διαθέσιμο margin
        $raw_qty = (float)$qty_or_cost;
        if ($raw_qty < 1) {
            $final_qty_val = floor($raw_qty * 1000) / 1000; // 3 δεκαδικά για μικρές ποσότητες
        } else {
            $final_qty_val = floor($raw_qty * 100) / 100;   // 2 δεκαδικά για μεγαλύτερες
        }
        $clean_qty = number_format($final_qty_val, ($raw_qty < 1 ? 3 : 2), '.', '');

        // Ορισμός παραμέτρων
        $o_params = [
            'symbol'   => strtoupper($symbol), 
            'side'     => strtoupper($side), 
            'type'     => 'MARKET',
            'quantity' => $clean_qty, 
            'timestamp' => number_format(microtime(true) * 1000, 0, '.', ''), 
            'recvWindow' => 20000 
        ];
        
        // ΔΙΟΡΘΩΣΗ: Αν ο χρήστης είναι σε One-Way Mode, το positionSide ΠΡΕΠΕΙ να λείπει.
        // Αν είναι σε Hedge Mode, το στέλνουμε κανονικά. 
        // Για να παίζει παντού, αν το positionSide είναι BOTH, δεν το στέλνουμε καθόλου.
        if (strtoupper($posSide) !== 'BOTH' && !empty($posSide)) {
            $o_params['positionSide'] = strtoupper($posSide);
        }

        $query = http_build_query($o_params);
        $sig = hash_hmac('sha256', $query, $sec);
        
// Στέλνουμε τα πάντα στο URL για να είμαστε σίγουροι 100%
        $full_url = $base . "/fapi/v1/order?" . $query . "&signature=" . $sig;

        // --- TLOG: ΚΑΤΑΓΡΑΦΗ ΠΡΙΝ ΤΗΝ ΑΠΟΣΤΟΛΗ ---
        tlog("📡 USER: $username | SENDING TO BINANCE: $symbol $side | Qty: $clean_qty");

        $ch = curl_init($full_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $key"]);
        curl_setopt($ch, CURLOPT_POST, true); // Παραμένει POST
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Timeout για να μην κολλάει το bot
        
        $response_raw = curl_exec($ch);
        $res = json_decode($response_raw, true); 
        curl_close($ch);

        // --- TLOG: ΚΑΤΑΓΡΑΦΗ ΑΠΟΤΕΛΕΣΜΑΤΟΣ ---
        if (isset($res['orderId'])) {
            tlog("✅ BINANCE SUCCESS [$symbol]: OrderID: " . $res['orderId'] . " | Status: " . ($res['status'] ?? 'FILLED'));
        } else {
            tlog("❌ BINANCE ERROR [$symbol]: " . $response_raw);
        }

        // 3. ΕΠΕΞΕΡΓΑΣΙΑ ΑΠΟΤΕΛΕΣΜΑΤΟΣ (WEIGHTED AVERAGE PRICE & FEES)
        if (isset($res['orderId'])) {
            $totalQty = 0;
            $totalCost = 0;
            $totalCommission = 0;

            // Αν η Binance επιστρέψει fills, τα αθροίζουμε
            if (isset($res['fills']) && is_array($res['fills'])) {
                foreach ($res['fills'] as $fill) {
                    $fQty = (float)$fill['qty'];
                    $fPrice = (float)$fill['price'];
                    $totalQty += $fQty;
                    $totalCost += ($fQty * $fPrice);
                    
                    $comm = (float)($fill['commission'] ?? 0);
                    $asset = strtoupper($fill['commissionAsset'] ?? 'USDT');
                    
                    // Αν το fee πληρώθηκε σε BNB, το μετατρέπουμε σε USDT (τιμή ~600)
                    if ($asset === 'BNB' && $comm > 0) {
                        $totalCommission += ($comm * 600);
                    } else {
                        $totalCommission += $comm;
                    }
                }
            }

            // --- CRITICAL FIX: Αν η Binance επιστρέψει 0, τράβα το από το Trade History της βάσης ---
            if ($totalCommission <= 0 && $user_id) {
                // Περιμένουμε 500ms για να προλάβει η Binance να ενημερώσει το API
                usleep(500000);
                $history = getBinanceTradeData($symbol, $key, $sec, $mode);
                if ($history && $history['commission'] > 0) {
                    $totalCommission = $history['commission'];
                }
            }

            // Υπολογισμός Weighted Average Price
            if ($totalQty > 0) {
                $finalPrice = $totalCost / $totalQty;
            } else {
                $finalPrice = (isset($res['avgPrice']) && (float)$res['avgPrice'] > 0) ? (float)$res['avgPrice'] : 0;
            }
            
            // Fallback στην τοπική cache τιμών αν όλα τα παραπάνω αποτύχουν
            if ($finalPrice <= 0 && isset($res['symbol'])) {
                 $cacheFile = __DIR__ . '/prices_cache.json';
                 $localPrices = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];
                 $finalPrice = $localPrices[$res['symbol']] ?? 0;
            }

            $finalQty = (isset($res['executedQty']) && (float)$res['executedQty'] > 0) 
                         ? (float)$res['executedQty'] 
                         : (isset($res['origQty']) ? (float)$res['origQty'] : 0);

         
        // ΕΠΙΣΤΡΟΦΗ ΔΕΔΟΜΕΝΩΝ
            return [
                'success'    => true, 
                'price'      => $finalPrice, 
                'qty'        => $finalQty, 
                'orderId'    => $res['orderId'],
                'commission' => $totalCommission
            ];
        } // <--- ΕΔΩ ΚΛΕΙΝΕΙ ΤΟ if (isset($res['orderId']))
        
        tlog("❌ Binance API Error for $symbol: " . json_encode($res)); 
        return ['success' => false, 'msg' => $res['msg'] ?? 'Order failed', 'code' => $res['code'] ?? 0];
    } 
} 

if (!function_exists('binance_get_position')) {
    function binance_get_position($key, $sec, $symbol, $mode = 'DEMO') {
        $base = ($mode === 'LIVE') ? "https://fapi.binance.com" : "https://testnet.binancefuture.com";
        
        // Μείωση delay στα 100ms για ταχύτερη απόκριση στον Fast Scanner
        usleep(100000);

        // ΔΙΟΡΘΩΣΗ: number_format αντί για round για να μην στέλνει Scientific Notation (1.7E+12)
        $timestamp = number_format(microtime(true) * 1000, 0, '.', '');
        $params = ['symbol' => $symbol, 'timestamp' => $timestamp];
        $sig = hash_hmac('sha256', http_build_query($params), $sec);
        
        $ch = curl_init($base . "/fapi/v2/positionRisk?" . http_build_query($params) . "&signature=" . $sig);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $key"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res_raw = curl_exec($ch);
        curl_close($ch);

        $res = json_decode($res_raw, true);

        // ΚΡΙΣΙΜΗ ΔΙΟΡΘΩΣΗ: Έλεγχος αν το response είναι πίνακας
        if (is_array($res)) {
            foreach ($res as $pos) {
                // Έλεγχος αν κάθε στοιχείο του πίνακα είναι επίσης πίνακας (αποφυγή TypeError)
                if (is_array($pos) && isset($pos['symbol']) && $pos['symbol'] === $symbol && floatval($pos['positionAmt'] ?? 0) != 0) {
                    return [
                        'quantity' => abs(floatval($pos['positionAmt'])), 
                        'side'     => floatval($pos['positionAmt']) > 0 ? 'LONG' : 'SHORT', 
                        'entry'    => floatval($pos['entryPrice'] ?? 0),
                        'positionSide' => $pos['positionSide'] ?? 'BOTH',
                        'unrealizedPnl' => floatval($pos['unRealizedProfit'] ?? 0)
                    ];
                }
            }
        }
        return ['quantity' => 0, 'side' => 'NONE', 'entry' => 0, 'positionSide' => 'BOTH'];
    }
}

// 4. NOTIFICATIONS & LOGS (MULTI-USER & TABLE SYNC)
if (!function_exists('sendTelegramNotification')) {
        function sendTelegramNotification($msg, $user_id = null) {
            tlog("📱 Telegram Attempt for User $user_id...");
            
        global $pdo;
        
        $userToken = '';
        $userChatId = '';
        $adminToken = defined('TG_BOT_TOKEN') ? TG_BOT_TOKEN : '';
        $adminChatId = defined('TG_ADMIN_ID') ? TG_ADMIN_ID : '';
        $mode = (strpos($msg, 'LIVE') !== false) ? 'LIVE' : 'DEMO';

        try {
            // 1. Λήψη στοιχείων χρήστη
            if ($user_id) {
                $stmt = $pdo->prepare("SELECT api_token, chat_id FROM telegram_bots WHERE user_id = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$user_id]);
                $s = $stmt->fetch();
                if ($s) {
                    $userToken = decrypt_data($s['api_token']);
                    $userChatId = decrypt_data($s['chat_id']);
                }
            }

            // --- ΛΟΓΙΚΗ ΑΠΟΣΤΟΛΗΣ ---

            // Α. ΑΠΟΣΤΟΛΗ ΣΤΟΝ ΧΡΗΣΤΗ (Στέλνουμε τα πάντα: LIVE & DEMO)
            if (!empty($userToken) && !empty($userChatId)) {
                $url = "https://api.telegram.org/bot" . $userToken . "/sendMessage";
                $postData = ['chat_id' => $userChatId, 'text' => $msg, 'parse_mode' => 'HTML'];
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                curl_exec($ch);
                curl_close($ch);
            }

            // Β. ΑΠΟΣΤΟΛΗ ΣΤΟΝ ADMIN (ΕΣΕΝΑ)
            // Συνθήκη: Στέλνουμε αν είναι LIVE ΠΑΝΤΟΥ ή αν το trade ανήκει στον ADMIN (user_id 1)
            $isAdminTrade = ($user_id == 1); // Υποθέτοντας ότι το δικό σου ID είναι 1
            
            if (!empty($adminToken) && !empty($adminChatId)) {
                if ($mode === 'LIVE' || $isAdminTrade) {
                    // Αποφυγή διπλής αποστολής αν ο admin είναι και ο χρήστης
                    if ($userChatId != $adminChatId) {
                        $url = "https://api.telegram.org/bot" . $adminToken . "/sendMessage";
                        $postData = ['chat_id' => $adminChatId, 'text' => $msg, 'parse_mode' => 'HTML'];
                        
                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                        curl_exec($ch);
                        curl_close($ch);
                    }
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Telegram API fail: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getBinanceAccountInfo')) {
    function getBinanceAccountInfo($key, $sec, $mode) {
        $base = ($mode === 'LIVE') ? "https://fapi.binance.com" : "https://testnet.binancefuture.com";
        $params = ['timestamp' => number_format(microtime(true) * 1000, 0, '.', '')];
        $query = http_build_query($params);
        $sig = hash_hmac('sha256', $query, $sec);
        
        $ch = curl_init($base . "/fapi/v2/account?" . $query . "&signature=" . $sig);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $key"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $res = json_decode($response, true);
        curl_close($ch);

        // Χρησιμοποιούμε το totalMarginBalance για το Equity και το totalWalletBalance για το Balance
        if (isset($res['totalMarginBalance'])) {
            return [
                'balance'     => (float)($res['totalWalletBalance'] ?? 0),
                'equity'      => (float)$res['totalMarginBalance'],
                'maintMargin' => (float)($res['totalMaintMargin'] ?? 0)
            ];
        }
        return null;
    }
}

function binance_set_leverage($key, $sec, $symbol, $leverage, $mode = 'DEMO') {
    $base = ($mode === 'LIVE') ? "https://fapi.binance.com" : "https://testnet.binancefuture.com";
    $endpoint = "/fapi/v1/leverage";
    $timestamp = number_format(microtime(true) * 1000, 0, '.', '');
    
    $params = [
        'symbol' => strtoupper($symbol),
        'leverage' => intval($leverage),
        'timestamp' => $timestamp
    ];
    
    $query = http_build_query($params);
    $sig = hash_hmac('sha256', $query, $sec);
    
    $ch = curl_init($base . $endpoint . '?' . $query . '&signature=' . $sig);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $key"]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

if (!function_exists('getBinanceTradeData')) {
    function getBinanceTradeData($symbol, $api_key, $api_secret, $mode) {
        if (empty($api_key) || empty($api_secret)) return null;

        if (!str_ends_with(strtoupper($symbol), 'USDT')) {
            $symbol = strtoupper($symbol) . 'USDT';
        }

        $base_url = ($mode === 'LIVE') ? "https://fapi.binance.com" : "https://testnet.binancefuture.com";
        $endpoint = "/fapi/v1/userTrades";
        
        // Προσθήκη Retry Loop: Αν η Binance δεν έχει προλάβει να καταγράψει το trade, 
        // δοκιμάζουμε έως 2 φορές με μικρή καθυστέρηση.
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $timestamp = number_format(microtime(true) * 1000, 0, '.', '');
            // Αυξημένο recvWindow στα 20000 για αποφυγή Time Sync Errors σε Live περιβάλλον
            $query = "symbol=" . strtoupper($symbol) . "&limit=5&recvWindow=20000&timestamp=" . $timestamp;
            $signature = hash_hmac('sha256', $query, $api_secret);
            
            $url = $base_url . $endpoint . "?" . $query . "&signature=" . $signature;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-MBX-APIKEY: ' . $api_key]);
            
            // --- ΠΡΟΣΘΗΚΗ TIMEOUTS ΓΙΑ ΑΠΟΦΥΓΗ FREEZE ---
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Χρόνος σύνδεσης
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);        // Συνολικός χρόνος αναμονής
            
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            
            $response = curl_exec($ch);
            
            // Έλεγχος για σφάλμα CURL
            if (curl_errno($ch)) {
                $error_msg = curl_error($ch);
                if (function_exists('tlog')) tlog("⚠️ Attempt $attempt: CURL Error on $symbol: " . $error_msg);
            }
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode($response, true);
if ($httpCode === 200 && is_array($data) && count($data) > 0) {
    $lastTrade = end($data);
    $comm = (float)($lastTrade['commission'] ?? 0);
    
    // Αν το fee είναι 0, περίμενε 0.5 δευτερόλεπτο και ξαναδοκίμασε ΜΙΑ φορά
    if ($comm <= 0) {
        usleep(500000); 
        // Εδώ θα μπορούσες να ξανακάνεις το curl call για το history
    }

    return [
        'price' => (float)($lastTrade['price'] ?? 0),
        'commission' => $comm
    ];
}
            
            // Αν αποτύχει η πρώτη προσπάθεια, περίμενε 1 δευτερόλεπτο πριν τη δεύτερη
            if ($attempt < 2) {
                usleep(1000000); 
            }
        } // ΕΔΩ ΚΛΕΙΝΕΙ ΤΟ FOR LOOP

        return null;
    } // ΕΔΩ ΚΛΕΙΝΕΙ Η FUNCTION
} // ΕΔΩ ΚΛΕΙΝΕΙ ΤΟ IF !FUNCTION_EXISTS

// 5. BROADCAST LOG FUNCTION
if (!function_exists('broadcastLog')) {
    function broadcastLog($pdo, $type, $message, $user_id = 0) {
        $allowed = ['INFO', 'ERROR', 'SUCCESS', 'TRADE', 'WARNING', 'CRITICAL'];
        $type = strtoupper($type);
        if (!in_array($type, $allowed)) $type = 'INFO';

        try {
            $stmt = $pdo->prepare("INSERT INTO system_notifications (user_id, type, message, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$user_id, $type, $message]);
        } catch (Exception $e) {
            error_log("Broadcast failed: " . $e->getMessage());
        }
    }
}
?>