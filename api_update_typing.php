<?php
/**
 * CT-OS | copyright by cryptoteam.gr - api_update_typing.php
 * ----------------------------------------------------------------
 * Σκοπός: Ενημέρωση της κατάστασης πληκτρολόγησης (Typing Indicator) του διαχειριστή στη βάση δεδομένων.
 */
// api_update_typing.php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || strtoupper($_SESSION['role']) !== 'ADMIN') {
    exit;
}

$status = isset($_GET['status']) ? (int)$_GET['status'] : 0;

// Ενημερώνουμε την εγγραφή με ID 1 (που ορίσαμε στη βάση για το typing)
$stmt = $pdo->prepare("UPDATE system_status SET is_active = ?, last_update = NOW() WHERE id = 1");
$stmt->execute([$status]);

echo json_encode(['success' => true]);