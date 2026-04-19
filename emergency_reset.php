<?php
/**
 * CT-OS | copyright by cryptoteam.gr - emergency_reset.php
 * ----------------------------------------------------------------
 * Σκοπός: Εργαλείο έκτακτης ανάγκης για τη διόρθωση της βάσης δεδομένων (Database Cleanup) και τον έλεγχο συγχρονισμού με το Exchange.
 */
session_start();
require_once 'db_config.php';

// Έλεγχος αν είναι Admin
if (!isset($_SESSION['user_id']) || strtoupper($_SESSION['role'] ?? '') !== 'ADMIN') {
    die("Unauthorized access.");
}

echo "<style>
    body { font-family: sans-serif; background: #020617; color: #f1f5f9; padding: 20px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #0f172a; }
    th, td { border: 1px solid #1e293b; padding: 12px; text-align: left; font-size: 13px; }
    th { background: #1e293b; color: #3b82f6; text-transform: uppercase; }
    .status-open { color: #22c55e; font-weight: bold; }
    .log-msg { color: #94a3b8; font-size: 14px; margin: 5px 0; }
    .header { border-bottom: 2px solid #3b82f6; padding-bottom: 10px; margin-bottom: 20px; }
</style>";

echo "<div class='header'><h1>🚀 CTT-OS System Recovery & Audit</h1></div>";

try {
    // --- 1. ΕΝΕΡΓΕΙΕΣ ΔΙΟΡΘΩΣΗΣ ---
    echo "<div class='log-msg'>⚙️ Resetting Auto-Increment for core tables...</div>";
    $pdo->exec("ALTER TABLE pair_universe AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE active_pairs AUTO_INCREMENT = 1");

    echo "<div class='log-msg'>🧹 Cleaning duplicate pairs from Universe...</div>";
    $pdo->exec("DELETE p1 FROM pair_universe p1 
                INNER JOIN pair_universe p2 
                WHERE p1.id < p2.id AND p1.asset_a = p2.asset_a AND p1.asset_b = p2.asset_b");

    echo "<div class='log-msg'>🗑️ Deleting old closed history (older than 7 days)...</div>";
    $delCount = $pdo->exec("DELETE FROM active_pairs WHERE status = 'CLOSED' AND closed_at < NOW() - INTERVAL 7 DAY");
    echo "<div class='log-msg'>✅ Removed $delCount old records.</div>";

    // --- 2. ΕΜΦΑΝΙΣΗ ΑΝΟΙΧΤΩΝ TRADES ΓΙΑ ΣΥΓΚΡΙΣΗ ---
    echo "<h2>📊 Current Open Trades in SQL</h2>";
    echo "<p>Σύγκρινε την παρακάτω λίστα με τα ανοιχτά positions στην Binance:</p>";

    $stmt = $pdo->query("SELECT id, user_id, asset_a, asset_b, side_a, side_b, quantity_a, quantity_b, mode, opened_at 
                         FROM active_pairs 
                         WHERE status = 'OPEN' 
                         ORDER BY opened_at DESC");
    $openTrades = $stmt->fetchAll();

    if ($openTrades) {
        echo "<table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Pair</th>
                        <th>Side A/B</th>
                        <th>Qty A/B</th>
                        <th>Mode</th>
                        <th>Opened At</th>
                    </tr>
                </thead>
                <tbody>";
        foreach ($openTrades as $trade) {
            echo "<tr>
                    <td>#{$trade['id']}</td>
                    <td>UID: {$trade['user_id']}</td>
                    <td><b>{$trade['asset_a']} / {$trade['asset_b']}</b></td>
                    <td>{$trade['side_a']} / {$trade['side_b']}</td>
                    <td>{$trade['quantity_a']} / {$trade['quantity_b']}</td>
                    <td>" . ($trade['mode'] == 'LIVE' ? '🔴 LIVE' : '🟡 DEMO') . "</td>
                    <td>{$trade['opened_at']}</td>
                  </tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p style='color: #94a3b8; italic;'>Δεν βρέθηκαν ανοιχτά trades στη βάση δεδομένων.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:#ef4444;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<div style='margin-top: 30px; padding: 15px; background: rgba(59, 130, 246, 0.1); border-radius: 10px;'>
        <b>Next Steps:</b><br>
        1. Αν ένα trade υπάρχει στο Binance αλλά ΟΧΙ παραπάνω, κλείστο χειροκίνητα στο App της Binance.<br>
        2. Αν ένα trade υπάρχει παραπάνω αλλά ΟΧΙ στο Binance, κάνε το 'Force Close' από το User Monitor του CTT-OS.
      </div>";