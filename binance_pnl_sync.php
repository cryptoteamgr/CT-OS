<?php
/**
 * CT-OS | binance_pnl_sync.php
 * Σκοπός: Centralized Binance PnL fetching - ξεχωριστό cron job
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/functions.php';

// LOCK FILE
$lock_file = __DIR__ . '/pnl_sync.lock';
if (file_exists($lock_file) && (time() - filemtime($lock_file) < 30)) { 
    die("Error: PnL Sync is already running.\n"); 
}
file_put_contents($lock_file, "running");

try {
    echo "[" . date("H:i:s") . "] Binance PnL Sync Started.\n";
    
    // Λήψη OPEN trades
    $openTrades = $pdo->query("SELECT ap.*, a.api_key, a.api_secret FROM active_pairs ap JOIN api_keys a ON ap.user_id = a.user_id AND ap.mode = a.account_type WHERE ap.status = 'OPEN' AND a.is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    $updPnl = $pdo->prepare("UPDATE active_pairs SET binance_pnl_a = ?, binance_pnl_b = ? WHERE id = ?");

    foreach ($openTrades as $pos) {
        $k = decrypt_data($pos['api_key']);
        $s = decrypt_data($pos['api_secret']);
        $mode = strtoupper($pos['mode']);

        $symA = strtoupper($pos['asset_a'] . "USDT");
        $symB = strtoupper($pos['asset_b'] . "USDT");

        $posA = binance_get_position($k, $s, $symA, $mode);
        $posB = binance_get_position($k, $s, $symB, $mode);

        $pnlA = $posA['unrealizedPnl'] ?? 0;
        $pnlB = $posB['unrealizedPnl'] ?? 0;

        $updPnl->execute([$pnlA, $pnlB, $pos['id']]);
        
        echo "[" . date("H:i:s") . "] PnL Sync: {$pos['asset_a']}/{$pos['asset_b']} | PnL A: {$pnlA} | PnL B: {$pnlB}\n";
    }
    
    echo "[" . date("H:i:s") . "] Binance PnL Sync Completed Successfully.\n";

} catch (Exception $e) {
    echo "[" . date("H:i:s") . "] CRITICAL ERROR: " . $e->getMessage() . "\n";
}

// Απελευθέρωση Lock
if (file_exists($lock_file)) unlink($lock_file);
