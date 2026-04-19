<?php
/**
 * CT-OS | copyright by cryptoteam.gr - forgot_password.php
 * ----------------------------------------------------------------
 * Σκοπός: Ασφαλής μηχανισμός ανάκτησης πρόσβασης μέσω email (Access Recovery Protocol).
 */
ob_start();
session_start();
require_once 'db_config.php';

// PHPMailer Integration
require __DIR__ . '/phpmailer/src/Exception.php';
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

$message = ""; 
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        $error = "INVALID PROTOCOL: MALFORMED EMAIL ADDRESS.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Δημιουργούμε το token ακόμα κι αν δεν υπάρχει ο χρήστης (για προστασία timing attacks)
            $token = bin2hex(random_bytes(32));
            $expiry = date("Y-m-d H:i:s", strtotime('+1 hour'));

            if ($user) {
                // Ενημέρωση βάσης μόνο αν υπάρχει ο χρήστης
                $pdo->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE id = ?")
                    ->execute([$token, $expiry, $user['id']]);

                // Δυναμικό Link (HTTP ή HTTPS)
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                $resetLink = $protocol . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
                $logoUrl = $protocol . $_SERVER['HTTP_HOST'] . "/logo_doulfis.JPG";

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'georgedoulfis@gmail.com'; 
                    $mail->Password   = 'csbj eegt oegw ilwm';    
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
                    $mail->Port       = 587;
                    $mail->CharSet    = 'UTF-8';

                    // SMTP Options για αποφυγή προβλημάτων SSL σε ορισμένους servers
                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );

                    $mail->setFrom('georgedoulfis@gmail.com', 'CRYPTOTEAM.GR');
                    $mail->addReplyTo('support@cryptoteam.gr', 'Support Team');
                    $mail->addAddress($email);
                    
                    $mail->isHTML(true);
                    $mail->Subject = 'CT-OS | Access Recovery Protocol';
                    
                    $mail->Body = "
                        <div style='background:#020617; color:#f8fafc; padding:40px; font-family:sans-serif; border-radius:20px; border: 1px solid #1e293b; max-width:500px; margin:auto;'>
                            <div style='text-align:center; margin-bottom:30px;'>
                                <img src='{$logoUrl}' alt='CT-OS LOGO' style='max-width:150px; border-radius:10px;'>
                            </div>
                            <h1 style='color:#3b82f6; font-size:22px; text-align:center; text-transform:uppercase; letter-spacing:2px;'>Access Recovery</h1>
                            <p style='font-size:14px; line-height:1.6;'>Greetings <b>" . htmlspecialchars($user['username']) . "</b>,</p>
                            <p style='font-size:14px; line-height:1.6;'>We received a request to reset your access credentials for the <b>CT-OS Terminal</b>.</p>
                            <div style='text-align:center; margin-top:30px; margin-bottom:30px;'>
                                <a href='{$resetLink}' style='background:#2563eb; color:white; padding:15px 30px; text-decoration:none; border-radius:12px; font-weight:bold; display:inline-block;'>AUTHORIZE RESET</a>
                            </div>
                            <p style='font-size:10px; color:#64748b; text-align:center;'>This link will expire in 60 minutes.</p>
                        </div>";

                    $mail->send();
                } catch (MailException $e) {
                    // Log error internally
                }
            }
            
            // Πάντα δείχνουμε το ίδιο μήνυμα για λόγους ασφαλείας
            $message = "RECOVERY LINK DISPATCHED. CHECK YOUR INBOX.";
            
        } catch (PDOException $e) {
            $error = "CRITICAL SYSTEM ERROR: DATABASE OFFLINE.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT-OS | RECOVERY</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #020617; color: white; }
        .glass-panel { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); }
        input::placeholder { color: #475569; text-transform: uppercase; font-size: 10px; letter-spacing: 0.1em; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-[420px] space-y-8 text-center">
        <div class="animate-pulse">
            <h1 class="text-4xl font-black italic tracking-tighter uppercase text-blue-600">LOST <span class="text-white">ACCESS</span></h1>
            <p class="text-[9px] font-bold tracking-[0.3em] text-slate-500 mt-2 uppercase">Identity Verification Required</p>
        </div>

        <div class="glass-panel p-10 rounded-[45px] shadow-2xl space-y-6 text-left border border-white/5">
            <?php if($message): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 text-[10px] font-black uppercase p-4 rounded-2xl text-center">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-500 text-[10px] font-black uppercase p-4 rounded-2xl text-center">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="bg-black/30 p-4 rounded-2xl border border-white/10 focus-within:border-blue-500/50 transition-all">
                    <label class="block text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Registered Email</label>
                    <input type="email" name="email" required autofocus placeholder="ENTER OPERATOR EMAIL" 
                           class="bg-transparent border-none outline-none w-full text-sm font-bold text-white tracking-tight">
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 py-5 rounded-2xl font-black uppercase text-[10px] tracking-widest transition-all shadow-lg shadow-blue-900/40 active:scale-95">
                    Authorize Dispatch
                </button>
            </form>
            
            <div class="text-center pt-2">
                <a href="login.php" class="text-[9px] font-black text-slate-600 uppercase tracking-widest hover:text-white transition-colors italic">
                    <span class="text-blue-500 mr-1"><</span> Return to Terminal
                </a>
            </div>
        </div>
    </div>
</body>
</html>