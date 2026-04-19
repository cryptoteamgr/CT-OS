<?php
/**
 * CT-OS | copyright by cryptoteam.gr - reset_2fa.php
 * ----------------------------------------------------------------
 * Σκοπός: Εργαλείο έκτακτης ανάγκης (Break-glass tool) για την απενεργοποίηση του 2FA. 
 * Χρησιμοποιείται αποκλειστικά όταν ο χρήστης χάσει την πρόσβαση στη συσκευή ελέγχου ταυτότητας.
 */
require_once 'db_config.php';
session_start();

// Εδώ βάλε το username σου για να ξέρει το σύστημα ποιον να ξεκλειδώσει
$target_username = "doulfis"; 

try {
    // 1. Βρίσκουμε το ID του χρήστη πριν το reset για το log
    $find = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $find->execute([$target_username]);
    $user_data = $find->fetch();

    // 2. Θέτουμε το 2fa_enabled σε 0 και καθαρίζουμε το secret
    $stmt = $pdo->prepare("UPDATE users SET 2fa_enabled = 0, 2fa_secret = NULL WHERE username = ?");
    $stmt->execute([$target_username]);

    if ($stmt->rowCount() > 0 && $user_data) {
        $user_id = $user_data['id'];
        $user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // ΚΑΤΑΓΡΑΦΗ ΣΤΟ ACTIVITY LOG (Για το notifications.php)
        $log_stmt = $pdo->prepare("INSERT INTO user_activity_logs (user_id, username, action, ip_address) VALUES (?, ?, '2FA_EMERGENCY_RESET', ?)");
        $log_stmt->execute([$user_id, $target_username, $user_ip]);

        // ΕΙΔΟΠΟΙΗΣΗ ΣΤΟ TELEGRAM
        if (function_exists('sendTelegramNotification')) {
            $tg_msg = "⚠️ <b>EMERGENCY 2FA RESET</b>\nUser: <code>{$target_username}</code>\nIP: <code>{$user_ip}</code>\nStatus: Security Cleared via Script";
            sendTelegramNotification($tg_msg);
        }

        echo "<div style='font-family:sans-serif; background:#020617; color:#22c55e; padding:20px; border-radius:10px; border:1px solid #22c55e; text-align:center; margin-top:50px;'>";
        echo "<h2>✅ 2FA RESET SUCCESSFUL</h2>";
        echo "Ο λογαριασμός <b>$target_username</b> είναι πλέον ελεύθερος.<br>Μπορείς να συνδεθείς κανονικά και να το ενεργοποιήσεις ξανά.";
        echo "<br><br><a href='login.php' style='color:white; text-decoration:none; background:#2563eb; padding:10px 20px; border-radius:5px;'>Επιστροφή στο Login</a>";
        echo "</div>";
    } else {
        echo "<div style='font-family:sans-serif; background:#020617; color:#ef4444; padding:20px; border-radius:10px; border:1px solid #ef4444; text-align:center; margin-top:50px;'>";
        echo "<h2>❌ USER NOT FOUND</h2>";
        echo "Το username <b>$target_username</b> δεν βρέθηκε στη βάση ή το 2FA ήταν ήδη κλειστό.";
        echo "</div>";
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>