<?php
/**
 * CT-OS | binance_history_seeder.php - 500H FORCE EDITION
 */
require_once 'db_config.php';
require_once 'functions.php';

// ΑΝΑΓΚΑΖΟΥΜΕ ΤΟ SCRIPT ΝΑ ΜΗΝ ΣΤΑΜΑΤΗΣΕΙ ΠΟΤΕ (Μέχρι να τελειώσει)
set_time_limit(0);
ini_set('memory_limit', '512M');

echo "<h2>📥 Binance History Seeder (500H Full Backfill)</h2>";
echo "<div style='font-family: monospace; background: #000; color: #0f0; padding: 20px;'>";

// 1. Παίρνουμε όλα τα μοναδικά assets από το Universe
$stmt = $pdo->query("SELECT DISTINCT asset_a as asset FROM pair_universe UNION SELECT DISTINCT asset_b FROM pair_universe");
$assets = $stmt->fetchAll(PDO::FETCH_COLUMN);

tlog("Found " . count($assets) . " unique assets to sync.");

foreach ($assets as $asset) {
    $symbol = strtoupper($asset) . "USDT";
    echo "Processing $symbol... ";

    // 2. Καλούμε το API της Binance για 500 ωριαία κεριά
    $url = "https://fapi.binance.com/fapi/v1/klines?symbol=$symbol&interval=1h&limit=500";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20); // Αυξημένο timeout για το curl
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $klines = json_decode($response, true);

    if ($httpCode !== 200 || !is_array($klines) || isset($klines['code'])) {
        echo "<span style='color:red;'>FAILED (Binance Error or HTTP $httpCode)</span><br>";
        continue;
    }

    $inserted = 0;
    $existing = 0;

    foreach ($klines as $k) {
        if (!is_array($k) || count($k) < 5) continue;

        $timestamp = date("Y-m-d H:i:s", $k[0] / 1000);
        $closePrice = $k[4];

        // 3. INSERT IGNORE: Αν υπάρχει ήδη το timestamp για αυτό το asset, το προσπερνάει
        $ins = $pdo->prepare("INSERT IGNORE INTO asset_history (asset, timestamp, price) VALUES (?, ?, ?)");
        $ins->execute([strtoupper($asset), $timestamp, $closePrice]);
        
        if ($ins->rowCount() > 0) {
            $inserted++;
        } else {
            $existing++;
        }
    }

    echo "<span style='color:springgreen;'>OK</span> (New: $inserted, In Base: $existing)<br>";
    
    // Μικρή παύση 0.1 δευτ. για προστασία από Rate Limit
    usleep(100000); 
}

echo "</div><br><b style='color:green;'>✅ SEEDING COMPLETED. All assets updated to 500H.</b>";
?>