<?php
/**
 * FILE NAME: binance_logs.php
 * CT-OS | BINANCE EXECUTION LOGS (PRIVATE FOR USERS / GLOBAL FOR ADMIN)
 */
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

$user_id = $_SESSION['user_id'];

// 1. ΕΛΕΓΧΟΣ ΑΝ ΕΙΝΑΙ ADMIN
$stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt_role->execute([$user_id]);
$user_info = $stmt_role->fetch();
$is_admin = ($user_info && strcasecmp($user_info['role'], 'admin') === 0);

// 2. ΑΥΤΟΜΑΤΗ ΔΙΑΓΡΑΦΗ ΠΑΛΙΩΝ LOGS (>24 ΩΡΕΣ)
try {
    $pdo->prepare("DELETE FROM binance_logs WHERE created_at < NOW() - INTERVAL 1 DAY")->execute();
} catch (Exception $e) { }

// 3. ΛΗΨΗ LOGS (ΔΙΟΡΘΩΜΕΝΟ ΓΙΑ ADMIN & PERFORMANCE)
$logs = [];
try {
    if ($is_admin) {
        // Ο Admin βλέπει τα πάντα - Προσθήκη JOIN και LIMIT για ταχύτητα
        $query = "SELECT l.*, u.username 
                  FROM binance_logs l 
                  JOIN users u ON l.user_id = u.id 
                  ORDER BY l.created_at DESC 
                  LIMIT 250";
        $stmt = $pdo->query($query);
    } else {
        // Ο απλός χρήστης βλέπει μόνο τα δικά του
        $query = "SELECT l.*, u.username 
                  FROM binance_logs l 
                  JOIN users u ON l.user_id = u.id 
                  WHERE l.user_id = ? 
                  ORDER BY l.created_at DESC 
                  LIMIT 150";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
    }
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT-OS | Binance Logs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Inter:wght@400;900&display=swap" rel="stylesheet">
    <style>
        body { background: #020617; color: #94a3b8; font-family: 'Inter', sans-serif; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .log-card { transition: all 0.2s; border-left: 4px solid #1e293b; }
        .log-card:hover { background: rgba(30, 41, 59, 0.4); border-left-color: #3b82f6; transform: translateX(5px); }
        .type-error { color: #f87171; border-left-color: #ef4444 !important; }
        .type-success { color: #4ade80; border-left-color: #22c55e !important; }
    </style>
</head>
<body class="p-4 md:p-10">

    <div class="max-w-5xl mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-center mb-10 gap-6">
            <div>
                <h1 class="text-2xl font-black text-white uppercase tracking-tighter italic">
                    <?= $is_admin ? 'Global' : 'My' ?> <span class="text-blue-500">Logs</span>
                </h1>
                <p class="text-[10px] text-slate-500 font-bold uppercase mt-1 tracking-[0.3em]">
                    <?= $is_admin ? "Monitoring All System Nodes" : "Private Execution Buffer" ?>
                </p>
            </div>
            
            <div class="relative w-full md:w-96">
                <input type="text" id="logSearch" onkeyup="filterLogs()" 
                       placeholder="FILTER BY SYMBOL, STATUS OR USER..." 
                       class="w-full bg-slate-900/50 border border-slate-800 rounded-xl px-5 py-3 text-xs font-bold text-white focus:outline-none focus:border-blue-500 transition-all uppercase tracking-widest">
            </div>

            <button onclick="location.reload()" class="bg-slate-800 hover:bg-blue-600 text-white text-[10px] font-black px-6 py-3 rounded-xl transition-all uppercase border border-slate-700">Refresh</button>
        </div>

        <div id="logsContainer" class="space-y-3">
            <?php if (empty($logs)): ?>
                <div class="text-center py-32 opacity-20 text-sm font-black uppercase tracking-[0.5em]">No Log Data Found</div>
            <?php endif; ?>

            <?php foreach ($logs as $log): ?>
                <?php 
                    $typeClass = ($log['type'] === 'ERROR') ? 'type-error' : (($log['type'] === 'SUCCESS') ? 'type-success' : '');
                ?>
                <div class="log-card p-5 bg-slate-900/30 rounded-r-2xl shadow-xl border border-white/5" 
                     data-content="<?= strtoupper(htmlspecialchars($log['message'])) ?> <?= $log['type'] ?> <?= strtoupper($log['username']) ?>">
                    
                    <div class="flex justify-between items-center mb-2">
                        <div class="flex items-center gap-3">
                            <span class="text-[10px] font-black px-2 py-0.5 rounded bg-black/40 border border-white/10 uppercase <?= $typeClass ?>">
                                [ <?= $log['type'] ?> ]
                            </span>
                            <?php if($is_admin): ?>
                                <span class="text-[10px] font-black text-blue-400 bg-blue-400/10 px-2 py-0.5 rounded border border-blue-400/20 uppercase tracking-tighter">
                                    OP: <?= htmlspecialchars($log['username']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="text-[10px] text-slate-600 mono font-bold">
                            <?= date('H:i:s | d M', strtotime($log['created_at'])) ?>
                        </span>
                    </div>
                    
                    <div class="text-sm font-bold text-slate-200 mono tracking-tight leading-relaxed">
                        <?= htmlspecialchars($log['message']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function filterLogs() {
            const input = document.getElementById('logSearch');
            const filter = input.value.toUpperCase();
            const cards = document.getElementsByClassName('log-card');

            for (let i = 0; i < cards.length; i++) {
                const content = cards[i].getAttribute('data-content');
                cards[i].style.display = content.includes(filter) ? "" : "none";
            }
        }
    </script>
</body>
</html>