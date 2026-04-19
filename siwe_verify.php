<?php
/**
 * CT-OS | copyright by cryptoteam.gr - siwe_verify.php
 * ----------------------------------------------------------------
 * Σκοπός: Ο κινητήρας επαλήθευσης Web3 (Titan Secure Engine). 
 * Υλοποιεί το πρωτόκολλο "Sign-In with Ethereum" (SIWE), επιτρέποντας στους χρήστες 
 * να συνδέονται στο Terminal χρησιμοποιώντας το crypto-πορτοφόλι τους (π.χ. MetaMask).
 */
ob_start(); 
session_start();

/**
 * CT-OS v5.8 | TITAN SECURE VERIFICATION ENGINE
 */

require_once 'db_config.php';
require_once 'ecrecover_helper.php';

// Καθαρισμός οποιουδήποτε κειμένου έχει παραχθεί πριν (π.χ. από whitespaces στα require)
if (ob_get_length()) ob_clean();

header('Content-Type: application/json');

// Απόλυτη σιγή σε σφάλματα που θα χαλούσαν το JSON output
error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'INVALID_METHOD']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message   = $input['message'] ?? '';
$signature = $input['signature'] ?? '';

try {
    if (empty($message) || empty($signature)) {
        throw new Exception("MISSING_DATA");
    }

    // --- ΒΗΜΑ 1: Parsing Διεύθυνσης ---
    $lines = explode("\n", $message);
    $addressLine = trim($lines[1] ?? '');
    $address = strtolower(str_replace('Address: ', '', $addressLine));

    if (!$address || !preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
        throw new Exception("MALFORMED_ETH_ADDRESS");
    }

    // --- ΒΗΜΑ 2: Έλεγχος Βάσης Δεδομένων ---
    $stmt = $pdo->prepare("SELECT id, username, role, siwe_nonce, 2fa_enabled FROM users WHERE LOWER(eth_address) = ? LIMIT 1");
    $stmt->execute([$address]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("OPERATOR_NOT_FOUND");
    }

    if (empty($user['siwe_nonce'])) {
        throw new Exception("SESSION_EXPIRED_RETRY");
    }

    // --- ΒΗΜΑ 3: Επαλήθευση Nonce ---
    if (strpos($message, "Nonce: " . $user['siwe_nonce']) === false) {
        throw new Exception("SECURITY_NONCE_MISMATCH");
    }

    // --- ΒΗΜΑ 4: Κρυπτογραφικό Handshake ---
    $recoveredAddress = ecrecover($message, $signature);

    if (!$recoveredAddress || strtolower($recoveredAddress) !== $address) {
        throw new Exception("SIGNATURE_VERIFICATION_FAILED");
    }

    // --- ΒΗΜΑ 5: Δημιουργία Secure Session ---
    session_regenerate_id(true);
    
    $_SESSION['user_id']     = $user['id'];
    $_SESSION['username']    = $user['username'];
    $_SESSION['role']        = strtoupper($user['role'] ?? 'USER');
    $_SESSION['user_ip']     = $_SERVER['REMOTE_ADDR'];
    $_SESSION['auth_method'] = 'WEB3';

    $require2fa = (int)$user['2fa_enabled'] === 1;

    if (!$require2fa) {
        $_SESSION['authenticated'] = true;
    } else {
        $_SESSION['pending_2fa_user_id'] = $user['id'];
    }

    // --- ΒΗΜΑ 6: Καθαρισμός & Logging ---
    $pdo->prepare("UPDATE users SET siwe_nonce = NULL, last_login = NOW() WHERE id = ?")->execute([$user['id']]);

    $log = $pdo->prepare("INSERT INTO user_activity_logs (user_id, action, details, ip_address) VALUES (?, 'WEB3_AUTH_SUCCESS', ?, ?)");
    $log->execute([$user['id'], "Wallet: $address", $_SERVER['REMOTE_ADDR']]);

    echo json_encode([
        'success' => true,
        'require_2fa' => $require2fa,
        'target' => $require2fa ? 'verify_2fa.php' : 'index.php'
    ]);

} catch (Exception $e) {
    error_log("CT-OS Auth Failure: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
ob_end_flush();
exit;