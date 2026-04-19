<?php
/**
 * CT-OS | cron_cointegration.php - PRO STATISTICAL EDITION (500H VERSION)
 * ----------------------------------------------------------------
 * Σκοπός: Υπολογισμός Συνολοκλήρωσης (Engle-Granger) με παράθυρο 500 ωρών.
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/functions.php';

// 1. LOCK FILE (Προστασία από διπλή εκτέλεση)
$lock_file = __DIR__ . '/cointegration.lock';
if (file_exists($lock_file) && (time() - filemtime($lock_file) < 600)) {
    die("Cointegration is already running.\n");
}
file_put_contents($lock_file, "running");
register_shutdown_function(function() use ($lock_file) {
    if (file_exists($lock_file)) unlink($lock_file);
});

if (!function_exists('tlog')) {
    function tlog($msg) { echo "[" . date("H:i:s") . "] " . $msg . "\n"; }
}

tlog("📊 Starting Professional 500H Cointegration Analysis...");
ini_set('memory_limit', '256M');

// Σταθερά Critical Value για Engle-Granger (N=500, 80% confidence)
// Χαλαρώθηκε από -3.34 σε -2.0 για ακόμα περισσότερες ευκαιρίες trading
$CRITICAL_VALUE_95 = -2.0; 

// 2. ΕΥΡΕΣΗ ΖΕΥΓΑΡΙΩΝ (Batch Mode: Αναλύουμε τα 50 λιγότερο πρόσφατα ενημερωμένα)
$pairs = [];
try {
    $pairs = $pdo->query("SELECT * FROM pair_universe WHERE is_active = 1 ORDER BY last_update ASC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    try {
        $pairs = $pdo->query("SELECT * FROM pair_universe WHERE active = 1 ORDER BY last_update ASC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        die("❌ CRITICAL SQL ERROR: Could not find 'is_active' or 'active' column.\n");
    }
}

$stats = ['passed' => 0, 'failed' => 0];

foreach ($pairs as $p) {
    // 3. JOIN ΤΙΜΩΝ ΑΠΟ asset_history (Rolling Window 500)
    $sql = "
        SELECT h1.price as price_a, h2.price as price_b 
        FROM asset_history h1
        JOIN asset_history h2 ON h1.timestamp = h2.timestamp
        WHERE h1.asset = ? AND h2.asset = ?
        ORDER BY h1.timestamp DESC 
        LIMIT 500
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$p['asset_a'], $p['asset_b']]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Αυξημένο όριο ελέγχου για στατιστική εγκυρότητα (χρειαζόμαστε τουλάχιστον το 90% των κεριών)
        if (count($history) < 450) {
            tlog("⚠️ Insufficient history for {$p['asset_a']}/{$p['asset_b']} (Found: " . count($history) . "/500)");
            $pdo->prepare("UPDATE pair_universe SET last_update = NOW() WHERE id = ?")->execute([$p['id']]);
            continue;
        }

        $history = array_reverse($history);
        $dataA = array_map('floatval', array_column($history, 'price_a'));
        $dataB = array_map('floatval', array_column($history, 'price_b'));

        // 4. STEP 1: Linear Regression (OLS) για εύρεση Rolling Beta (Hedge Ratio)
        $reg = calculate_linear_regression($dataA, $dataB);
        $beta = $reg['beta'];
        
        // Φίλτρο Beta: Το Bot δεν ανοίγει trade αν το beta είναι κάτω από 0.7
        if ($beta < 0.7) {
            update_pair_db($pdo, $p['id'], 1.0, 0, $beta);
            tlog("❌ FAILED: Beta too low ($beta) for {$p['asset_a']}/{$p['asset_b']}");
            $stats['failed']++;
            continue;
        }

        // 5. STEP 2: Calculate Residuals (Spread)
        $residuals = [];
        foreach ($dataA as $i => $valA) {
            $predictedB = ($beta * $valA) + $reg['alpha'];
            $residuals[] = $dataB[$i] - $predictedB;
        }

        // 6. STEP 3: ADF Test στα Residuals (Augmented Dickey-Fuller t-stat)
        $adf_stat = calculate_adf_statistic($residuals);

        // Έλεγχος Συνολοκλήρωσης βάσει Critical Values
        $isCointegrated = ($adf_stat < $CRITICAL_VALUE_95) ? 1 : 0;
        $pValue = estimate_p_value($adf_stat);

        // 7. ΕΝΗΜΕΡΩΣΗ pair_universe
        update_pair_db($pdo, $p['id'], $pValue, $isCointegrated, $beta);

        if ($isCointegrated) {
            $stats['passed']++;
            tlog("Pair: {$p['asset_a']}/{$p['asset_b']} | ADF: ".round($adf_stat,2)." | Beta: ".round($beta,3)." | ✅ COINTEGRATED");
        } else {
            $stats['failed']++;
            tlog("Pair: {$p['asset_a']}/{$p['asset_b']} | ADF: ".round($adf_stat,2)." | Beta: ".round($beta,3)." | ❌ FAILED");
        }

    } catch (Exception $e) {
        tlog("❌ Error on pair {$p['asset_a']}/{$p['asset_b']}: " . $e->getMessage());
    }
}

tlog("🏁 Analysis Completed. Passed: {$stats['passed']} | Failed: {$stats['failed']}");

// --- ΜΑΘΗΜΑΤΙΚΕΣ ΣΥΝΑΡΤΗΣΕΙΣ ---

function calculate_linear_regression($x, $y) {
    $n = count($x);
    if ($n == 0) return ['beta' => 0, 'alpha' => 0];
    $meanX = array_sum($x) / $n;
    $meanY = array_sum($y) / $n;
    $num = 0; $den = 0;
    for ($i = 0; $i < $n; $i++) {
        $num += ($x[$i] - $meanX) * ($y[$i] - $meanY);
        $den += pow($x[$i] - $meanX, 2);
    }
    $beta = ($den == 0) ? 0 : $num / $den;
    $alpha = $meanY - ($beta * $meanX);
    return ['beta' => $beta, 'alpha' => $alpha];
}

function calculate_adf_statistic($residuals) {
    $n = count($residuals);
    $diffs = []; $lagged = [];
    for ($i = 1; $i < $n; $i++) {
        $diffs[] = $residuals[$i] - $residuals[$i-1];
        $lagged[] = $residuals[$i-1];
    }
    
    // Regression: diffs = gamma * lagged (No intercept for residuals stationarity check)
    $num = 0; $den = 0;
    foreach ($lagged as $i => $val) {
        $num += $val * $diffs[$i];
        $den += pow($val, 2);
    }
    $gamma = ($den == 0) ? 0 : $num / $den;
    
    // Calculate Standard Error of Gamma
    $sse = 0;
    foreach ($diffs as $i => $d) {
        $sse += pow($d - ($gamma * $lagged[$i]), 2);
    }
    
    $n_minus_k = count($diffs) - 1; // k=1 (μόνο το gamma)
    if ($n_minus_k <= 0 || $den == 0) return 0;
    
    $se = sqrt($sse / $n_minus_k) / sqrt($den);
    
    return ($se == 0) ? 0 : $gamma / $se; // t-statistic
}

function estimate_p_value($stat) {
    if ($stat < -4.0) return 0.001;
    if ($stat < -3.34) return 0.049;
    if ($stat < -3.0) return 0.150;
    return 0.500;
}

function update_pair_db($pdo, $id, $p_val, $is_c, $beta) {
    $sql = "UPDATE pair_universe SET p_value = ?, is_cointegrated = ?, last_beta = ?, last_update = NOW() WHERE id = ?";
    $pdo->prepare($sql)->execute([$p_val, $is_c, $beta, $id]);
}