<?php
/**
 * CT-OS | copyright by cryptoteam.gr - cron_monitor.php (PRO VERSION - TIME EXIT ENABLED)
 */

// --- 1. LOCK FILE (Double run protection) ---
$lock_file = __DIR__ . '/monitor.lock';
if (file_exists($lock_file) && (time() - filemtime($lock_file) < 300)) {
    // Αν το προηγούμενο script τρέχει ήδη λιγότερο από 5 λεπτά, σταμάτα.
    die("Error: Monitor is already running.\n");
}
file_put_contents($lock_file, "running");

// Διασφάλιση ότι το lock θα σβηστεί ακόμα και αν το script κρασάρει
register_shutdown_function(function() use ($lock_file) {
    if (file_exists($lock_file)) unlink($lock_file);
});

// --- 2. REQUIRES & CONFIG ---
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/bot_engine.php'; 

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') { 
    session_start(); 
}
session_write_close();

// Live Output Buffering Fix
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
while (ob_get_level()) ob_end_clean();

error_reporting(E_ALL);
ini_set('display_errors', 1);

function tlog($msg) {
    // Only log important events (errors, trades, status changes)
    $important_keywords = ['ERROR', '✅', '❌', '🔥', 'CLOSED', 'OPENED', 'PROFIT', 'LOSS'];
    $is_important = false;
    
    foreach ($important_keywords as $keyword) {
        if (stripos($msg, $keyword) !== false) {
            $is_important = true;
            break;
        }
    }
    
    // Always log errors and trade actions
    if ($is_important || stripos($msg, 'ERROR') !== false || stripos($msg, 'TRADE') !== false) {
        $formatted = "[" . date("H:i:s") . "] " . $msg . "\n";
        echo $formatted;
        file_put_contents(__DIR__ . '/cron_log.txt', $formatted, FILE_APPEND);
    } else {
        // Only echo to console, don't write to file
        echo "[" . date("H:i:s") . "] " . $msg . "\n";
    }
}

function get_live_qty($key, $sec, $symbol, $pside, $mode) {
    $url = ($mode === 'LIVE') ? "https://fapi.binance.com/fapi/v2/positionRisk" : "https://testnet.binancefuture.com/fapi/v2/positionRisk";
    usleep(200000); 
    $timestamp = number_format(microtime(true) * 1000, 0, '.', '');
    $params = ['symbol' => $symbol, 'timestamp' => $timestamp];
    $query = http_build_query($params);
    $sig = hash_hmac('sha256', $query, $sec);
    
    $ch = curl_init($url . '?' . $query . '&signature=' . $sig);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-MBX-APIKEY: $key"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) return false;
    $res = json_decode($response, true);
    if (!is_array($res)) return false; 

    foreach ($res as $p) {
        if (is_array($p) && isset($p['symbol']) && $p['symbol'] === $symbol) {
            $pSideResponse = $p['positionSide'] ?? '';
            if (strtoupper($pSideResponse) === strtoupper($pside)) {
                return abs(floatval($p['positionAmt'] ?? 0));
            }
        }
    }
    return 0;
}

if (!function_exists('get_live_pnl')) {
    function get_live_pnl($trade, $prices, $leverage = 5) {
        $symA = strtoupper($trade['asset_a'] . "USDT");
        $symB = strtoupper($trade['asset_b'] . "USDT");
        if (!isset($prices[$symA]) || !isset($prices[$symB])) return null; 
        $pnlA = (strtoupper($trade['side_a']) === 'BUY') 
            ? ($prices[$symA] - floatval($trade['entry_price_a'])) * floatval($trade['quantity_a'])
            : (floatval($trade['entry_price_a']) - $prices[$symA]) * floatval($trade['quantity_a']);
        $pnlB = (strtoupper($trade['side_b']) === 'BUY') 
            ? ($prices[$symB] - floatval($trade['entry_price_b'])) * floatval($trade['quantity_b'])
            : (floatval($trade['entry_price_b']) - $prices[$symB]) * floatval($trade['quantity_b']);
        return round(($pnlA + $pnlB) / $leverage, 4);
    }
}

/**
 * Check Alerts Function - Integrated Alert System
 */
