<?php
/**
 * CT-OS | copyright by cryptoteam.gr - notifications.php
 * ----------------------------------------------------------------
 * Σκοπός: Το κέντρο ελέγχου συμβάντων ασφαλείας (Security Event Monitor). 
 * Παρακολουθεί και εμφανίζει σε πραγματικό χρόνο τις τελευταίες 20 ενέργειες του χρήστη (Logs).
 */
session_start();
require_once 'db_config.php';

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Ανάκτηση των τελευταίων 20 ειδοποιήσεων/logs για τον συγκεκριμένο χρήστη
    $stmt = $pdo->prepare("SELECT * FROM user_activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    $notifications = [];
}

// Helper συνάρτηση για χρώματα ανάλογα με το action
function getActionStyle($action) {
    $action = strtoupper($action);
    if (strpos($action, 'LOGIN') !== false) return 'text-green-400 bg-green-400/10 border-green-500/20';
    if (strpos($action, 'LOGOUT') !== false) return 'text-slate-400 bg-slate-400/10 border-slate-500/20';
    if (strpos($action, '2FA') !== false) return 'text-blue-400 bg-blue-400/10 border-blue-500/20';
    if (strpos($action, 'AVATAR') !== false) return 'text-purple-400 bg-purple-400/10 border-purple-500/20';
    if (strpos($action, 'RESET') !== false) return 'text-red-400 bg-red-400/10 border-red-500/20';
    return 'text-amber-400 bg-amber-400/10 border-amber-500/20';
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT-OS | NOTIFICATIONS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;500;700&display=swap');
        body {
            font-family: 'Space Grotesk', sans-serif;
            background: #020617;
            color: white;
            min-height: 100vh;
            background-image: radial-gradient(circle at 50% -20%, #1e293b 0%, #020617 80%);
        }
        .glass-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.05);
        }
        .scanline {
            width: 100%;
            height: 2px;
            background: rgba(59, 130, 246, 0.1);
            position: fixed;
            top: 0;
            z-index: 100;
            animation: scan 8s linear infinite;
        }
        @keyframes scan {
            0% { top: 0; }
            100% { top: 100%; }
        }
    </style>
</head>
<body class="p-4 md:p-8">
    <div class="scanline"></div>
    <div class="max-w-4xl mx-auto">
        <div class="flex justify-between items-center mb-10 border-b border-white/5 pb-8">
            <div>
                <h1 class="text-3xl font-black tracking-tighter uppercase italic text-blue-500">Security Logs</h1>
                <p class="text-[10px] text-slate-500 tracking-[0.4em] uppercase font-bold">CT-OS System Notifications</p>
            </div>
            <div class="text-right">
                <span class="text-[9px] font-black text-blue-500/50 block tracking-widest uppercase">Status</span>
                <span class="text-[10px] font-bold text-green-500 tracking-widest uppercase">Monitoring Active</span>
            </div>
        </div>

        <div class="space-y-4">
            <?php if (empty($notifications)): ?>
                <div class="glass-card rounded-3xl p-12 text-center border-dashed border-white/10">
                    <p class="text-slate-500 uppercase tracking-widest text-xs font-bold italic">No system events recorded in the last 24h</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $log): ?>
                    <div class="glass-card rounded-2xl p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:border-white/10 transition-all group">
                        <div class="flex items-center gap-5">
                            <div class="px-4 py-2 rounded-xl border text-[10px] font-black uppercase tracking-widest <?= getActionStyle($log['action']) ?>">
                                <?= htmlspecialchars($log['action']) ?>
                            </div>
                            
                            <div>
                                <h4 class="text-sm font-bold text-slate-200 group-hover:text-white transition-colors">
                                    System Event: <?= htmlspecialchars($log['action']) ?>
                                </h4>
                                <div class="flex items-center gap-3 mt-1">
                                    <span class="text-[9px] font-mono text-slate-500">IP: <?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></span>
                                    <span class="text-[9px] text-slate-700">|</span>
                                    <span class="text-[9px] font-mono text-slate-500"><?= date('H:i:s d/m/Y', strtotime($log['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center md:justify-end">
                            <span class="text-[8px] font-black text-slate-600 uppercase tracking-widest group-hover:text-blue-500/50 transition-colors italic">
                                Verified by CT-OS
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="mt-10 pt-8 border-t border-white/5 text-center">
            <p class="text-[9px] text-slate-600 uppercase tracking-[0.3em] font-bold italic">
                Logs are encrypted and stored for 30 days. Unauthorized access is prohibited.
            </p>
        </div>
    </div>
</body>
</html>