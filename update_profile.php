<?php
/**
 * CT-OS | copyright by cryptoteam.gr - update_profile.php
 * ----------------------------------------------------------------
 * Σκοπός: Το τερματικό ενημέρωσης στοιχείων χρήστη (Profile Update Terminal). 
 * Διαχειρίζεται την αλλαγή του email επικοινωνίας, εξασφαλίζοντας τη μοναδικότητα 
 * της ταυτότητας στη βάση δεδομένων και την άμεση ειδοποίηση ασφαλείας.
 */
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// 1. Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$email = isset($data['email']) ? trim($data['email']) : '';

// 2. Έλεγχος αν το email είναι κενό ή έγκυρο
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

try {
    // 3. Έλεγχος αν το email χρησιμοποιείται από άλλον χρήστη
    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
    $check_stmt->execute([$email, $user_id]);
    if ($check_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email is already in use by another operator']);
        exit;
    }

    // 4. Ενημέρωση της βάσης
    $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
    $stmt->execute([$email, $user_id]);

    if ($stmt->rowCount() > 0) {
        // λήψη username για την ειδοποίηση
        $username = $_SESSION['username'] ?? 'Operator';
        $user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // 5. Telegram Notification
        $tg_msg = "📧 <b>PROFILE UPDATED</b>\n";
        $tg_msg .= "User: <code>{$username}</code>\n";
        $tg_msg .= "New Email: <code>{$email}</code>\n";
        $tg_msg .= "IP: <code>{$user_ip}</code>";
       
        if (function_exists('sendTelegramNotification')) {
            sendTelegramNotification($tg_msg);
        }

        echo json_encode(['success' => true, 'message' => 'Identity profile updated']);
    } else {
        // Σε περίπτωση που το email είναι το ίδιο με το υπάρχον
        echo json_encode(['success' => true, 'message' => 'No changes detected']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Terminal Database Error']);
}