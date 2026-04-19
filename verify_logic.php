<?php
/**
 * CT-OS | copyright by cryptoteam.gr - verify_logic.php
 * ----------------------------------------------------------------
 * Σκοπός: Ο κεντρικός "ελεγκτής" (Unified Logic Core) για όλες τις λειτουργίες 2FA.
 * Διαχειρίζεται την ενεργοποίηση, απενεργοποίηση και την τελική επικύρωση 
 * του Login μέσω TOTP (Time-based One-Time Password).
 */
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_config.php';
require_once 'GoogleAuthenticator.php'; // Χρησιμοποιούμε την κεντρική κλάση

date_default_timezone_set('Europe/Athens');

// Αρχικοποίηση κλάσης 2FA
$ga = new PHPGangsta_GoogleAuthenticator();

// Λήψη δεδομένων (υποστήριξη και για POST και για JSON/Fetch API)
$json_input = json_decode(file_get_contents('php://input'), true);
$otp_code = $_POST['otp_code'] ?? $json_input['otp_code'] ?? '';
$otp_code = str_replace([' ', '-'], '', trim($otp_code));

$action = $_GET['action'] ?? '';

/**
 * Helper function για ομοιόμορφες απαντήσεις (AJAX ή Redirect)
 */
function sendResponse($success, $message, $redirectDefault) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }
    
    $error_param = $success ? "" : (strpos($redirectDefault, '?') === false ? "?error=" : "&error=") . urlencode($message);
    header("Location: " . $redirectDefault . $error_param);
    exit;
}

// Προστασία: Μόνο POST επιτρέπεται
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

// --- CASE A: ENABLE 2FA (Ενεργοποίηση από το Setup) ---
if ($action === 'enable' && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT 2fa_secret FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user && $ga->verifyCode($user['2fa_secret'], $otp_code, 2)) {
        $pdo->prepare("UPDATE users SET 2fa_enabled = 1 WHERE id = ?")->execute([$_SESSION['user_id']]);
        sendResponse(true, "2FA_ENABLED", "profile.php");
    } else {
        sendResponse(false, "wrong_code", "setup_2fa.php");
    }
}

// --- CASE B: DISABLE 2FA (Απενεργοποίηση από το Profile) ---
if ($action === 'disable' && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT 2fa_secret FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user && $ga->verifyCode($user['2fa_secret'], $otp_code, 2)) {
        $pdo->prepare("UPDATE users SET 2fa_enabled = 0, 2fa_secret = NULL WHERE id = ?")->execute([$_SESSION['user_id']]);
        sendResponse(true, "2FA_DISABLED", "profile.php");
    } else {
        sendResponse(false, "wrong_code", "profile.php");
    }
}

// --- CASE C: LOGIN VERIFICATION (Το τελικό βήμα του Login) ---
if ($action === 'login' && isset($_SESSION['pending_2fa_user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['pending_2fa_user_id']]);
    $user = $stmt->fetch();

    if ($user && $ga->verifyCode($user['2fa_secret'], $otp_code, 2)) {
        // Καθαρισμός Pending Session και ορισμός Full Authenticated
        $_SESSION['authenticated'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        
        $pdo->prepare("UPDATE users SET is_online = 1 WHERE id = ?")->execute([$user['id']]);
        
        unset($_SESSION['pending_2fa_user_id']);
        unset($_SESSION['pending_2fa_username']);
        
        sendResponse(true, "SUCCESS", "index.php");
    } else {
        sendResponse(false, "wrong_code", "verify_2fa.php");
    }
}

// Αν δεν ταιριάζει κανένα action
header("Location: login.php");
exit;
ob_end_flush();