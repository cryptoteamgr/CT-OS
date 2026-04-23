<?php
/**
 * Fetch full Binance exchange info to get precision values
 */

$base_url = "https://fapi.binance.com";
$endpoint = "/fapi/v1/exchangeInfo";

$ch = curl_init($base_url . $endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    die("Error fetching exchange info. HTTP code: $httpCode\n");
}

$data = json_decode($response, true);

if (!isset($data['symbols'])) {
    die("Invalid response format\n");
}

// Default precision for common assets (from pair_scanner.php)
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
    'OPUSDT' => 0.1,
    'VETUSDT' => 1,
    'XLMUSDT' => 1,
    'DOGEUSDT' => 1,
    'ALGOUSDT' => 0.1,
    'XRPUSDT' => 0.1,
    'CRVUSDT' => 0.1,
    'SUIUSDT' => 0.1,
    'SANDUSDT' => 1
];

echo "=== BINANCE PRECISION CHECK ===\n";
echo "Comparing default_steps with actual Binance exchange info\n\n";

$mismatch_count = 0;

foreach ($default_steps as $symbol => $default_step) {
    $found = false;
    $actual_step = 'N/A';
    
    foreach ($data['symbols'] as $s) {
        if ($s['symbol'] === $symbol && $s['status'] === 'TRADING') {
            $found = true;
            if (isset($s['filters'])) {
                foreach ($s['filters'] as $f) {
                    if ($f['filterType'] === 'LOT_SIZE') {
                        $actual_step = floatval($f['stepSize']);
                        break;
                    }
                }
            }
            break;
        }
    }
    
    if (!$found) {
        echo "❌ $symbol | Default: $default_step | NOT FOUND in Binance\n";
        $mismatch_count++;
    } elseif ($actual_step != $default_step) {
        echo "❌ $symbol | Default: $default_step | Actual: $actual_step (MISMATCH)\n";
        $mismatch_count++;
    } else {
        echo "✅ $symbol | Default: $default_step | Actual: $actual_step (MATCH)\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "Total assets checked: " . count($default_steps) . "\n";
echo "Mismatches found: $mismatch_count\n";

if ($mismatch_count > 0) {
    echo "\n⚠️ ACTION REQUIRED: Update default_steps in pair_scanner.php with the actual values.\n";
} else {
    echo "\n✅ All precision values are correct.\n";
}
?>
