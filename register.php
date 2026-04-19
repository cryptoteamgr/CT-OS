<?php
/**
 * CT-OS | copyright by cryptoteam.gr - register.php
 * ----------------------------------------------------------------
 * Σκοπός: Πρωτόκολλο εγγραφής νέου χειριστή (Operator Registration). Διαχειρίζεται την ασφαλή αποθήκευση κωδικών, 
 * τον έλεγχο διπλότυπων λογαριασμών και την αποστολή κρυπτογραφημένου συνδέσμου ενεργοποίησης μέσω SMTP.
 */
session_start();
require_once 'db_config.php';

require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

$message = ""; $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username']));
    $email    = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password']; // Νέο πεδίο

    if (!$email) {
        $error = "INVALID IDENTIFICATION: PLEASE ENTER A VALID EMAIL.";
    } elseif (strlen($password) < 8) {
        $error = "SECURITY BREACH: PASSWORD MUST BE AT LEAST 8 CHARACTERS.";
    } elseif ($password !== $confirm_password) {
        // ΕΛΕΓΧΟΣ ΤΑΥΤΟΤΗΤΑΣ ΚΩΔΙΚΩΝ
        $error = "MISMATCH DETECTED: PASSWORDS DO NOT MATCH.";
    } else {
        try {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $check->execute([$email, $username]);
            
            if ($check->fetch()) {
                $error = "OPERATOR ALREADY EXISTS IN DATABASE.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $token = bin2hex(random_bytes(5)); 

                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, verification_code, is_verified, role) VALUES (?, ?, ?, ?, 0, 'TRADER')");
                $stmt->execute([$username, $email, $hashed_password, $token]);

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com'; 
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'georgedoulfis@gmail.com'; 
                    $mail->Password   = 'csbj eegt oegw ilwm';    
                    $mail->SMTPSecure = 'tls';
                    $mail->Port       = 587;
                    $mail->CharSet    = 'UTF-8';

                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );

                    if (file_exists('logo_doulfis.JPG')) {
                        $mail->addEmbeddedImage('logo_doulfis.JPG', 'doulfis_logo');
                    }

                    $mail->setFrom('georgedoulfis@gmail.com', 'CT-OS TERMINAL');
                    $mail->addAddress($email);
                    $mail->isHTML(true);
                    $mail->Subject = 'CT-OS | Account Activation Protocol';
                    
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                    $link = $protocol . $_SERVER['HTTP_HOST'] . "/verify_email.php?token=" . $token;

                    $mail->Body = "
                        <div style='background:#020617; color:#f8fafc; padding:40px; font-family:sans-serif; border-radius:20px; border: 1px solid #1e293b; max-width:500px; margin:auto; text-align:center;'>
                            <img src='cid:doulfis_logo' alt='Logo' style='width:120px; margin-bottom:20px; border-radius:10px;'>
                            <h2 style='color:#3b82f6; text-transform:uppercase;'>Welcome Operator</h2>
                            <p>Account for <b>" . htmlspecialchars($username) . "</b> has been created.</p>
                            <p>To authorize your terminal access, please click the button below:</p>
                            <div style='margin-top:30px; margin-bottom:30px;'>
                                <a href='{$link}' style='background:#2563eb; color:white; padding:15px 35px; text-decoration:none; border-radius:12px; font-weight:bold; display:inline-block;'>ACTIVATE ACCOUNT</a>
                            </div>
                        </div>";

                    $mail->send();
                    $message = "REGISTRATION SUCCESSFUL. CHECK INBOX FOR ACTIVATION LINK.";
                } catch (Exception $e) {
                    $error = "DATABASE UPDATED, BUT MAILER FAILED: " . $mail->ErrorInfo;
                }
            }
        } catch (PDOException $e) {
            $error = "DATABASE ERROR: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT-OS | REGISTER</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #020617; color: white; }
        .glass { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-[420px] space-y-8">
        <div class="text-center italic">
            <h1 class="text-4xl font-black text-blue-600 uppercase italic">CT<span class="text-white">-OS</span></h1>
            <p class="text-[9px] font-bold text-slate-500 tracking-[0.4em] uppercase mt-2 text-center">New Operator Registration</p>
        </div>

        <div class="glass p-10 rounded-[45px] shadow-2xl space-y-6">
            <?php if($message): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 text-[10px] font-black p-4 rounded-2xl text-center uppercase tracking-widest">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-500 text-[10px] font-black p-4 rounded-2xl text-center uppercase tracking-widest">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <?php if(!$message): ?>
            <form method="POST" class="space-y-4">
                <div class="bg-black/40 p-4 rounded-2xl border border-white/5 focus-within:border-blue-500/50">
                    <label class="block text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Username</label>
                    <input type="text" name="username" required placeholder="OPERATOR_NAME" 
                           class="bg-transparent border-none outline-none w-full text-sm font-bold text-white uppercase">
                </div>

                <div class="bg-black/40 p-4 rounded-2xl border border-white/5 focus-within:border-blue-500/50">
                    <label class="block text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Email</label>
                    <input type="email" name="email" required placeholder="EMAIL@SYSTEM.COM" 
                           class="bg-transparent border-none outline-none w-full text-sm font-bold text-white uppercase">
                </div>

                <div class="bg-black/40 p-4 rounded-2xl border border-white/5 focus-within:border-blue-500/50">
                    <label class="block text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Password</label>
                    <input type="password" name="password" required placeholder="********" 
                           class="bg-transparent border-none outline-none w-full text-sm font-bold text-white uppercase">
                </div>

                <div class="bg-black/40 p-4 rounded-2xl border border-white/5 focus-within:border-blue-500/50">
                    <label class="block text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Confirm Access Cipher</label>
                    <input type="password" name="confirm_password" required placeholder="********" 
                           class="bg-transparent border-none outline-none w-full text-sm font-bold text-white uppercase">
                </div>
                
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 py-5 rounded-2xl font-black uppercase text-[10px] tracking-widest transition-all">
                    Initialize Registration
                </button>
            </form>
            <?php endif; ?>

            <div class="text-center pt-2">
                <a href="login.php" class="text-[9px] font-black text-slate-600 uppercase tracking-widest hover:text-white transition-colors">
                    Already an Operator? Login
                </a>
            </div>
        </div>
    </div>

</body>
</html>