<?php
/**
 * CT-OS | copyright by cryptoteam.gr - setup_2fa.php
 * ----------------------------------------------------------------
 * Σκοπός: Η διεπαφή ρύθμισης 2FA (Two-Factor Authentication). 
 * Παρέχει το QR Code για συγχρονισμό με εφαρμογές όπως Google Authenticator και Authy,
 * εξασφαλίζοντας ένα επιπλέον επίπεδο ασφαλείας στο Terminal.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_config.php';
require_once 'GoogleAuthenticator.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$ga = new PHPGangsta_GoogleAuthenticator();
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Operator';

$stmt = $pdo->prepare("SELECT 2fa_secret, 2fa_enabled FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$is_enabled = (isset($user['2fa_enabled']) && (int)$user['2fa_enabled'] === 1);

// Αν δεν είναι enabled, παράγουμε/ανανεώνουμε το secret
if (!$is_enabled) {
    $user_secret = $ga->createSecret();
    $update = $pdo->prepare("UPDATE users SET 2fa_secret = ? WHERE id = ?");
    $update->execute([$user_secret, $user_id]);
} else {
    $user_secret = $user['2fa_secret'];
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT-OS | 2FA SETUP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #020617; 
            color: white; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .glass-panel { 
            background: rgba(15, 23, 42, 0.9); 
            backdrop-filter: blur(20px); 
            border: 1px solid rgba(255, 255, 255, 0.05); 
        }
        #qrcode img { margin: 0 auto; border-radius: 15px; }
    </style>
</head>
<body>

    <div class="glass-panel p-8 md:p-10 rounded-[45px] max-w-[400px] w-full relative border-t border-blue-500/30">
        
        <div class="flex items-center gap-4 mb-8">
            <div class="p-3 bg-blue-600/20 rounded-2xl text-blue-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2-0 002-2v-6a2 2-0 00-2-2H6a2 2-0 00-2 2v6a2 2-0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-xl font-black uppercase italic tracking-tighter text-white">2FA Setup</h2>
                <p class="text-[8px] text-slate-500 font-bold uppercase tracking-widest leading-none">Identity Shielding Active</p>
            </div>
        </div>

        <?php if ($is_enabled): ?>
            <div class="text-center py-10 space-y-6">
                <div class="w-16 h-16 bg-green-500/10 text-green-500 rounded-full flex items-center justify-center mx-auto border border-green-500/20">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                </div>
                <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold">Authentication status: SECURED</p>
                <a href="profile/" class="inline-block bg-slate-800 px-8 py-4 rounded-2xl text-[9px] font-black uppercase tracking-[0.2em] hover:bg-slate-700 transition-all">Exit Terminal</a>
            </div>
        <?php else: ?>
            <div class="flex flex-col items-center gap-6">
                <div class="bg-white p-4 rounded-[30px] shadow-2xl">
                    <div id="qrcode"></div>
                </div>

                <div class="w-full space-y-6">
                    <div class="text-center">
                        <label class="text-[9px] font-black uppercase text-slate-500 tracking-[0.2em] mb-2 block italic">Emergency Secret Key</label>
                        <div class="bg-black/60 border border-white/5 p-3 rounded-xl text-blue-400 font-mono text-[12px] select-all uppercase tracking-widest text-center">
                            <?= htmlspecialchars($user_secret) ?>
                        </div>
                    </div>

                    <form method="POST" action="verify_logic.php?action=enable" class="space-y-4">
                        <div class="space-y-2 text-center">
                            <label class="text-[9px] font-black uppercase text-slate-400 tracking-widest italic">Authenticator Code</label>
                            <input type="text" name="otp_code" maxlength="6" inputmode="numeric" placeholder="------" autofocus required
                                   class="bg-slate-950 border border-slate-800 p-5 rounded-2xl text-center text-4xl font-black tracking-[0.4em] text-white w-full outline-none focus:border-blue-600 transition-all">
                        </div>
                        
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 py-5 rounded-2xl font-black uppercase text-[10px] tracking-widest text-white shadow-lg shadow-blue-900/40 active:scale-95">
                            Verify & Activate
                        </button>

                        <div class="text-center pt-2">
                            <a href="profile/" class="text-[9px] text-slate-600 hover:text-red-400 uppercase font-bold tracking-widest transition-all italic">Abort Setup</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        (function() {
            const qrElement = document.getElementById("qrcode");
            if (qrElement) {
                const secret = "<?= $user_secret ?>";
                const user = "<?= rawurlencode($username) ?>";
                const issuer = "Cryptoteam"; 
                const otpUrl = `otpauth://totp/${issuer}:${user}?secret=${secret}&issuer=${issuer}`;
               
                new QRCode(qrElement, {
                    text: otpUrl,
                    width: 200, height: 200,
                    colorDark : "#000000", colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
            }
        })();
    </script>
</body>
</html>