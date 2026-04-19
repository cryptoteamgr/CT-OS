<?php
/**
 * CT-OS | copyright by cryptoteam.gr - biometric_handler.php
 * ----------------------------------------------------------------
 * Σκοπός: Διαχείριση εγγραφής και επαλήθευσης βιομετρικών στοιχείων (Passkeys) μέσω του προτύπου WebAuthn.
 */

session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// Ενεργοποίηση αναφοράς λαθών μόνο για ανάπτυξη
// ini_set('display_errors', 1); 

$action = $_GET['action'] ?? '';

try {
    // --- 1. REGISTER_OPTIONS (Προετοιμασία για ΕΓΓΡΑΦΗ από το Profile) ---
    if ($action === 'register_options') {
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("Unauthorized: Please login first.");
        }
        
        $user_id = $_SESSION['user_id'];
        $challenge = random_bytes(32);
        $_SESSION['webauthn_challenge'] = base64_encode($challenge);

        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) throw new Exception("User not found.");

        // Δυναμικό RP ID (Relaying Party ID) βασισμένο στο domain
        $rpId = parse_url((isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]", PHP_URL_HOST);

        $options = [
            'challenge' => base64_encode($challenge),
            'rp' => [
                'name' => 'CT-OS Terminal', 
                'id' => $rpId
            ],
            'user' => [
                'id' => base64_encode((string)$user_id),
                'name' => $user['username'],
                'displayName' => $user['username']
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256 (P-256)
                ['type' => 'public-key', 'alg' => -257]  // RS256
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'residentKey' => 'required',
                'requireResidentKey' => true,
                'userVerification' => 'required' 
            ],
            'timeout' => 60000
        ];
        echo json_encode($options);
        exit;
    }

    // --- 2. REGISTER_VERIFY (Αποθήκευση νέου Passkey στη βάση) ---
    if ($action === 'register_verify') {
        if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");
        
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) throw new Exception("No data received");

        $credential_id = $data['id']; 
        $public_key = $data['response']['attestationObject'] ?? ''; 

        // Αποθήκευση στον πίνακα user_passkeys
        $stmt = $pdo->prepare("INSERT INTO user_passkeys (user_id, credential_id, public_key, display_name) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $credential_id,
            $public_key,
            "SECURE_DEVICE_" . date("d.m.Y H:i")
        ]);

        echo json_encode(['success' => true]);
        exit;
    }

    // --- 3. LOGIN_OPTIONS (Προετοιμασία για ΣΥΝΔΕΣΗ από το login.php) ---
    if ($action === 'login_options') {
        $challenge = random_bytes(32);
        $_SESSION['webauthn_challenge'] = base64_encode($challenge);

        $rpId = parse_url((isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]", PHP_URL_HOST);

        echo json_encode([
            'challenge' => base64_encode($challenge),
            'rpId' => $rpId,
            'userVerification' => 'required',
            'timeout' => 60000
        ]);
        exit;
    }

    // --- 4. LOGIN_VERIFY (Επαλήθευση βιομετρικών και Είσοδος) ---
    if ($action === 'login_verify') {
        $data = json_decode(file_get_contents('php://input'), true);
        $credential_id = $data['id'] ?? '';

        if (empty($credential_id)) throw new Exception("Invalid Credential Data.");

        // Αναζήτηση χρήστη βάσει του credential_id της συσκευής
        $stmt = $pdo->prepare("
            SELECT u.* FROM users u 
            JOIN user_passkeys p ON u.id = p.user_id 
            WHERE p.credential_id = ? LIMIT 1
        ");
        $stmt->execute([$credential_id]);
        $user = $stmt->fetch();

        if (!$user) throw new Exception("Device not recognized. Please login with password first.");

        // Έλεγχος αν ο λογαριασμός είναι ενεργός
        if (isset($user['is_verified']) && $user['is_verified'] == 0) throw new Exception("Account not verified.");

        // Διαχείριση 2FA αν είναι ενεργοποιημένο
        if (isset($user['2fa_enabled']) && $user['2fa_enabled'] == 1) {
            $_SESSION['pending_2fa_user_id'] = $user['id'];
            echo json_encode(['success' => true, 'require_2fa' => true]);
            exit;
        }

        // Επιτυχής είσοδος - Δημιουργία Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'] ?? 'USER';
        $_SESSION['authenticated'] = true;
        
        echo json_encode(['success' => true, 'require_2fa' => false]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}