<?php
/**
 * CT-OS | Admin Terminal v2.5
 * ----------------------------------------------------------------
 * Πλήρης εποπτεία: Scanner, Monitor, Price Aggregator & Binance IP Status.
 */

require_once 'auth_check.php';
require_once 'db_config.php';
require_once 'functions.php';

// 1. ΕΛΕΓΧΟΣ ΑΝ ΕΙΝΑΙ ADMIN
$user_role = strtoupper(trim($_SESSION['role'] ?? 'USER'));
if ($user_role !== 'ADMIN') {
    die("<div style='color:red; font-family:sans-serif; padding:20px;'>ACCESS DENIED: Admin privileges required.</div>");
}

$admin_id = $_SESSION['user_id'];
$message = "";
$log_file = __DIR__ . '/cron_log.txt';
$aggregator_log = __DIR__ . '/aggregator_log.txt'; // Νέο log για τις τιμές

// 2. ΛΟΓΙΚΗ ΔΙΑΓΡΑΦΗΣ LOGS
if (isset($_POST['clear_logs'])) {
    file_put_contents($log_file, "[" . date("H:i:s") . "] --- CRON LOG CLEARED ---\n");
    if(file_exists($aggregator_log)) file_put_contents($aggregator_log, "[" . date("H:i:s") . "] --- AGGR LOG CLEARED ---\n");
    $message = "✅ Τα αρχεία logs καθαρίστηκαν.";
}

// 3. ΛΟΓΙΚΗ ΔΙΑΓΡΑΦΗΣ GHOST TRADES
if (isset($_POST['clean_user_id'])) {
    $target_user_id = (int)$_POST['clean_user_id'];
    $stmt = $pdo->prepare("UPDATE active_pairs SET status = 'CLOSED', closed_at = NOW(), notes = 'Admin Force Close' WHERE user_id = ? AND status = 'OPEN'");
    $stmt->execute([$target_user_id]);
    $count = $stmt->rowCount();
    $message = "✅ Καθαρίστηκαν $count 'φαντάσματα' για τον χρήστη #$target_user_id.";
}

// 4. ΕΛΕΓΧΟΣ ΥΓΕΙΑΣ ΣΥΣΤΗΜΑΤΟΣ
$cron_alive = (file_exists($log_file) && (time() - filemtime($log_file)) < 90);
$aggr_alive = (file_exists($aggregator_log) && (time() - filemtime($aggregator_log)) < 60);

// Διάβασμα των τελευταίων 100 γραμμών και από τα δύο logs
function get_last_logs($file, $limit = 60) {
    if (!file_exists($file)) return "File missing: $file";
    $data = file($file);
    $data = array_slice($data, -$limit);
    return implode("", $data);
}

$terminal_content = "--- MONITOR ENGINE ---\n" . get_last_logs($log_file) . "\n\n--- PRICE AGGREGATOR ---\n" . get_last_logs($aggregator_log);

