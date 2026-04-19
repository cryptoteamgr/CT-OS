<?php
/**
 * CT-OS | copyright by cryptoteam.gr - verify_2fa.php
 * ----------------------------------------------------------------
 * Σκοπός: Η διεπαφή επαλήθευσης 2FA (Two-Factor Authentication) κατά την είσοδο. 
 * Αποτελεί το δεύτερο επίπεδο ασφαλείας (Layer 2) που προστατεύει το Terminal 
 * από μη εξουσιοδοτημένη πρόσβαση, ακόμα και αν ο κωδικός πρόσβασης έχει διαρρεύσει.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Προστασία Header για ασφάλεια
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// 1. Έλεγχος αν υπάρχει εκκρεμές Login
// Αν ο χρήστης δεν έχει περάσει το Level 1 (Password/Passkey), τον διώχνουμε
if (!isset($_SESSION['pending_2fa_user_id'])) {
    header("Location: login.php");
    exit;
}

$pending_username = $_SESSION['pending_2fa_username'] ?? 'Authorized Operator';
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT-OS | 2FA SECURITY CHECK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #020617;
            background-image:
                radial-gradient(circle at 0% 0%, rgba(30, 58, 138, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(30, 58, 138, 0.1) 0%, transparent 50%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
        }
        .glass-panel {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .otp-input:focus {
            box-shadow: 0 0 25px rgba(59, 130, 246, 0.2);
            border-color: rgba(59, 130, 246, 0.5);
        }
        @keyframes pulse-soft {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .animate-pulse-slow { animation: pulse-soft 3s infinite; }
        
        /* Chrome, Safari, Edge, Opera - Remove arrows */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    </style>
</head>
<body class="p-4">
    <div class="glass-panel w-full max-w-[440px] p-8 md:p-10 rounded-[40px] transition-all relative overflow-hidden">
        
        <div class="absolute -top-24 -left-24 w-48 h-48 bg-blue-600/10 blur-[80px] pointer-events-none"></div>

        <div class="flex flex-col items-center text-center mb-10 relative">
            <div class="p-4 bg-blue-500/10 rounded-3xl text-blue-500 mb-6 border border-blue-500/20 shadow-inner">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            
            <h2 class="text-3xl font-black uppercase italic tracking-tighter text-white mb-2">
                Security Check
            </h2>
            <div class="flex items-center gap-2 px-3 py-1 bg-slate-900/80 rounded-full border border-slate-800">
                <div class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></div>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-[0.15em]">
                    Identity: <span class="text-blue-400"><?php echo htmlspecialchars($pending_username); ?></span>
                </p>
            </div>
        </div>

        <div class="text-center mb-8">
            <p class="text-xs text-slate-400 uppercase tracking-widest leading-relaxed">
                Enter the <span class="text-white font-bold">6-digit code</span> from your <br>
                <span class="text-blue-500">authenticator terminal</span>
            </p>
        </div>

        <form id="otp-form" method="POST" action="verify_logic.php?action=login" class="space-y-6">
            
            <?php if(isset($_GET['error'])): ?>
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 text-[10px] py-3 rounded-xl text-center font-bold uppercase tracking-widest animate-pulse">
                    <?php
                        if($_GET['error'] === 'wrong_code') echo "Invalid Verification Code";
                        else if($_GET['error'] === 'timeout') echo "Session Expired - Retry";
                        else echo "Authentication Error";
                    ?>
                </div>
            <?php endif; ?>

            <div class="relative group">
                <input type="text"
                       id="otp_code"
                       name="otp_code"
                       maxlength="6"
                       inputmode="numeric"
                       pattern="[0-9]*"
                       placeholder="------"
                       required
                       autofocus
                       autocomplete="one-time-code"
                       class="otp-input w-full bg-slate-950/50 border border-slate-800 p-6 rounded-2xl text-center text-4xl font-black tracking-[0.4em] outline-none text-white placeholder:text-slate-900 transition-all">
            </div>
            
            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-500 py-5 rounded-2xl font-black uppercase tracking-widest text-xs text-white transition-all shadow-lg shadow-blue-900/40 active:scale-95 flex items-center justify-center gap-3 group">
                <span>Verify Access</span>
                <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </button>
        </form>

        <div class="mt-10 pt-6 border-t border-white/5 text-center">
            <a href="logout.php" class="group inline-flex items-center gap-2">
                <span class="text-[10px] text-slate-500 group-hover:text-red-400 uppercase font-bold tracking-widest transition-colors italic">
                    Cancel Authentication
                </span>
            </a>
        </div>
    </div>

    <div class="hidden md:block absolute bottom-8 left-8 text-slate-800 pointer-events-none">
        <p class="text-[10px] font-mono uppercase tracking-[0.5em]">CT-OS Security Protocol Active</p>
    </div>

    <script>
        const otpInput = document.getElementById('otp_code');
        const otpForm = document.getElementById('otp-form');

        otpInput.addEventListener('input', (e) => {
            // Φιλτράρισμα μόνο για αριθμούς
            let val = e.target.value.replace(/[^0-9]/g, '');
            e.target.value = val;
            
            if (val.length === 6) {
                // Submit αμέσως μόλις συμπληρωθούν τα 6 ψηφία
                otpForm.submit();
            }
        });

        // Αυτόματο Focus
        window.addEventListener('load', () => {
            otpInput.focus();
        });
    </script>
</body>
</html>