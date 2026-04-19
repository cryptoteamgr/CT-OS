<?php
require_once __DIR__ . '/db_config.php';

$assetA = 'TIA';
$assetB = 'UNI';

echo "=== ENABLING COINTEGRATION FOR $assetA/$assetB ===\n";

// Update the pair universe
$stmt = $pdo->prepare("UPDATE pair_universe SET is_cointegrated = 1 WHERE asset_a = ? AND asset_b = ? OR asset_a = ? AND asset_b = ?");
$stmt->execute([$assetA, $assetB, $assetB, $assetA]);

if ($stmt->rowCount() > 0) {
    echo "✅ Successfully enabled cointegration for $assetA/$assetB\n";
    
    // Verify the update
    $stmtCheck = $pdo->prepare("SELECT * FROM pair_universe WHERE asset_a = ? AND asset_b = ? OR asset_a = ? AND asset_b = ?");
    $stmtCheck->execute([$assetA, $assetB, $assetB, $assetA]);
    $pair = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($pair) {
        echo "\n=== UPDATED PAIR DETAILS ===\n";
        echo "Pair: " . $pair['asset_a'] . "/" . $pair['asset_b'] . "\n";
        echo "Is Active: " . ($pair['is_active'] ? 'YES' : 'NO') . "\n";
        echo "Is Cointegrated: " . ($pair['is_cointegrated'] ? 'YES' : 'NO') . "\n";
        echo "Last Z-Score: " . $pair['last_z_score'] . "\n";
        echo "Last Beta: " . $pair['last_beta'] . "\n";
        echo "Correlation: " . ($pair['correlation'] ?? 'NULL') . "\n";
    }
} else {
    echo "❌ Pair not found or already cointegrated\n";
}