// 5. ΛΗΨΗ ΧΡΗΣΤΩΝ & GLOBAL STATS
$users = $pdo->query("SELECT u.id, u.username, 
    (SELECT COUNT(*) FROM active_pairs WHERE user_id = u.id AND status = 'OPEN') as open_pairs
    FROM users u ORDER BY u.id ASC")->fetchAll();

include 'header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="glass-card p-4 rounded-2xl border <?= $cron_alive ? 'border-emerald-500/30' : 'border-red-500/30' ?>">
            <p class="text-[9px] font-black text-slate-500 uppercase">Scanner / Monitor</p>
            <p class="text-sm font-black <?= $cron_alive ? 'text-emerald-400' : 'text-red-500' ?>">
                <?= $cron_alive ? '● RUNNING' : '● OFFLINE' ?>
            </p>
        </div>
        <div class="glass-card p-4 rounded-2xl border <?= $aggr_alive ? 'border-blue-500/30' : 'border-red-500/30' ?>">
            <p class="text-[9px] font-black text-slate-500 uppercase">Price Aggregator</p>
            <p class="text-sm font-black <?= $aggr_alive ? 'text-blue-400' : 'text-red-500' ?>">
                <?= $aggr_alive ? '● SYNCING' : '● STALLED' ?>
            </p>
        </div>
        <div class="glass-card p-4 rounded-2xl border border-slate-800">
            <p class="text-[9px] font-black text-slate-500 uppercase">Binance IP Status</p>
            <p class="text-sm font-black text-white"><?= strpos($terminal_content, 'Blocked') ? '🔴 RESTRICTED' : '🟢 CLEAR' ?></p>
        </div>
        <div class="glass-card p-4 rounded-2xl border border-slate-800">
            <p class="text-[9px] font-black text-slate-500 uppercase">Global Exposure</p>
            <p class="text-sm font-black text-white"><?= array_sum(array_column($users, 'open_pairs')) ?> Pairs</p>
        </div>
    </div>

    <div class="mb-8">
        <div class="flex justify-between items-center mb-3">
            <h2 class="text-[11px] font-black uppercase text-white tracking-widest flex items-center gap-2">
                <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span> SYSTEM_LIVE_TERMINAL
            </h2>
            <form method="POST">
                <button type="submit" name="clear_logs" class="text-[9px] text-red-400 hover:text-white font-bold uppercase transition-all">Clear Logs 🗑️</button>
            </form>
        </div>
        <div id="terminal" class="bg-black/90 border border-white/10 rounded-2xl p-5 font-mono text-[11px] leading-relaxed h-[450px] overflow-y-auto shadow-2xl">
            <?php 
            $lines = explode("\n", $terminal_content);
            foreach ($lines as $line) {
                $color = "text-blue-400";
                if (strpos($line, 'ERROR') !== false || strpos($line, 'Failed') !== false || strpos($line, 'Offline') !== false) $color = "text-red-500 font-bold";
                if (strpos($line, 'SUCCESS') !== false || strpos($line, 'Finalized') !== false) $color = "text-emerald-400";
                if (strpos($line, '---') !== false) $color = "text-yellow-500 font-black";
                
                echo "<div class='$color'>" . htmlspecialchars($line) . "</div>";
            }
            ?>
        </div>
    </div>

    <div class="glass-card rounded-3xl overflow-hidden border border-slate-800 shadow-2xl">
        <div class="p-4 bg-slate-900/80 border-b border-slate-800 text-[10px] font-black uppercase tracking-widest text-slate-400">
            Account Reconciliation (Active Users)
        </div>
        <table class="w-full text-left">
            <tr class="bg-black/20 text-[10px] font-black uppercase text-slate-500 tracking-widest border-b border-slate-800">
                <th class="p-4">User</th>
                <th class="p-4 text-center">Live SQL Pairs</th>
                <th class="p-4 text-right">Actions</th>
            </tr>
            <?php foreach ($users as $user): ?>
            <tr class="border-b border-slate-800/50 hover:bg-white/5">
                <td class="p-4">
                    <span class="text-white font-bold"><?= $user['username'] ?></span>
                    <span class="text-[9px] text-slate-500 block">ID: #<?= $user['id'] ?></span>
                </td>
                <td class="p-4 text-center">
                    <span class="px-3 py-1 rounded-full text-[10px] font-black <?= $user['open_pairs'] > 0 ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-800 text-slate-600' ?>">
                        <?= $user['open_pairs'] ?> OPEN
                    </span>
                </td>
                <td class="p-4 text-right">
                    <?php if ($user['open_pairs'] > 0): ?>
                    <form method="POST" onsubmit="return confirm('⚠️ Προσοχή: Θα κλείσουν τα trades ΜΟΝΟ στην SQL. Συνέχεια;');">
                        <input type="hidden" name="clean_user_id" value="<?= $user['id'] ?>">
                        <button class="bg-red-500/10 hover:bg-red-500 text-red-500 hover:text-white border border-red-500/30 px-3 py-1 rounded text-[9px] font-black transition-all">FORCE CLOSE SQL</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<script>
    // Auto-scroll στο τέλος του terminal κατά τη φόρτωση
    const term = document.getElementById('terminal');
    term.scrollTop = term.scrollHeight;

    // Auto-refresh κάθε 30 δευτερόλεπτα
    setTimeout(() => { window.location.reload(); }, 30000);
</script>

<style>
    .glass-card { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(12px); }
    #terminal::-webkit-scrollbar { width: 6px; }
    #terminal::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
</style>

<?php include 'footer.php'; ?>