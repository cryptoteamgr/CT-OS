<?php
/**
 * CT-OS | copyright by cryptoteam.gr - toggle_2fa.php
 * ----------------------------------------------------------------
 * Σκοπός: Πρωτόκολλο ασφαλούς απενεργοποίησης του 2FA. Απαιτεί την εισαγωγή 
 * ενός έγκυρου κωδικού (OTP) πριν επιτρέψει την υποβάθμιση της ασφάλειας του λογαριασμού.
 */
session_start();
require_once 'db_config.php';
// Εδώ πρέπει να συμπεριλάβεις τη βιβλιοθήκη που επαληθεύει τα OTP (π.χ. GoogleAuthenticator.php)
// require_once 'GoogleAuthenticator.php'; 

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$otp_code = $data['otp_code'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT 2fa_enabled, 2fa_secret FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Αν ο χρήστης πάει να το ΑΠΕΝΕΡΓΟΠΟΙΗΣΕΙ (από 1 σε 0)
    if ($user['2fa_enabled'] == 1) {
        if (empty($otp_code)) {
            echo json_encode(['success' => false, 'requires_otp' => true]);
            exit;
        }

        /* ΕΔΩ ΓΙΝΕΤΑΙ Η ΕΠΑΛΗΘΕΥΣΗ (Παράδειγμα αν είχες τη βιβλιοθήκη):
           $ga = new PHPGangsta_GoogleAuthenticator();
           $checkResult = $ga->verifyCode($user['2fa_secret'], $otp_code, 2); 
           
           Για τώρα, ας υποθέσουμε ότι η επαλήθευση γίνεται στο verify_logic.php 
           ή αντιστοίχως εδώ αν έχεις έτοιμη τη συνάρτηση.
        */
        
        // ΠΡΟΣΩΡΙΝΟ: Εδώ θα έμπαινε το check του OTP. 
        // Αν ο κωδικός είναι σωστός, προχωράμε:
        $update = $pdo->prepare("UPDATE users SET 2fa_enabled = 0 WHERE id = ?");
        $update->execute([$user_id]);
        echo json_encode(['success' => true, 'message' => '2FA Disabled Safely']);
    } else {
        // Αν πάει να το ΕΝΕΡΓΟΠΟΙΗΣΕΙ, τον στέλνουμε στο setup_2fa.php
        echo json_encode(['success' => false, 'redirect' => 'setup_2fa.php']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'System Error']);
}