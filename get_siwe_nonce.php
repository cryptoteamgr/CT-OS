<?php
/**
 * CT-OS | copyright by cryptoteam.gr - get_siwe_nonce.php
 * ----------------------------------------------------------------
 * Σκοπός: Παραγωγή και αποθήκευση κρυπτογραφικού Nonce για τη διαδικασία Sign-In with Ethereum (SIWE), διασφαλίζοντας την ακεραιότητα του Web3 Login.
 */
ob_start();
session_start();

/**
 * FILE: get_siwe_nonce.php
 * CT-OS v5.8 | ΔΙΟΡΘΩΜΕΝΟ ΓΙΑ JSON ΑΣΦΑΛΕΙΑ
 */

require_once 'db_config.php';

// Καθαρισμός buffer για να σιγουρευτούμε ότι δεν θα σταλεί κείμενο/σχόλια
if (ob_get_length()) ob_clean();

header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

// 1. Έλεγχος αν υπάρχει address
$address = $_GET['address'] ?? null;
if (!$address || !preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or missing Ethereum address'
    ]);
    exit;
}

$address = strtolower($address);

try {
    // 2. Δημιουργία nonce
    $nonce = bin2hex(random_bytes(16)); 
    $chain_id = 1; 

    // 3. Αποθήκευση στη βάση
    $stmt = $pdo->prepare("UPDATE users SET siwe_nonce = :nonce WHERE LOWER(eth_address) = :address");
    $stmt->execute([
        ':nonce'   => $nonce,
        ':address' => $address
    ]);

    if ($stmt->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Identity Error: Wallet not linked.'
        ]);
        exit;
    }

    // 4. Επιστροφή δεδομένων
    echo json_encode([
        'success' => true,
        'nonce'   => $nonce,
        'chainId' => $chain_id
    ]);

} catch (PDOException $e) {
    error_log("SIWE Nonce Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Terminal Database Error'
    ]);
}
ob_end_flush();
exit;