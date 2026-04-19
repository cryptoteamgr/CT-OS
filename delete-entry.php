<?php
/**
 * CT-OS | copyright by cryptoteam.gr - delete-entry.php
 * ----------------------------------------------------------------
 * Σκοπός: Ενιαίος μηχανισμός διαγραφής εγγραφών (Unified Delete Engine) με αυστηρό έλεγχο ιδιοκτησίας.
 */
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// 1. SESSION VALIDATION
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Please login']);
    exit;
}

// 2. PARAMETER VALIDATION
$delete_id = isset($_POST['delete_id']) ? intval($_POST['delete_id']) : null;
$target    = isset($_POST['target']) ? $_POST['target'] : 'journal'; // journal OR active

if (!$delete_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID']);
    exit;
}

try {
    // DEBUG: Log attempt
    file_put_contents(__DIR__ . '/debug_delete.log', date('Y-m-d H:i:s') . " Delete attempt - User: $user_id, ID: $delete_id, Target: $target\n", FILE_APPEND);
    
    /**
     * ΕΠΙΛΟΓΗ ΠΙΝΑΚΑ (Strict Whitelist)
     * Διασφαλίζουμε ότι το query θα εκτελεστεί μόνο σε επιτρεπόμενους πίνακες.
     */
    if ($target === 'active') {
        $table = 'active_pairs';
    } else {
        $table = 'zEQZkBci_trade_journal'; // Default target
    }
    
    // DEBUG: Log table
    file_put_contents(__DIR__ . '/debug_delete.log', date('Y-m-d H:i:s') . " Using table: $table\n", FILE_APPEND);
    
    // 3. SQL EXECUTION WITH OWNERSHIP CHECK
    // Το user_id στο WHERE διασφαλίζει ότι ένας χρήστης δεν μπορεί να διαγράψει trade άλλου.
    $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ? AND user_id = ?");
    $stmt->execute([$delete_id, $user_id]);

    // DEBUG: Log result
    file_put_contents(__DIR__ . '/debug_delete.log', date('Y-m-d H:i:s') . " Rows affected: " . $stmt->rowCount() . "\n", FILE_APPEND);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success', 
            'message' => "Entry removed from " . ucfirst($target)
        ]);
    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Access denied or record does not exist'
        ]);
    }

} catch (PDOException $e) {
    // Error Logging για τον προγραμματιστή
    error_log("Delete Error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error', 
        'message' => 'System Error occurred during deletion'
    ]);
}
?>