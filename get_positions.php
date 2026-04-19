<?php
/**
 * CT-OS | copyright by cryptoteam.gr - get_positions.php
 * ----------------------------------------------------------------
 * Σκοπός: API ανάκτησης όλων των ενεργών arbitrage trades του χρήστη από την τοπική βάση δεδομένων για την τροφοδοσία του Terminal UI.
 */

header('Content-Type: application/json');
session_start();
require_once 'db_config.php';

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) { 
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit; 
}

$user_id = $_SESSION['user_id'];

try {
    // ΔΙΟΡΘΩΣΗ: Αλλαγή από 'active_positions' σε 'active_pairs' για να συμβαδίζει με τον Scanner
    $stmt = $pdo->prepare("SELECT * FROM active_pairs WHERE user_id = ? AND status = 'OPEN' ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Επιστροφή δεδομένων σε μορφή JSON για το Front-end
    echo json_encode([
        'success' => true, 
        'count' => count($positions),
        'positions' => $positions
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}