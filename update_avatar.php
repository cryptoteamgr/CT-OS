<?php
/**
 * CT-OS | copyright by cryptoteam.gr - update_avatar.php
 * ----------------------------------------------------------------
 * Σκοπός: Συγχρονισμός της οπτικής ταυτότητας (Avatar) του χρήστη. 
 * Υποστηρίζει εξωτερικά URLs και NFT metadata, επιτρέποντας στον Operator 
 * να εξατομικεύσει το Terminal του με απομακρυσμένες πηγές εικόνων.
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
$username = $_SESSION['username'] ?? 'Operator';
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['avatar_url']) && !empty($data['avatar_url'])) {
    $new_url = filter_var($data['avatar_url'], FILTER_SANITIZE_URL);

    // 2. Βασικός έλεγχος αν το URL είναι έγκυρο
    if (!filter_var($new_url, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid Image URL']);
        exit;
    }

    // Προαιρετικό: Έλεγχος αν το URL οδηγεί σε εικόνα (HEAD request)
    $ch = curl_init($new_url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if (strpos($content_type, 'image/') !== 0) {
        echo json_encode(['success' => false, 'message' => 'URL does not point to a valid image']);
        exit;
    }

    try {
        // 3. Ενημέρωση της βάσης δεδομένων
        $stmt = $pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
        $stmt->execute([$new_url, $user_id]);

        if ($stmt->rowCount() > 0) {
            // 4. TELEGRAM NOTIFICATION
            $user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $tg_msg = "🖼️ <b>AVATAR SYNCED (URL)</b>\n";
            $tg_msg .= "User: <code>{$username}</code> (ID: {$user_id})\n";
            $tg_msg .= "Source: Remote/NFT Link\n";
            $tg_msg .= "IP: <code>{$user_ip}</code>";
           
            if (function_exists('sendTelegramNotification')) {
                sendTelegramNotification($tg_msg);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Remote visual identity synced',
                'url' => $new_url
            ]);
        } else {
            echo json_encode(['success' => true, 'message' => 'No changes made']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Terminal Database Error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No URL data received']);
}
?>