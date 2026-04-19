<?php
/**
 * CT-OS | copyright by cryptoteam.gr - auto_universe_sync.php
 * ----------------------------------------------------------------
 * Σκοπός: Μηχανή αυτόματης ανακάλυψης (Auto-Discovery) και στατιστικού συγχρονισμού ζευγαριών βάσει συσχέτισης (Correlation).
 */

require_once 'db_config.php';
require_once 'functions.php';

if (php_sapi_name() !== 'cli') { echo "<pre>"; }

echo "--- CT-OS AUTO-DISCOVERY STARTING [" . date("Y-m-d H:i:s") . "] ---\n";

// 1. Assets προς σκανάρισμα
// Τραβάει αυτόματα όλα τα νομίσματα που έχει κατεβάσει ο Seeder στη βάση
$stmt_assets = $pdo->query("SELECT DISTINCT asset FROM asset_history");
$scanList = $stmt_assets->fetchAll(PDO::FETCH_COLUMN);

// Ρυθμίσεις Thresholds
$maxTotalPairs = 200;            // ΑΥΣΤΗΡΟ ΟΡΙΟ ΖΕΥΓΑΡΙΩΝ
$minCorrelationToAdd = 0.85;    // Πιο ελαστικό για να βρει νέα ζεύγη
$minCorrelationToKeep = 0.75;   // Όριο για να παραμείνει ένα ζευγάρι
$newPairsFound = 0;             // Ορισμός για αποφυγή Warning

$allReturns = [];

echo "📡 Fetching hourly returns for " . count($scanList) . " assets...\n";

foreach ($scanList as $symbol) {
    $r = getReturns($symbol); 
    if ($r && count($r) > 0) {
        $allReturns[$symbol] = $r;
        echo "   [+] Data received for $symbol\n";
    } else {
        echo "   [-] Failed to fetch $symbol (Empty or API Error)\n";
    }
    usleep(400000); // 0.4s delay
}

// --- 1. CLEANER STAGE: Απενεργοποίηση ζευγαριών με χαμηλή συσχέτιση ---
echo "\n🧹 Scanning existing Universe for weak correlations...\n";
$existingPairs = $pdo->query("SELECT * FROM pair_universe")->fetchAll(PDO::FETCH_ASSOC);
$deactivatedCount = 0;

foreach ($existingPairs as $pair) {
    $a = $pair['asset_a'];
    $b = $pair['asset_b'];

    if (isset($allReturns[$a], $allReturns[$b])) {
        $currentCorr = calculateCorrelation($allReturns[$a], $allReturns[$b]);
        
        if ($currentCorr < $minCorrelationToKeep) {
            $checkActive = $pdo->prepare("SELECT id FROM active_pairs WHERE (asset_a = ? AND asset_b = ?) AND status = 'OPEN'");
            $checkActive->execute([$a, $b]);
            
            if ($checkActive->rowCount() == 0) {
                $pdo->prepare("UPDATE pair_universe SET is_active = 0 WHERE id = ?")->execute([$pair['id']]);
                echo "   🗑️ DEACTIVATED: $a/$b (Correlation: " . round($currentCorr * 100, 1) . "%)\n";
                $deactivatedCount++;
            }
        }
    }
}
echo "✅ Cleanup finished. Deactivated $deactivatedCount pairs.\n\n";

// --- 2. DISCOVERY STAGE ---
$currentCount = $pdo->query("SELECT COUNT(*) FROM pair_universe WHERE is_active = 1")->fetchColumn();
echo "\n✨ Current Active Universe: $currentCount. Searching (Max Target: $maxTotalPairs)...\n";

$keys = array_keys($allReturns);
for ($i = 0; $i < count($keys); $i++) {
    for ($j = $i + 1; $j < count($keys); $j++) {
        
        $assetA = $keys[$i];
        $assetB = $keys[$j];
        $corr = calculateCorrelation($allReturns[$assetA], $allReturns[$assetB]);

        if ($corr > $minCorrelationToAdd) {
            // Έλεγχος αν υπάρχει ήδη στη βάση (ανεξαρτήτως σειράς)
            $check = $pdo->prepare("SELECT id, is_active FROM pair_universe WHERE (asset_a = ? AND asset_b = ?) OR (asset_a = ? AND asset_b = ?) LIMIT 1");
            $check->execute([$assetA, $assetB, $assetB, $assetA]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);
            
            if (!$existing) {
                // ΝΕΟ ΖΕΥΓΑΡΙ - Χρήση στήλης 'correlation'
                if ($currentCount < $maxTotalPairs) {
                    $ins = $pdo->prepare("INSERT INTO pair_universe (asset_a, asset_b, correlation, is_active, last_update) VALUES (?, ?, ?, 1, NOW())");
                    $ins->execute([$assetA, $assetB, round($corr, 4)]);
                    echo "✨ NEW PAIR ADDED: $assetA/$assetB (Corr: " . round($corr * 100, 2) . "%)\n";
                    $newPairsFound++;
                    $currentCount++;
                }
            } elseif ($existing['is_active'] == 0) {
                // ΕΠΑΝΕΝΕΡΓΟΠΟΙΗΣΗ ΠΑΛΙΟΥ - Χρήση στήλης 'correlation'
                if ($currentCount < $maxTotalPairs) {
                    $pdo->prepare("UPDATE pair_universe SET is_active = 1, correlation = ? WHERE id = ?")
                        ->execute([round($corr, 4), $existing['id']]);
                    echo "🔄 RE-ACTIVATED: $assetA/$assetB (Corr: " . round($corr * 100, 2) . "%)\n";
                    $currentCount++;
                }
            }
        }
    }
}

