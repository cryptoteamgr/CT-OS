<?php
/**
 * CT-OS | copyright by cryptoteam.gr - logout.php
 * ----------------------------------------------------------------
 * Σκοπός: Ασφαλής τερματισμός συνεδρίας (Session Termination), ενημέρωση κατάστασης χρήστη (Offline) και καταγραφή δραστηριότητας στο User Monitor.
 */
session_start();
include 'db_config.php';

// 1. ΕΝΗΜΕΡΩΣΗ ΒΑΣΗΣ ΠΡΙΝ ΤΗΝ ΚΑΤΑΣΤΡΟΦΗ ΤΟΥ SESSION
if (isset($_SESSION['user_id'])) {
    try {
        $user_id = $_SESSION['user_id'];
        $username = $_SESSION['username'] ?? 'Unknown';
        $user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; 

        // Θέτουμε τον χρήστη ως Offline στον πίνακα χρηστών
        $updateStmt = $pdo->prepare("UPDATE users SET is_online = 0 WHERE id = ?");
        $updateStmt->execute([$user_id]);

        // Καταγραφή της εξόδου στο Activity Log για το User Monitor
        $logStmt = $pdo->prepare("INSERT INTO user_activity_logs (user_id, username, action, ip_address) VALUES (?, ?, 'LOGOUT', ?)");
        $logStmt->execute([$user_id, $username, $user_ip]);

        // Telegram Notification για την έξοδο (αν η συνάρτηση είναι διαθέσιμη)
        $tg_msg = "🚪 <b>USER LOGOUT</b>\nUser: <code>{$username}</code>\nIP: <code>{$user_ip}</code>\nStatus: Session Terminated";
        if (function_exists('sendTelegramNotification')) {
            sendTelegramNotification($tg_msg);
        }
    } catch (PDOException $e) {
        // Καταγραφή σφάλματος στα logs του server χωρίς να εμποδιστεί η έξοδος
        error_log("Logout DB Error: " . $e->getMessage());
    }
}

// 2. Καθαρισμός όλων των μεταβλητών session
$_SESSION = array();

// 3. Καταστροφή του Session Cookie στον browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Καταστροφή της συνεδρίας στο server
session_unset();
session_destroy();

// 5. Επιστροφή στη σελίδα login με cache control για να μην λειτουργεί το κουμπί "Back" του browser
header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 
header("Location: login.php");
exit;
?>