function checkAlerts($pdo) {
    // Get all active alerts
    $stmt = $pdo->prepare("SELECT * FROM user_alerts WHERE is_active = 1");
    $stmt->execute();
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($alerts as $alert) {
        $user_id = $alert['user_id'];
        $alert_type = $alert['alert_type'];
        $threshold = $alert['threshold'];
        
        // Get user mode
        $stmtUser = $pdo->prepare("SELECT bot_mode FROM users WHERE id = ?");
        $stmtUser->execute([$user_id]);
        $user = $stmtUser->fetch();
        $mode = $user['bot_mode'] ?? 'DEMO';
        
        try {
            switch ($alert_type) {
                case 'z_score':
                    checkZScoreAlert($pdo, $alert, $user_id, $mode, $threshold);
                    break;
                case 'correlation':
                    checkCorrelationAlert($pdo, $alert, $user_id, $mode, $threshold);
                    break;
                case 'drawdown':
                    checkDrawdownAlert($pdo, $alert, $user_id, $mode, $threshold);
                    break;
                case 'profit_target':
                    checkProfitTargetAlert($pdo, $alert, $user_id, $mode, $threshold);
                    break;
            }
        } catch (Exception $e) {
            error_log("Alert handler error for user $user_id: " . $e->getMessage());
        }
    }
}

/**
 * Check Z-Score Alert
 */
function checkZScoreAlert($pdo, $alert, $user_id, $mode, $threshold) {
    $stmt = $pdo->prepare("SELECT asset_a, asset_b, last_z_score FROM pair_universe WHERE is_active = 1 AND is_cointegrated = 1 AND ABS(last_z_score) >= ?");
    $stmt->execute([$threshold]);
    $pairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($pairs as $pair) {
        $z_score = abs($pair['last_z_score']);
        if ($z_score >= $threshold) {
            $message = "🔔 Z-Score Alert: {$pair['asset_a']}/{$pair['asset_b']} Z = {$pair['last_z_score']} (Threshold: {$threshold})";
            triggerAlert($pdo, $alert, $user_id, $alert['alert_type'], $message, $threshold, $pair['last_z_score']);
        }
    }
}

/**
 * Check Correlation Alert
 */
function checkCorrelationAlert($pdo, $alert, $user_id, $mode, $threshold) {
    $stmt = $pdo->prepare("SELECT asset_a, asset_b, correlation FROM pair_universe WHERE is_active = 1 AND correlation < ?");
    $stmt->execute([$threshold]);
    $pairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($pairs as $pair) {
        $message = "🔔 Correlation Alert: {$pair['asset_a']}/{$pair['asset_b']} Correlation = {$pair['correlation']}% (Threshold: {$threshold}%)";
        triggerAlert($pdo, $alert, $user_id, $alert['alert_type'], $message, $threshold, $pair['correlation']);
    }
}

/**
 * Check Drawdown Alert
 */
