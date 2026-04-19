<?php
/**
 * CT-OS | copyright by cryptoteam.gr - register_process.php
 * ----------------------------------------------------------------
 * Σκοπός: Εξειδικευμένο πρωτόκολλο ανάκτησης εγγραφής και επιβεβαίωσης στοιχείων. 
 * Διασφαλίζει τον συγχρονισμό των μεταβλητών συστήματος και την αξιοπιστία της αποστολής email μέσω PHPMailer.
 */
ob_start();
session_start();
require_once 'db_config.php';

// PHPMailer Files - Βεβαιώσου ότι η διαδρομή είναι σωστή
require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Λήψη δεδομένων και καθαρισμός
    $username = strtolower(trim($_POST['username'] ?? ''));
    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password_raw = $_POST['password'] ?? '';
    $phone    = !empty($_POST['phone']) ? preg_replace('/[^0-9]/', '', $_POST['phone']) : NULL;

    if (!$username || !$email || !$password_raw) {
        die("ERROR: REQUIRED DATA MISSING.");
    }

    try {
        // 2. Έλεγχος στη βάση (Συγχρονισμός ονομάτων)
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->execute([$username, $email]);
        
        if ($check->fetch()) {
            die("ERROR: IDENTITY ALREADY EXISTS IN DATABASE.");
        }

        // 3. Προετοιμασία εγγραφής
        $hashed_pw = password_hash($password_raw, PASSWORD_BCRYPT);
        $token = bin2hex(random_bytes(32));

        // ΕΔΩ Η ΔΙΟΡΘΩΣΗ: Χρησιμοποιούμε τα ονόματα που θέλει το verify_email.php
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, phone, verification_token, is_verified, role) VALUES (?, ?, ?, ?, ?, 0, 'OPERATOR')");
        $stmt->execute([$username, $email, $hashed_pw, $phone, $token]);

        // 4. PHPMailer Setup (Οι ρυθμίσεις που δουλεύουν)
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'georgedoulfis@gmail.com';
        $mail->Password   = 'csbj eegt oegw ilwm'; // Το App Password σου
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->SMTPOptions = [
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
        ];

        // 5. Δυναμικό Link
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $verifyLink = $protocol . $_SERVER['HTTP_HOST'] . "/verify_email.php?token=" . $token;

        // 6. Σύνταξη Email
        $mail->setFrom('georgedoulfis@gmail.com', 'CT-OS SYSTEM');
        $mail->addAddress($email, $username);
        $mail->isHTML(true);
        $mail->Subject = 'CT-OS | Verification Required';
        
        $mail->Body = "
            <div style='background:#020617; color:#f8fafc; padding:40px; font-family:monospace;'>
                <h2 style='color:#3b82f6;'>ACCESS INITIALIZED</h2>
                <p>Operator: <b>" . htmlspecialchars($username) . "</b></p>
                <p>Click below to verify your terminal access:</p>
                <a href='{$verifyLink}' style='background:#2563eb; color:white; padding:12px 20px; text-decoration:none; border-radius:5px; display:inline-block;'>ACTIVATE ACCESS</a>
            </div>";

        $mail->send();
        
        // Επιτυχία
        echo "<script>alert('Registration Successful! Check your email.'); window.location.href='login.php';</script>";

    } catch (Exception $e) {
        // Αν αποτύχει, μας λέει ακριβώς ΓΙΑΤΙ
        die("CRITICAL SYSTEM ERROR: " . $e->getMessage());
    }
}