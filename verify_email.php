<?php
/**
 * CT-OS | copyright by cryptoteam.gr - verify_email.php
 * ----------------------------------------------------------------
 * Σκοπός: Ο "Identity Validator" του συστήματος. Επαληθεύει το token ενεργοποίησης
 * που στάλθηκε μέσω email και ξεκλειδώνει οριστικά την πρόσβαση του χρήστη στο Terminal.
 */
require_once 'db_config.php';

$token = $_GET['token'] ?? '';
$status = "processing";
$username = "";

if (empty($token)) {
    $status = "error";
    $error_msg = "INVALID PROTOCOL: NO TOKEN PROVIDED.";
} else {
    try {
        // 1. Αναζήτηση του χρήστη
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE verification_code = ? AND verification_code IS NOT NULL LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // 2. Ενεργοποίηση και καθαρισμός
            $update = $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?");
            $update->execute([$user['id']]);
            $status = "success";
            $username = $user['username'];
        } else {
            $status = "error";
            $error_msg = "INVALID OR EXPIRED SECURITY TOKEN.";
        }
    } catch (PDOException $e) {
        $status = "error";
        $error_msg = "DATABASE CRITICAL ERROR.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CT-OS | ACTIVATION</title>
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
            <h1 class="text-4xl font-black text-blue-600 uppercase">CT<span class="text-white">-OS</span></h1>
            <p class="text-[9px] font-bold text-slate-500 tracking-[0.4em] uppercase mt-2">Identity Validator</p>
        </div>

        <div class="glass p-10 rounded-[45px] shadow-2xl text-center space-y-6">
            <?php if($status === "success"): ?>
                <div class="text-5xl mb-4">✅</div>
                <h2 class="text-xl font-black uppercase italic text-emerald-500">Access Granted</h2>
                <p class="text-sm text-slate-400">Operator <span class="text-white font-bold"><?= htmlspecialchars($username) ?></span>, your terminal access is now fully authorized.</p>
                <div class="pt-4">
                    <a href="login.php" class="inline-block w-full bg-blue-600 hover:bg-blue-500 py-4 rounded-2xl font-black uppercase text-[10px] tracking-widest transition-all">
                        Initialize Login
                    </a>
                </div>
            <?php else: ?>
                <div class="text-5xl mb-4">⚠️</div>
                <h2 class="text-xl font-black uppercase italic text-red-500">Access Denied</h2>
                <p class="text-sm text-slate-400"><?= $error_msg ?></p>
                <div class="pt-4">
                    <a href="resend_activation.php" class="inline-block w-full bg-slate-800 hover:bg-slate-700 py-4 rounded-2xl font-black uppercase text-[10px] tracking-widest transition-all">
                        Request New Link
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>