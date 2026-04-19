<?php
// --- ΡΥΘΜΙΣΕΙΣ ---
$fee_percent = 0.0005; // 0.05% Binance Fee
$std_dev_pct = 0.012;  // 1.2% Μέση Τυπική Απόκλιση Spread

$cap = isset($_GET['cap']) ? floatval($_GET['cap']) : 100;
$lev = isset($_GET['lev']) ? intval($_GET['lev']) : 10;
$entry_z = isset($_GET['entry_z']) ? floatval($_GET['entry_z']) : 2.0;

// Mode 1: Υπολογισμός από TP $
$target_tp_usd = isset($_GET['tp_usd']) ? floatval($_GET['tp_usd']) : 5;

// Mode 2: Υπολογισμός από TP Z
$target_exit_z = isset($_GET['exit_z']) ? floatval($_GET['exit_z']) : 0.5;

$pos_size = $cap * $lev;
$total_fees = ($pos_size * $fee_percent) * 2;

// ΥΠΟΛΟΓΙΣΜΟΙ
// 1. Από TP $ -> βρίσκουμε το Exit Z
// formula: Exit Z = Entry Z - ( (TP + Fees) / (PosSize * StdDev) )
$z_dist_needed = ($target_tp_usd + $total_fees) / ($pos_size * $std_dev_pct);
$calculated_exit_z = abs($entry_z) - $z_dist_needed;

// 2. Από Exit Z -> βρίσκουμε το TP $
// formula: TP $ = ( (Entry Z - Exit Z) * PosSize * StdDev ) - Fees
$z_dist_provided = abs($entry_z) - abs($target_exit_z);
$calculated_tp_usd = ($z_dist_provided * $pos_size * $std_dev_pct) - $total_fees;

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>CT-OS Strategy Calc</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #0e1013; color: #e0e0e0; padding: 30px; }
        .grid { display: flex; gap: 20px; flex-wrap: wrap; }
        .card { background: #161b22; padding: 20px; border-radius: 12px; border: 1px solid #30363d; flex: 1; min-width: 300px; }
        h2 { color: #58a6ff; margin-top: 0; }
        .highlight { font-size: 1.5em; font-weight: bold; color: #238636; display: block; margin: 10px 0; }
        .fee-label { color: #f85149; font-size: 0.9em; }
        input { background: #0d1117; border: 1px solid #30363d; color: #fff; padding: 10px; border-radius: 6px; width: 100%; box-sizing: border-box; margin: 5px 0 15px; }
        button { background: #238636; color: white; border: none; padding: 12px; width: 100%; border-radius: 6px; cursor: pointer; font-weight: bold; }
        button:hover { background: #2ea043; }
        hr { border: 0; border-top: 1px solid #30363d; margin: 20px 0; }
    </style>
</head>
<body>

<h1>🔬 Arbitrage Strategy Calculator</h1>

<form method="GET" class="grid">
    <div class="card">
        <h2>⚙️ Ρυθμίσεις Λογαριασμού</h2>
        <label>Capital ($)</label>
        <input type="number" name="cap" value="<?php echo $cap; ?>">
        <label>Leverage (x)</label>
        <input type="number" name="lev" value="<?php echo $lev; ?>">
        <label>Entry Z-Score (π.χ. 2.2)</label>
        <input type="number" step="0.1" name="entry_z" value="<?php echo $entry_z; ?>">
        <button type="submit">Ενημέρωση Όλων</button>
        <p class="fee-label">⛽ Εκτιμώμενα Fees: $<?php echo number_format($total_fees, 2); ?></p>
    </div>

    <div class="card" style="border-top: 4px solid #58a6ff;">
        <h2>💵 Αν ορίσω TP σε $</h2>
        <label>Στόχος Κέρδους ($)</label>
        <input type="number" name="tp_usd" value="<?php echo $target_tp_usd; ?>">
        <p>Το trade θα κλείσει όταν το Z-Score επιστρέψει στο:</p>
        <span class="highlight">Z = <?php echo number_format($calculated_exit_z, 2); ?></span>
        <small>Απαιτούμενη κίνηση Z: <?php echo number_format($z_dist_needed, 2); ?></small>
    </div>

    <div class="card" style="border-top: 4px solid #238636;">
        <h2>📊 Αν ορίσω TP σε Z</h2>
        <label>Στόχος Exit Z-Score (π.χ. 0.5)</label>
        <input type="number" step="0.1" name="exit_z" value="<?php echo $target_exit_z; ?>">
        <p>Το καθαρό κέρδος (μετά τα fees) θα είναι:</p>
        <span class="highlight">$<?php echo number_format($calculated_tp_usd, 2); ?></span>
        <small>Απόσταση Z: <?php echo number_format($z_dist_provided, 2); ?></small>
    </div>
</form>

</body>
</html>