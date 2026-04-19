<?php
/**
 * CT-OS | copyright by cryptoteam.gr - reset_password.php
 * ----------------------------------------------------------------
 * Σκοπός: Το τερματικό επαναφοράς κωδικού (Password Reset Terminal). 
 * Διαχειρίζεται την αντικατάσταση του Cipher πρόσβασης μέσω ασφαλούς Token, 
 * επιβάλλοντας αυστηρούς κανόνες πολυπλοκότητας.
 */
ob_start(); // Ξεκινάμε το output buffering για να μην έχουμε θέμα με τα headers
session_start();
require_once 'db_config.php';

$message = "";
$error = "";
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$show_form = false;

// Forbidden common passwords
$forbidden_passwords = ['123456', '12345678', 'password', 'admin123', 'qwerty', '123456789', 'cryptoteam', 'ctos2026'];

if (empty($token)) {
    $error = "MISSING AUTHORIZATION TOKEN";
} else {
    try {
        // Έλεγχος token και ημερομηνίας λήξης
        $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND token_expiry > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            $show_form = true;
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $new_pw = $_POST['new_password'] ?? '';
                $confirm_pw = $_POST['confirm_password'] ?? '';

                if (strlen($new_pw) < 8) {
                    $error = "CIPHER TOO WEAK (MIN 8 CHARS)";
                } elseif (in_array(strtolower($new_pw), $forbidden_passwords)) {
                    $error = "CIPHER IS TOO COMMON / FORBIDDEN";
                } elseif ($new_pw !== $confirm_pw) {
                    $error = "CIPHER MISMATCH";
                } else {
                    // ΔΙΟΡΘΩΣΗ 1: Χρήση PASSWORD_BCRYPT για συνέπεια με το Register
                    $hashed_pw = password_hash($new_pw, PASSWORD_BCRYPT);
                    
                    // Update database και καθαρισμός token
                    $update = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE id = ?");
                    $update->execute([$hashed_pw, $user['id']]);

                    $message = "CIPHER UPDATED SUCCESSFULLY. REDIRECTING...";
                    $show_form = false;
                    
                    // ΔΙΟΡΘΩΣΗ 2: Ασφαλής ανακατεύθυνση
                    header("Refresh: 3; url=login.php");
                }
            }
        } else {
            $error = "TOKEN EXPIRED OR INVALID";
        }
    } catch (PDOException $e) {
        $error = "SYSTEM ERROR: ACCESS DENIED";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT-OS | RESET CIPHER</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #020617; color: white; overflow-x: hidden; }
        .glass-panel { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .input-box { background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); transition: 0.3s; }
        .input-box:focus-within { border-color: #3b82f6; box-shadow: 0 0 15px rgba(59, 130, 246, 0.3); }
        .strength-bar { height: 4px; transition: all 0.4s ease; width: 0%; border-radius: 2px; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-[420px] space-y-8">
        <div class="text-center">
            <h1 class="text-4xl font-black italic tracking-tighter uppercase text-blue-600">NEW <span class="text-white">CIPHER</span></h1>
            <p class="text-[9px] font-bold tracking-[0.3em] text-slate-500 mt-2 uppercase">Security Protocol Override</p>
        </div>

        <div class="glass-panel p-10 rounded-[45px] shadow-2xl">
            <?php if($message): ?>
                <div class="bg-green-500/10 border border-green-500/20 text-green-500 text-[10px] font-black uppercase p-4 rounded-2xl text-center mb-6">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-500 text-[10px] font-black uppercase p-4 rounded-2xl text-center mb-6">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <?php if($show_form): ?>
                <form method="POST" id="resetForm" class="space-y-6">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    
                    <div class="space-y-4">
                        <div class="input-box p-4 rounded-2xl">
                            <label class="block text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">New Access Cipher</label>
                            <input type="password" name="new_password" id="new_password" required placeholder="••••••••" class="bg-transparent border-none outline-none w-full text-sm font-bold text-white">
                            <div class="w-full bg-white/5 mt-3 rounded-full overflow-hidden">
                                <div id="strengthMeter" class="strength-bar bg-red-600"></div>
                            </div>
                            <p id="strengthText" class="text-[7px] font-black uppercase tracking-widest mt-1 text-slate-500">Security: VOID</p>
                        </div>
                        
                        <div class="input-box p-4 rounded-2xl">
                            <label class="block text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Confirm Cipher</label>
                            <input type="password" name="confirm_password" required placeholder="••••••••" class="bg-transparent border-none outline-none w-full text-sm font-bold text-white">
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 py-5 rounded-2xl font-black uppercase text-[10px] tracking-widest transition-all shadow-lg shadow-blue-900/30">
                        Update Credentials
                    </button>
                </form>
            <?php else: ?>
                <div class="text-center">
                    <a href="login.php" class="text-[10px] font-black text-blue-500 uppercase tracking-widest hover:underline italic">Return to Terminal</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const passwordInput = document.getElementById('new_password');
        const meter = document.getElementById('strengthMeter');
        const text = document.getElementById('strengthText');
        const forbidden = <?= json_encode($forbidden_passwords) ?>;

        passwordInput.addEventListener('input', function() {
            const val = passwordInput.value;
            let strength = 0;
            
            // Check for forbidden passwords
            if (forbidden.includes(val.toLowerCase())) {
                meter.style.width = '100%';
                meter.className = 'strength-bar bg-red-600';
                text.innerText = 'Security: FORBIDDEN / COMPROMISED';
                text.style.color = '#dc2626';
                return;
            }

            if (val.length >= 8) strength += 25;
            if (val.match(/[A-Z]/)) strength += 25;
            if (val.match(/[0-9]/)) strength += 25;
            if (val.match(/[^A-Za-z0-9]/)) strength += 25;

            meter.style.width = strength + '%';

            if (strength <= 25) {
                meter.className = 'strength-bar bg-red-600';
                text.innerText = 'Security: CRITICAL / WEAK';
                text.style.color = '#dc2626';
            } else if (strength <= 50) {
                meter.className = 'strength-bar bg-orange-500';
                text.innerText = 'Security: MODERATE';
                text.style.color = '#f97316';
            } else if (strength <= 75) {
                meter.className = 'strength-bar bg-yellow-400';
                text.innerText = 'Security: OPTIMIZED';
                text.style.color = '#facc15';
            } else {
                meter.className = 'strength-bar bg-green-500';
                text.innerText = 'Security: MAXIMUM';
                text.style.color = '#22c55e';
            }

            if (val.length === 0) {
                meter.style.width = '0%';
                text.innerText = 'Security: VOID';
                text.style.color = '#64748b';
            }
        });
    </script>
</body>
</html>