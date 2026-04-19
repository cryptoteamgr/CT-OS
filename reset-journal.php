<?php
/**
 * CT-OS | copyright by cryptoteam.gr - reset-journal.php
 * ----------------------------------------------------------------
 * Σκοπός: Εκκαθάριση του ιστορικού συναλλαγών (Trade Journal) για συγκεκριμένο τύπο λογαριασμού (LIVE/DEMO). 
 * Χρησιμοποιείται όταν ο trader θέλει να κάνει "Fresh Start" στα στατιστικά του.
 */
session_start();
require_once 'db_config.php';

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
    $user_id = $_SESSION['user_id'];
    $mode = $_POST['mode'] === 'LIVE' ? 'LIVE' : 'DEMO';

    try {
        $stmt = $pdo->prepare("DELETE FROM zEQZkBci_trade_journal WHERE user_id = ? AND account_type = ?");
        $stmt->execute([$user_id, $mode]);
        echo "Success";
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error: " . $e->getMessage();
    }
}