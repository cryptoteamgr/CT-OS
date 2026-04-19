<?php
/**
 * CT-OS | copyright by cryptoteam.gr - get_nonce.php
 * ----------------------------------------------------------------
 * Σκοπός: Παραγωγή μοναδικού κωδικού (Nonce) για την ασφαλή ταυτοποίηση μέσω Web3 (Sign-In with Ethereum).
 */

// Ξεκινάμε το buffering αμέσως
ob_start();

// Καθολική απενεργοποίηση εμφάνισης σφαλμάτων που "μολύνουν" το JSON
error_reporting(0);
ini_set('display_errors', 0);

require_once 'db_config.php';

header('Content-Type: application/json');
date_default_timezone_set('Europe/Athens');

// Λήψη και καθαρισμός της διεύθυνσης
$address = isset($_GET['address']) ? trim($_GET['address']) : '';

// 1. ΒΑΣΙΚΟΣ ΕΛΕΓΧΟΣ ΔΙΕΥΘΥΝΣΗΣ
if (!$address || !preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'INVALID_ADDRESS']);
    exit;
}

try {
    // 2. ΕΛΕΓΧΟΣ ΧΡΗΣΤΗ (Χρήση eth_address)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(eth_address) = LOWER(?) LIMIT 1");
    $stmt->execute([$address]);
    $user = $stmt->fetch();

    if (!$user) {
        ob_clean();
        echo json_encode([
            'success' => false, 
            'error' => 'ADDRESS_NOT_REGISTERED',
            'debug' => 'Address lookup failed in eth_address column'
        ]);
        exit;
    }

    // 3. ΔΗΜΙΟΥΡΓΙΑ ΑΣΦΑΛΟΥΣ NONCE
    $nonce = bin2hex(random_bytes(16));

    // 4. ΕΝΗΜΕΡΩΣΗ ΤΟΥ ΧΡΗΣΤΗ
    $update = $pdo->prepare("UPDATE users SET siwe_nonce = :nonce WHERE id = :uid");
    $update->execute([
        ':nonce' => $nonce, 
        ':uid'   => $user['id']
    ]);

    // 5. ΕΠΙΣΤΡΟΦΗ ΔΕΔΟΜΕΝΩΝ - Καθαρίζουμε τα πάντα πριν το echo
    ob_clean();
    echo json_encode([
        'success' => true,
        'nonce'   => $nonce,
        'message' => 'Nonce generated. Please sign to verify ownership.'
    ]);

} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false, 
        'error'   => 'SERVER_ERROR'
    ]);
}