function checkDrawdownAlert($pdo, $alert, $user_id, $mode, $threshold) {
    $stmt = $pdo->prepare("SELECT SUM(final_pnl) as total_pnl FROM active_pairs WHERE user_id = ? AND status = 'CLOSED' AND mode = ?");
    $stmt->execute([$user_id, $mode]);
    $result = $stmt->fetch();
    $total_pnl = $result['total_pnl'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT MAX(final_pnl) as max_pnl FROM active_pairs WHERE user_id = ? AND status = 'CLOSED' AND mode = ?");
    $stmt->execute([$user_id, $mode]);
    $result = $stmt->fetch();
    $max_pnl = $result['max_pnl'] ?? 0;
    
    if ($max_pnl > 0) {
        $drawdown = (($max_pnl - $total_pnl) / $max_pnl) * 100;
        
        if ($drawdown >= $threshold) {
            $message = "🔔 Drawdown Alert: Current drawdown = " . number_format($drawdown, 1) . "% (Threshold: {$threshold}%)";
            triggerAlert($pdo, $alert, $user_id, $alert['alert_type'], $message, $threshold, $drawdown);
        }
    }
}

/**
 * Check Profit Target Alert
 */
function checkProfitTargetAlert($pdo, $alert, $user_id, $mode, $threshold) {
    $stmt = $pdo->prepare("SELECT SUM(final_pnl) as today_pnl FROM active_pairs WHERE user_id = ? AND status = 'CLOSED' AND mode = ? AND DATE(closed_at) = CURDATE()");
    $stmt->execute([$user_id, $mode]);
    $result = $stmt->fetch();
    $today_pnl = $result['today_pnl'] ?? 0;
    
    if ($today_pnl >= $threshold) {
        $message = "🔔 Profit Target Alert: Today's profit = $" . number_format($today_pnl, 2) . " (Target: \${$threshold})";
        triggerAlert($pdo, $alert, $user_id, $alert['alert_type'], $message, $threshold, $today_pnl);
    }
}

/**
 * Trigger Alert (Telegram Only)
 */
function triggerAlert($pdo, $alert, $user_id, $alert_type, $message, $threshold_value, $current_value) {
    // Check cooldown (don't trigger same alert within 10 minutes)
    $stmt = $pdo->prepare("SELECT id FROM alert_history WHERE alert_id = ? AND triggered_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    $stmt->execute([$alert['id']]);
    if ($stmt->fetch()) {
        return;
    }
    
    // Log alert
    $stmt = $pdo->prepare("INSERT INTO alert_history (user_id, alert_id, alert_type, message, threshold_value, current_value, triggered_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $alert['id'], $alert_type, $message, $threshold_value, $current_value]);
    
    // Update last triggered time
    $stmt = $pdo->prepare("UPDATE user_alerts SET last_triggered_at = NOW() WHERE id = ?");
    $stmt->execute([$alert['id']]);
    
    // Send Telegram notification only
    sendTelegramNotification($message, $user_id);
}

$script_start_time = time();
$execution_limit = 50; 
$last_balance_sync = 0; 

// 🔴 TEMPORARILY DISABLED - SMART TIME EXIT loop for user 24
tlog("🔴 MONITOR DISABLED TEMPORARILY (SMART TIME EXIT loop)");
exit;

tlog("🚀 PRO MONITOR STARTING (Fast Loop Mode)");

while (time() - $script_start_time < $execution_limit) {
    
    $cacheFile = __DIR__ . '/prices_cache.json';
    $prices = json_decode(@file_get_contents($cacheFile), true);
    
    if (!$prices || !is_array($prices)) {
        usleep(1000000);
        continue;
    }

    // 3. ΕΛΕΓΧΟΣ ΑΝΟΙΧΤΩΝ TRADES
    $stmt = $pdo->prepare("SELECT * FROM active_pairs WHERE status = 'OPEN'");
    $stmt->execute();
    $activeTrades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($activeTrades as $trade) {
        usleep(50000); 
        try {
            $symA = strtoupper($trade['asset_a'] . "USDT");
            $symB = strtoupper($trade['asset_b'] . "USDT");

            $currentPnL = get_live_pnl($trade, $prices, $settings['leverage'] ?? 5);
            if ($currentPnL === null) continue;

            $stmtZ = $pdo->prepare("SELECT last_z_score FROM pair_universe WHERE asset_a = ? AND asset_b = ? LIMIT 1");
            $stmtZ->execute([$trade['asset_a'], $trade['asset_b']]);
            $current_z = (float)$stmtZ->fetchColumn();

            $user_id = $trade['user_id'];
            $mode    = $trade['mode'] ?: 'DEMO';

            $userStmt = $pdo->prepare("SELECT username, tp_dollar, sl_dollar, z_exit_threshold, leverage FROM users WHERE id = ?");
$userStmt->execute([$user_id]);
$settings = $userStmt->fetch(PDO::FETCH_ASSOC);
$display_username = $settings['username'] ?? "User $user_id";

            // --- LOGIC ΓΙΑ ΕΞΟΔΟ ---
            $shouldClose = false;
            $reason = "";
            $tp_limit = floatval($settings['tp_dollar'] ?? 0);
            $sl_limit = -abs(floatval($settings['sl_dollar'] ?? 0));
            $z_exit   = abs(floatval($settings['z_exit_threshold'] ?? 0.1));

            // Υπολογισμός διάρκειας trade
            $duration_hours = (time() - strtotime($trade['created_at'])) / 3600;

            // 1. Έλεγχος STOP LOSS
            if ($sl_limit < 0 && $currentPnL <= $sl_limit) { 
                $shouldClose = true; 
                $reason = "STOP LOSS $ (PnL: $currentPnL)"; 
            } 
            
			// 2. Έλεγχος SMART TIME EXIT (Κλείσιμο στις 5 μέρες ΜΟΝΟ αν υπάρχει κέρδος > 2.00$)
elseif ($duration_hours >= 120 && $currentPnL > 2.00) {
    $shouldClose = true;
    $reason = "SMART TIME EXIT (".round($duration_hours)."h & Profit > $2)";
}
			
            // 3. Λοιποί έλεγχοι (TP, Z-Score, Stale Exit)
            else {
                $is_tp_triggered = ($tp_limit > 0 && $currentPnL >= $tp_limit);
                $is_z_triggered  = (abs($current_z) <= $z_exit);
                $is_stale_exit   = ($duration_hours >= 72 && $currentPnL > 0.30); 

                if ($is_tp_triggered || $is_z_triggered || $is_stale_exit) {
                    
                    // Υπολογισμός συνολικής αξίας του trade (Notional Value)
                    $total_notional = ($trade['quantity_a'] * ($prices[$symA] ?? 0)) + ($trade['quantity_b'] * ($prices[$symB] ?? 0));
                    
                    // Δυναμικά fees (0.1%) και καθαρό κέρδος (0.3% της αξίας)
                    $approx_closing_fees = $total_notional * 0.0010; 
                    $min_net_needed      = $total_notional * 0.0030; 

                    if ($is_tp_triggered) {
                        $shouldClose = true;
                        $reason = "HARD TAKE PROFIT $ (PnL: $currentPnL)";
                    } 
                    // Κλείσιμο με Z-Score ΜΟΝΟ αν καλύπτει fees + 0.3% καθαρό κέρδος
                    elseif ($is_z_triggered && $currentPnL > ($approx_closing_fees + $min_net_needed)) {
                        $shouldClose = true;
                        $reason = "CONVERGENCE Z (PnL: $currentPnL)";
                    }
                    elseif ($is_stale_exit) {
                        $shouldClose = true;
                        $reason = "STALE TRADE EXIT (72h+ & PnL > 0)";
                    }
                }
            }

            if ($shouldClose) {
                $pdo->prepare("UPDATE active_pairs SET status = 'CLOSING' WHERE id = ?")->execute([$trade['id']]);
                tlog("🚨 SIGNAL CLOSE: {$trade['asset_a']}/{$trade['asset_b']} | $reason");
                
                $stmt_keys = $pdo->prepare("SELECT api_key, api_secret FROM api_keys WHERE user_id = ? AND account_type = ? AND is_active = 1 LIMIT 1");
                $stmt_keys->execute([$user_id, $mode]);
                $api = $stmt_keys->fetch();
                
                if (!$api) {
                    $pdo->prepare("UPDATE active_pairs SET status = 'OPEN' WHERE id = ?")->execute([$trade['id']]);
                    continue;
                }

                $fK = decrypt_data($api['api_key']); 
                $fS = decrypt_data($api['api_secret']);
                
                $psA = (strtoupper($trade['side_a']) === 'BUY') ? 'LONG' : 'SHORT';
                $psB = (strtoupper($trade['side_b']) === 'BUY') ? 'LONG' : 'SHORT';
                $clA = ($psA === 'LONG') ? 'SELL' : 'BUY';
                $clB = ($psB === 'LONG') ? 'SELL' : 'BUY';

                $qtyA = get_live_qty($fK, $fS, $symA, $psA, $mode);
                $qtyB = get_live_qty($fK, $fS, $symB, $psB, $mode);

                if ($qtyA === false || $qtyB === false) {
                    $pdo->prepare("UPDATE active_pairs SET status = 'OPEN' WHERE id = ?")->execute([$trade['id']]);
                    continue;
                }

                $resA = ($qtyA > 0) ? binance_market_order($fK, $fS, $symA, $clA, $settings['leverage'], $qtyA, true, $psA, $mode, $user_id, $reason, $current_z) : ['success'=>true, 'price'=>0, 'commission'=>0];
                usleep(300000); 
                $resB = ($qtyB > 0) ? binance_market_order($fK, $fS, $symB, $clB, $settings['leverage'], $qtyB, true, $psB, $mode, $user_id, $reason, $current_z) : ['success'=>true, 'price'=>0, 'commission'=>0];

                if ($resA['success'] && $resB['success']) {
                    $exitA = ($resA['price'] > 0) ? $resA['price'] : ($prices[$symA] ?? 0);
                    $exitB = ($resB['price'] > 0) ? $resB['price'] : ($prices[$symB] ?? 0);
                    
                    $realPnlA = (strtoupper($trade['side_a']) === 'BUY') 
                        ? ($exitA - (float)$trade['entry_price_a']) * (float)$trade['quantity_a'] 
                        : ((float)$trade['entry_price_a'] - $exitA) * (float)$trade['quantity_a'];
                    
                    $realPnlB = (strtoupper($trade['side_b']) === 'BUY') 
                        ? ($exitB - (float)$trade['entry_price_b']) * (float)$trade['quantity_b'] 
                        : ((float)$trade['entry_price_b'] - $exitB) * (float)$trade['quantity_b'];
                    
                    $finalGrossPnl = round($realPnlA + $realPnlB, 4);
                    $entryFees = floatval($trade['commission_a'] ?? 0) + floatval($trade['commission_b'] ?? 0);
                    $exitFees  = floatval($resA['commission'] ?? 0) + floatval($resB['commission'] ?? 0);
                    $totalFees = $entryFees + $exitFees;
                    $netPnl    = round($finalGrossPnl - $totalFees, 4);

                    $pdo->prepare("UPDATE active_pairs SET status = 'CLOSED', closed_at = NOW(), exit_price_a = ?, exit_price_b = ?, final_pnl = ?, notes = ? WHERE id = ?")
                        ->execute([$exitA, $exitB, $finalGrossPnl, "Monitor: $reason", $trade['id']]);

                    // INSERT TO JOURNAL (13 Fields)
                    $sqlJournal = "INSERT INTO zEQZkBci_trade_journal (
                        user_id, pair, account_type, 
                        entry_price_a, entry_price_b, exit_price_a, exit_price_b, 
                        gross_pnl, total_commission, net_pnl,
                        duration_minutes, entry_z_score, exit_reason, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                    $pdo->prepare($sqlJournal)->execute([
                        $user_id, $trade['asset_a']."/".$trade['asset_b'], $mode,
                        $trade['entry_price_a'], $trade['entry_price_b'], $exitA, $exitB,
                        round($finalGrossPnl, 4), round($totalFees, 4), round($netPnl, 4),
                        round((time() - strtotime($trade['created_at'])) / 60), 
                        (float)($trade['entry_z_score'] ?? 0), $reason
                    ]);

                    tlog("✅ CLOSED & JOURNALED: {$trade['asset_a']}/{$trade['asset_b']} | Net PnL: $$netPnl");

                    // --- TELEGRAM NOTIFICATION FOR MONITOR EXIT ---
                    $emoji = ($netPnl >= 0) ? "✅" : "🔻";
                    $mode_label = ($mode === 'LIVE') ? "🔵 <b>LIVE</b>" : "🟡 <b>DEMO</b>";
                    
                    $msg = "👤 {$emoji} <b>AUTOMATIC EXIT COMPLETED</b>\n";
                    $msg .= "📊 Pair: <code>{$trade['asset_a']}/{$trade['asset_b']}</code>\n";
                    $msg .= "🏁 Reason: <b>{$reason}</b>\n";
                    $msg .= "💰 GROSS PnL: <b>$" . number_format($finalGrossPnl, 2) . "</b>\n";
                    $msg .= "⛽ Total Fees: <code>$" . number_format($totalFees, 4) . " USDT</code>\n";
                    $msg .= "💵 <b>NET PnL: " . ($netPnl >= 0 ? "+" : "") . "$" . number_format($netPnl, 2) . "</b>\n";
                    $msg .= "------------------------\n";
                    $msg .= "👤 User: <b>{$display_username}</b> | {$mode_label}";
                    
                    sendTelegramNotification($msg, $user_id);

                } else {
                    // Αν αποτύχει το κλείσιμο στη Binance, το επαναφέρουμε σε OPEN
                    $pdo->prepare("UPDATE active_pairs SET status = 'EXECUTION_ERROR' WHERE id = ?")->execute([$trade['id']]);
                    tlog(" ERROR: Binance rejected close for {$trade['asset_a']}/{$trade['asset_b']}");
                }
            } 
        } catch (Exception $e) { 
            tlog(" Error ID {$trade['id']}: " . $e->getMessage()); 
        }
    } 
    
    // --- ALERT CHECKING (Integrated) ---
    try {
        checkAlerts($pdo);
    } catch (Exception $e) {
        tlog(" Alert check error: " . $e->getMessage());
    }
    
    sleep(5); // Προστασία από BAN
} 

// --- 3. RELEASE LOCK FILE ---
if (file_exists($lock_file)) unlink($lock_file);

tlog(" MONITOR CYCLE COMPLETED.");
tlog("🏁 MONITOR CYCLE COMPLETED.");
?>