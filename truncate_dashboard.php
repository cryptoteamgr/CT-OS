<?php
/**
 * CT-OS | copyright by cryptoteam.gr - truncate_dashboard.php
 * ----------------------------------------------------------------
 * Σκοπός: Πρωτόκολλο "Ολικής Εκκαθάρισης" (Emergency Reset) του Dashboard. 
 * Χρησιμοποιείται σε περιπτώσεις κρίσιμων σφαλμάτων συγχρονισμού ή όταν ο Operator 
 * επιθυμεί να μηδενίσει το Terminal και να ξεκινήσει από την αρχή.
 */

require_once 'db_config.php';

echo "<pre>--- EMERGENCY DASHBOARD RESET [" . date("Y-m-d H:i:s") . "] ---\n";

try {
    // 1. Καθαρισμός των ενεργών pairs (Status -> CLOSED)
    $stmt1 = $pdo->prepare("UPDATE active_pairs SET status = 'CLOSED', notes = 'MANUAL RESET' WHERE status = 'OPEN'");
    $stmt1->execute();
    echo "✅ " . $stmt1->rowCount() . " active pairs marked as CLOSED.\n";

    // 2. Καθαρισμός του πίνακα active_positions (αν χρησιμοποιείται για cache)
    $stmt2 = $pdo->prepare("DELETE FROM active_positions");
    $stmt2->execute();
    echo "✅ Active positions cache cleared.\n";

    echo "\n🚀 Dashboard is now CLEAN. You can start new trades.";
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage();
}

echo "</pre>";
?>