<?php
/**
 * CT-OS | copyright by cryptoteam.gr - login.php
 * ----------------------------------------------------------------
 * Σκοπός: Η κύρια πύλη εισόδου (Elite Login Terminal). Διαχειρίζεται την ασφάλεια των συνεδριών, 
 * την επαλήθευση ταυτότητας (2FA) και το Session Hardening του συστήματος.
 */
session_start();
require_once 'db_config.php';

// 1. Προστασία από "Διπλή" Σύνδεση
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header("Location: console.php"); 
    exit;
}

$error = "";
$show_resend = false; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $un = strtolower(trim($_POST['username'] ?? ''));
    $pw = trim($_POST['password'] ?? '');

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(username) = ? LIMIT 1");
        $stmt->execute([$un]);
        $user = $stmt->fetch();

        if ($user && password_verify($pw, $user['password'])) {
            
            // 2. Έλεγχος Verification (Αν έχεις τέτοιο σύστημα)
            if (isset($user['is_verified']) && (int)$user['is_verified'] === 0) {
                $_SESSION['pending_verification_id'] = $user['id']; 
                $error = "ACCOUNT INACTIVE: IDENTITY VERIFICATION REQUIRED.";
                $show_resend = true; 
            } 
            else {
                // 3. Έλεγχος 2FA (Google Authenticator)
                if (isset($user['2fa_enabled']) && (int)$user['2fa_enabled'] === 1) {
                    $_SESSION['pending_2fa_user_id'] = $user['id'];
                    $_SESSION['pending_2fa_username'] = $user['username'];
                    header("Location: verify_2fa.php"); 
                    exit;
                }

                // 4. ΕΠΙΤΥΧΗΣ ΕΙΣΟΔΟΣ - SESSION HARDENING
                $_SESSION['authenticated'] = true;
                $_SESSION['user_id']       = $user['id'];
                $_SESSION['username']      = $user['username'];
                $_SESSION['role']          = strtoupper(trim($user['role'] ?? 'USER'));
                
                // Fingerprinting για αποφυγή Session Hijacking
                $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
                $_SESSION['user_ua'] = $_SERVER['HTTP_USER_AGENT'];
                $_SESSION['auth_method'] = 'PASSWORD';

                // Update Last Login
                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

                header("Location: console.php"); 
                exit;
            }
        } else { 
            $error = "ACCESS DENIED: INVALID CREDENTIALS"; 
            // Εδώ θα μπορούσες να προσθέσεις ένα μικρό sleep(1) για προστασία από brute force
        }
    } catch (PDOException $e) {
        $error = "DATABASE ERROR: TERMINAL OFFLINE";
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT-OS | SECURE LOGIN</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #020617; color: white; overflow: hidden; }
        .glass-panel { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(25px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .input-box { background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.08); transition: all 0.2s ease-in-out; }
        .input-box:focus-within { border-color: #3b82f6; box-shadow: 0 0 20px rgba(59, 130, 246, 0.15); }
        .btn-glow:hover { box-shadow: 0 0 25px rgba(37, 99, 235, 0.4); transform: translateY(-1px); }
    </style>
</head>
<body class="flex flex-col items-center justify-center min-h-screen p-4 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-slate-900 via-slate-950 to-black">

    <div class="w-full max-w-[420px] space-y-8 relative">
        <div class="text-center">
            <h1 class="text-7xl font-black italic tracking-tighter uppercase select-none bg-gradient-to-b from-white to-slate-500 bg-clip-text text-transparent">CT<span class="text-blue-600">-OS</span></h1>
            <p class="text-[10px] font-bold text-blue-500/60 tracking-[0.5em] uppercase mt-2">Crypto Team Operation System</p>
        </div>

        <div class="glass-panel p-8 rounded-[40px] shadow-2xl border-t border-white/10">
            
            <div class="grid grid-cols-2 gap-4 mb-8">
                <button type="button" onclick="location.href='wallet-connect.php'" class="bg-slate-900/60 p-4 rounded-3xl border border-white/5 hover:border-orange-500/50 hover:bg-slate-800/80 transition-all group">
                    <div class="flex flex-col items-center gap-2">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/3/36/MetaMask_Fox.svg" class="w-7 h-7 group-hover:scale-110 transition-transform">
                        <span class="text-[9px] font-black uppercase text-orange-500/80 tracking-widest">Web3 Node</span>
                    </div>
                </button>

                <button type="button" id="biometricLoginBtn" class="bg-slate-900/60 p-4 rounded-3xl border border-white/5 hover:border-blue-500/50 hover:bg-slate-800/80 transition-all group">
                    <div class="flex flex-col items-center gap-2">
                        <span class="text-2xl group-hover:scale-110 transition-transform">🛡️</span>
                        <span class="text-[9px] font-black uppercase text-blue-500/80 tracking-widest">Biometric</span>
                    </div>
                </button>
            </div>

            <form method="POST" class="space-y-5">
                <?php if($error): ?>
                    <div class="bg-red-500/10 border border-red-500/20 text-red-500 text-[10px] font-bold p-4 rounded-2xl text-center uppercase tracking-widest">
                        ⚠️ <?= $error ?>
                    </div>
                <?php endif; ?>

                <div class="space-y-4">
                    <div class="input-box p-4 rounded-2xl">
                        <label class="block text-[9px] font-black text-slate-500 uppercase mb-1 ml-1">Identity</label>
                        <input type="text" name="username" placeholder="OPERATOR_ID" required
                               class="bg-transparent outline-none w-full text-white uppercase text-sm font-bold placeholder:opacity-10">
                    </div>

                    <div class="input-box p-4 rounded-2xl">
                        <label class="block text-[9px] font-black text-slate-500 uppercase mb-1 ml-1">Security Key</label>
                        <input type="password" name="password" placeholder="••••••••" required
                               class="bg-transparent outline-none w-full text-white text-sm tracking-widest">
                    </div>
                </div>

                <button type="submit" class="btn-glow w-full bg-blue-600 py-5 rounded-2xl font-black uppercase text-[11px] tracking-[0.2em] text-white transition-all active:scale-[0.98]">
                    Authorize Session
                </button>
            </form>
        </div>

        <div class="flex justify-between px-6 text-[10px] font-black uppercase tracking-widest">
            <a href="register.php" class="text-slate-500 hover:text-blue-500 transition-colors">Create Node</a>
            <a href="forgot_password.php" class="text-slate-500 hover:text-white transition-colors">Reset Key</a>
        </div>
    </div>

    <div class="fixed top-0 left-0 w-full h-full pointer-events-none opacity-20 z-0">
        <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-900/20 blur-[120px] rounded-full"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-slate-800/20 blur-[120px] rounded-full"></div>
    </div>

    <script src="js/biometric_logic.js"></script>
</body>
</html>