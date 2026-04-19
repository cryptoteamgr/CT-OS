<?php
/**
 * CT-OS | copyright by cryptoteam.gr - auth_check.php
 * ----------------------------------------------------------------
 * Σκοπός: Κεντρικός πυρήνας πιστοποίησης, προστασία σελίδων και έλεγχος ασφάλειας συνεδρίας (2FA/Session Hijacking).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Απαραίτητο για τη σύνδεση με τη βάση
require_once 'db_config.php'; 

$current_page = basename($_SERVER['PHP_SELF']);

// 1. ΕΛΕΓΧΟΣ ΕΚΚΡΕΜΟΥΣ 2FA
if (isset($_SESSION['pending_2fa_user_id'])) {
    if ($current_page !== 'verify_2fa.php') {
        header("Location: verify_2fa.php");
        exit;
    }
    return; 
}

// 2. ΕΛΕΓΧΟΣ ΓΕΝΙΚΗΣ ΣΥΝΔΕΣΗΣ
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    // Επιτρέπουμε μόνο τη σελίδα login και register χωρίς session
    if ($current_page !== 'login.php' && $current_page !== 'register.php') {
        header("Location: login.php");
        exit;
    }
}

// 3. ΠΡΟΣΤΑΣΙΑ SESSION (IP & USER AGENT BINDING)
// Διόρθωση των "Undefined key" σφαλμάτων
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown_device';
    
    // Αν είναι η πρώτη φορά που συνδέεται σε αυτή τη συνεδρία, αποθηκεύουμε τα στοιχεία
    if (!isset($_SESSION['user_ip'])) {
        $_SESSION['user_ip'] = $current_ip;
    }
    if (!isset($_SESSION['user_ua'])) {
        $_SESSION['user_ua'] = $current_ua;
    }

    // Έλεγχος αν τα στοιχεία ταυτίζονται (πρόληψη Session Hijacking)
    if ($_SESSION['user_ip'] !== $current_ip || $_SESSION['user_ua'] !== $current_ua) {
        session_unset();
        session_destroy();
        // Χρήση πλήρους URL ή σωστού path για την ανακατεύθυνση
        header("Location: login.php?error=security_mismatch");
        exit;
    }

    // --- ΒΗΜΑ 4: LIVE ROLE SYNC ---
    try {
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $db_role = $stmt->fetchColumn();

            if ($db_role) {
                $_SESSION['role'] = strtoupper(trim($db_role));
            } else {
                // Αν ο χρήστης δεν υπάρχει πλέον στη βάση
                session_unset();
                session_destroy();
                header("Location: login.php?error=account_deleted");
                exit;
            }
        }
    } catch (Exception $e) {
        // Σφάλμα βάσης: καταγραφή αν χρειάζεται, αλλά δεν διακόπτουμε τη ροή
    }
}
?>