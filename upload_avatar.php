<?php
/**
 * CT-OS | copyright by cryptoteam.gr - avatar_upload.php
 * ----------------------------------------------------------------
 * Σκοπός: Το τερματικό μεταφόρτωσης αρχείου εικόνας (Avatar Upload Terminal).
 * Διαχειρίζεται την τοπική αποθήκευση εικόνων προφίλ, την εκκαθάριση παλαιών αρχείων 
 * και την επιβολή αυστηρών κανόνων ασφαλείας στα uploads.
 */
session_start();
require_once 'db_config.php';

// Ορισμός Header για JSON απόκριση
header('Content-Type: application/json');

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['avatar'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'Unknown Operator';
    $target_dir = "uploads/avatars/";

    // Δημιουργία φακέλου αν δεν υπάρχει με σωστά permissions
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $file_info = pathinfo($_FILES["avatar"]["name"]);
    $file_extension = strtolower($file_info['extension'] ?? '');

    // 1. Έλεγχος τύπου αρχείου (Extension)
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($file_extension, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: jpg, jpeg, png, gif, webp']);
        exit;
    }

    // 2. Έλεγχος αν είναι πραγματική εικόνα (MIME Check)
    $check = getimagesize($_FILES["avatar"]["tmp_name"]);
    if ($check === false) {
        echo json_encode(['success' => false, 'message' => 'File is not a valid image']);
        exit;
    }

    // 3. Περιορισμός μεγέθους (π.χ. 2MB)
    if ($_FILES["avatar"]["size"] > 2000000) {
        echo json_encode(['success' => false, 'message' => 'File too large (Max 2MB)']);
        exit;
    }

    // Ονομάζουμε το αρχείο με το ID του χρήστη και timestamp
    $new_filename = "user_" . $user_id . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;

    if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
        try {
            // Ανάκτηση παλιού avatar για διαγραφή
            $old_stmt = $pdo->prepare("SELECT avatar_url FROM users WHERE id = ?");
            $old_stmt->execute([$user_id]);
            $old_avatar = $old_stmt->fetchColumn();

            // Ενημέρωση της βάσης με το νέο URL (σχετικό path)
            $stmt = $pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
            $stmt->execute([$target_file, $user_id]);

            // Διαγραφή παλιού αρχείου από τον server (αν υπάρχει και δεν είναι default)
            if ($old_avatar && file_exists($old_avatar) && strpos($old_avatar, 'default') === false) {
                @unlink($old_avatar);
            }

            // TELEGRAM NOTIFICATION
            $user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $tg_msg = "🖼️ <b>AVATAR UPDATED</b>\n";
            $tg_msg .= "User: <code>{$username}</code> (ID: {$user_id})\n";
            $tg_msg .= "Status: New identity visual active\n";
            $tg_msg .= "IP: <code>{$user_ip}</code>";

            if (function_exists('sendTelegramNotification')) {
                sendTelegramNotification($tg_msg);
            }

            echo json_encode([
                'success' => true,
                'url' => $target_file . '?v=' . time(), // Cache busting
                'message' => 'Identity visual updated'
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'File move failed – check server permissions']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No file received or invalid request method']);
}