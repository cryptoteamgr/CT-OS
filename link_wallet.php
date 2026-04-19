<?php
/**
 * CT-OS | copyright by cryptoteam.gr - link_wallet.php
 * ----------------------------------------------------------------
 * Σκοπός: API σύνδεσης (Linking) ενός Ethereum Wallet με το προφίλ του χρήστη, επιτρέποντας μελλοντικά Web3 Logins και On-chain ταυτοποίηση.
 */

session_start();
require_once 'db_config.php';

// Πάντα header JSON στην αρχή και καθαρισμός buffer
ob_clean();
header('Content-Type: application/json');

// Προστασία από PHP Errors που χαλάνε το JSON output στο frontend
error_reporting(0);
ini_set('display_errors', 0);

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος στο session
if (!isset($_SESSION['authenticated']) || !isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'AUTH_REQUIRED',
        'debug' => 'No active session found'
    ]);
    exit;
}

// Λήψη των δεδομένων από το fetch body (JSON)
$input = json_decode(file_get_contents('php://input'), true);
$address = $input['address'] ?? null;

// Validation της διεύθυνσης Wallet (Ethereum hex address format)
if ($address && preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
    $address = strtolower(trim($address));
    
    try {
        // 1. Έλεγχος αν το wallet χρησιμοποιείται ήδη από άλλον λογαριασμό
        $check = $pdo->prepare("SELECT id FROM users WHERE eth_address = ? AND id != ?");
        $check->execute([$address, $_SESSION['user_id']]);
        
        if ($check->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => 'WALLET_ALREADY_LINKED',
                'detail' => 'This wallet is already associated with another operator.'
            ]);
            exit;
        }

        // 2. Ενημέρωση της διεύθυνσης στη στήλη eth_address
        $stmt = $pdo->prepare("UPDATE users SET eth_address = ? WHERE id = ?");
        $result = $stmt->execute([$address, $_SESSION['user_id']]);
       
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'WALLET_UPDATED',
                'address' => $address
            ]);
        } else {
            throw new PDOException("Update failed to execute");
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'DATABASE_ERROR',
            'error_code' => $e->getCode()
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'INVALID_HEX_ADDRESS',
        'received' => $address          
    ]);
}
// ΤΕΛΟΣ ΑΡΧΕΙΟΥ - Μην προσθέσεις τίποτα κάτω από το exit
exit;