// --- 3. STATISTICAL SYNC (Για τα νέα ή ενεργά ζευγάρια) ---
echo "\n📊 Updating Statistics (Z-Score & Beta) for active Universe...\n";
$activePairs = $pdo->query("SELECT * FROM pair_universe WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

foreach ($activePairs as $p) {
    $hA = getBinanceHistory($p['asset_a']);
    $hB = getBinanceHistory($p['asset_b']);
    
    if (count($hA) >= 450 && count($hB) >= 450) {
        $minLen = min(count($hA), count($hB));
        $hA = array_slice($hA, -$minLen);
        $hB = array_slice($hB, -$minLen);
        
        $ratios = [];
        for ($k = 0; $k < $minLen; $k++) {
            if ($hB[$k] > 0) $ratios[] = $hA[$k] / $hB[$k];
        }
        
        $zScore = calculateZScore($ratios);
        $beta = calculateBeta($hA, $hB);
        
        $upd = $pdo->prepare("UPDATE pair_universe SET last_z_score = ?, last_beta = ?, last_update = NOW() WHERE id = ?");
        $upd->execute([round($zScore, 4), round($beta, 4), $p['id']]);
    }
}

if ($newPairsFound > 0) {
    broadcastLog($pdo, 'INFO', "Auto-Discovery: Added/Re-activated $newPairsFound pairs.", 0);
}
echo "\n🏁 CYCLE COMPLETED. Universe is synced.\n";
if (php_sapi_name() !== 'cli') { echo "</pre>"; }

/**
 * ΣΥΝΑΡΤΗΣΕΙΣ
 */

function getReturns($symbol) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT price FROM asset_history WHERE asset = ? ORDER BY timestamp DESC LIMIT 500");
    $stmt->execute([strtoupper($symbol)]);
    $prices = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $prices = array_reverse($prices); 
    if (count($prices) < 2) return [];
    $returns = [];
    for ($i = 1; $i < count($prices); $i++) { 
        if ($prices[$i-1] > 0) $returns[] = ($prices[$i] - $prices[$i-1]) / $prices[$i-1]; 
    }
    return $returns;
}

function calculateCorrelation($x, $y) {
    $n = min(count($x), count($y));
    if ($n < 2) return 0;
    $x = array_slice($x, 0, $n); $y = array_slice($y, 0, $n);
    $meanX = array_sum($x) / $n; $meanY = array_sum($y) / $n;
    $num = 0; $denX = 0; $denY = 0;
    for ($i = 0; $i < $n; $i++) {
        $num += ($x[$i] - $meanX) * ($y[$i] - $meanY);
        $denX += pow($x[$i] - $meanX, 2);
        $denY += pow($y[$i] - $meanY, 2);
    }
    $denominator = sqrt($denX * $denY);
    return ($denominator == 0) ? 0 : $num / $denominator;
}

function getBinanceHistory($symbol) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT price FROM asset_history WHERE asset = ? ORDER BY timestamp DESC LIMIT 500");
    $stmt->execute([strtoupper($symbol)]);
    return array_reverse($stmt->fetchAll(PDO::FETCH_COLUMN));
}

function calculateZScore($ratios) {
    $n = count($ratios);
    if ($n < 30) return 0;
    $mean = array_sum($ratios) / $n;
    $sq_diff_sum = 0;
    foreach ($ratios as $r) { $sq_diff_sum += pow($r - $mean, 2); }
    $std_dev = sqrt($sq_diff_sum / ($n - 1));
    return ($std_dev == 0) ? 0 : ($ratios[$n - 1] - $mean) / $std_dev;
}

function calculateBeta($pricesA, $pricesB) {
    $n = min(count($pricesA), count($pricesB));
    if ($n < 2) return 1.0;
    $retA = []; $retB = [];
    for ($i = 1; $i < $n; $i++) {
        if ($pricesA[$i-1] > 0) $retA[] = ($pricesA[$i] - $pricesA[$i-1]) / $pricesA[$i-1];
        if ($pricesB[$i-1] > 0) $retB[] = ($pricesB[$i] - $pricesB[$i-1]) / $pricesB[$i-1];
    }
    $n_ret = count($retA);
    if ($n_ret < 2) return 1.0;
    $meanA = array_sum($retA) / $n_ret; $meanB = array_sum($retB) / $n_ret;
    $num = 0; $den = 0;
    for ($i = 0; $i < $n_ret; $i++) {
        $num += ($retA[$i] - $meanA) * ($retB[$i] - $meanB);
        $den += pow($retB[$i] - $meanB, 2);
    }
    return ($den == 0) ? 1.0 : $num / $den;
}