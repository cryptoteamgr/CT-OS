<?php
/**
 * CT-OS | copyright by cryptoteam.gr - resend_verification.php
 * ----------------------------------------------------------------
 * Σκοπός: Πρωτόκολλο επαναποστολής συνδέσμου ενεργοποίησης (Recovery Protocol). 
 * Σχεδιασμένο για να υποστηρίζει τόσο νέους όσο και παλαιότερους χρήστες (Legacy) που ενδέχεται να μην είχαν συνδέσει email στον λογαριασμό τους.
 */
ob_start();
session_start();

if (!file_exists('db_config.php')) {
    die("CRITICAL SYSTEM ERROR: db_config.php missing.");
}
require_once 'db_config.php';

require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

$message = "";
$error = "";

// Λογική για Legacy Users: Αν είναι ήδη logged in αλλά unverified
$pending_user_id = $_SESSION['user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        $error = "INVALID IDENTIFICATION: PLEASE ENTER A VALID EMAIL.";
    } else {
        try {
            // 1. ΑΝΑΖΗΤΗΣΗ ΧΡΗΣΤΗ (Είτε με το email, είτε με το ID αν είναι logged in)
            if ($pending_user_id) {
                $stmt = $pdo->prepare("SELECT id, username, email, is_verified FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$pending_user_id]);
            } else {
                $stmt = $pdo->prepare("SELECT id, username, email, is_verified FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
            }
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if ($user['is_verified'] == 1) {
                    $message = "OPERATOR ALREADY ACTIVE. NO ACTION REQUIRED.";
                } else {
                    // 2. UPDATE EMAIL ΑΝ ΗΤΑΝ ΚΕΝΟ (Για τους παλιούς χρήστες)
                    if (empty($user['email'])) {
                        $updateEmail = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                        $updateEmail->execute([$email, $user['id']]);
                    }

                    // 3. GENERATE TOKEN (Αύξησα το entropy για ασφάλεια)
                    $token = bin2hex(random_bytes(16)); 
                    $update = $pdo->prepare("UPDATE users SET verification_code = ? WHERE id = ?");
                    $update->execute([$token, $user['id']]);

                    // 4. ΑΠΟΣΤΟΛΗ ΜΕ PHPMailer
                    $mail = new PHPMailer(true);
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
                    $mail->Subject = 'CT-OS | Activation Link Protocol';
                    
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                    $verifyLink = $protocol . $_SERVER['HTTP_HOST'] . "/verify_email.php?token=" . $token;

                    $mail->Body = "
                        <div style='background:#020617; color:#f8fafc; padding:40px; font-family:sans-serif; border-radius:20px; border: 1px solid #1e293b; max-width:500px; margin:auto; text-align:center;'>
                            <img src='cid:doulfis_logo' alt='Logo' style='width:120px; margin-bottom:20px; border-radius:10px;'>
                            <h2 style='color:#3b82f6; text-transform:uppercase; margin-bottom:10px;'>Authorize Access</h2>
                            <p style='font-size:16px;'>Operator <b>" . htmlspecialchars($user['username']) . "</b>,</p>
                            <p style='color:#94a3b8;'>Your activation link is ready. Use the secure button below to verify your identity.</p>
                            <div style='margin-top:30px; margin-bottom:30px;'>
                                <a href='{$verifyLink}' style='background:#2563eb; color:white; padding:15px 35px; text-decoration:none; border-radius:12px; font-weight:bold; display:inline-block; letter-spacing:1px;'>VERIFY ACCOUNT</a>
                            </div>
                            <p style='font-size:11px; color:#475569;'>ID: " . $user['id'] . " | Status: Pending</p>
                        </div>";

                    if($mail->send()) {
                        $message = "SUCCESS: ACTIVATION LINK DISPATCHED TO " . strtoupper($email);
                    }
                }
            } else {
                // Security trick: Μην λες ότι δεν υπάρχει ο χρήστης για να μην κάνουν enumeration
                $message = "PROTOCOL INITIATED: CHECK YOUR INBOX.";
            }
        } catch (Exception $e) {
            $error = "SYSTEM ERROR: UNABLE TO DISPATCH MAIL.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CT-OS | RECOVERY</title>
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
            <h1 class="text-4xl font-black text-blue-600 uppercase italic">CT-OS</h1>
            <p class="text-[9px] font-bold text-slate-500 tracking-[0.4em] uppercase mt-2">Recovery Protocol</p>
        </div>

        <div class="glass p-10 rounded-[45px] shadow-2xl space-y-6">
            <?php if($message): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 text-[10px] font-black p-4 rounded-2xl text-center uppercase">
                    <?= $message ?>
                </div>
                <div class="text-center">
                    <a href="login.php" class="text-[10px] font-black bg-white/5 px-6 py-3 rounded-xl hover:bg-white/10 transition-all uppercase italic">Return to Login</a>
                </div>
            <?php else: ?>
                <?php if($error): ?>
                    <div class="bg-red-500/10 border border-red-500/20 text-red-500 text-[10px] font-black p-4 rounded-2xl text-center uppercase">
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <p class="text-[10px] text-slate-400 text-center uppercase font-bold px-4 leading-relaxed">
                        Enter your email to receive a new activation link. If your account had no email, enter one now to bind it.
                    </p>
                    <div class="bg-black/40 p-5 rounded-2xl border border-white/5 focus-within:border-blue-500/50 transition-all">
                        <label class="block text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Target Email</label>
                        <input type="email" name="email" required placeholder="OPERATOR@EMAIL.COM" 
                               class="bg-transparent border-none outline-none w-full text-sm font-bold text-white uppercase">
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 py-5 rounded-2xl font-black uppercase text-[10px] tracking-widest transition-all">
                        Execute Dispatch
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>