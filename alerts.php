<?php
/**
 * CT-OS | copyright by cryptoteam.gr - alerts.php
 * ----------------------------------------------------------------
 * Σκοπός: Smart Alert System για traders
 * Alert Types: Z-Score, Correlation, Drawdown, Profit Target
 */

require_once 'auth_check.php';
require_once 'db_config.php';
require_once 'functions.php';

$user_id = $_SESSION['user_id'];

// Λήψη mode
$stmt = $pdo->prepare("SELECT bot_mode FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$current_mode = $user['bot_mode'] ?? 'DEMO';

// Λήψη alerts από τη βάση
$stmt = $pdo->prepare("SELECT * FROM user_alerts WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Alert configuration
$alert_types = [
    'z_score' => [
        'name' => 'Z-Score Alert',
        'description' => 'Alert when Z-Score approaches threshold',
        'icon' => '📊'
    ],
    'correlation' => [
        'name' => 'Correlation Alert',
        'description' => 'Alert when correlation drops below threshold',
        'icon' => '🔗'
    ],
    'drawdown' => [
        'name' => 'Drawdown Alert',
        'description' => 'Alert when portfolio drawdown exceeds threshold',
        'icon' => '📉'
    ],
    'profit_target' => [
        'name' => 'Profit Target',
        'description' => 'Alert when daily/weekly profit target reached',
        'icon' => '🎯'
    ]
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create_alert') {
        $alert_type = $_POST['alert_type'];
        $threshold = floatval($_POST['threshold']);
        $notification_method = $_POST['notification_method'] ?? 'telegram';
        $is_active = 1;
        
        $stmt = $pdo->prepare("INSERT INTO user_alerts (user_id, alert_type, threshold, notification_method, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $alert_type, $threshold, $notification_method, $is_active]);
        
        header("Location: alerts.php");
        exit;
    }
    
    if ($action === 'toggle_alert') {
        $alert_id = intval($_POST['alert_id']);
        $is_active = intval($_POST['is_active']);
        
        $stmt = $pdo->prepare("UPDATE user_alerts SET is_active = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$is_active, $alert_id, $user_id]);
        
        header("Location: alerts.php");
        exit;
    }
    
    if ($action === 'delete_alert') {
        $alert_id = intval($_POST['alert_id']);
        
        $stmt = $pdo->prepare("DELETE FROM user_alerts WHERE id = ? AND user_id = ?");
        $stmt->execute([$alert_id, $user_id]);
        
        header("Location: alerts.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT-OS | Smart Alert System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #020617;
            color: white;
        }
        .glass-panel {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body class="min-h-screen p-4 md:p-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-black italic tracking-tighter text-blue-600 mb-2">SMART ALERTS</h1>
            <p class="text-sm text-slate-400 uppercase tracking-widest">Real-time Trading Notifications (<?= $current_mode ?>)</p>
        </div>

        <!-- Create Alert Form -->
        <div class="glass-panel rounded-2xl p-6 mb-6">
            <h3 class="text-lg font-bold text-white mb-4">Create New Alert</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="create_alert">
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Alert Type</label>
                    <select name="alert_type" class="w-full p-3 bg-slate-900 border border-slate-700 rounded-xl text-white font-bold">
                        <?php foreach ($alert_types as $key => $type): ?>
                            <option value="<?= $key ?>"><?= $type['icon'] ?> <?= $type['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Threshold</label>
                    <input type="number" step="0.1" name="threshold" placeholder="e.g., 2.0 for Z-Score, 85 for correlation, 10 for drawdown" class="w-full p-3 bg-slate-900 border border-slate-700 rounded-xl text-white font-bold" required>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Notification Method</label>
                    <select name="notification_method" class="w-full p-3 bg-slate-900 border border-slate-700 rounded-xl text-white font-bold">
                        <option value="telegram">📱 Telegram Only</option>
                    </select>
                </div>
                
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 py-3 rounded-xl font-black uppercase text-xs tracking-widest transition-all">
                    Create Alert
                </button>
            </form>
        </div>

        <!-- Active Alerts -->
        <div class="glass-panel rounded-2xl p-6">
            <h3 class="text-lg font-bold text-white mb-4">Active Alerts</h3>
            
            <?php if (empty($alerts)): ?>
                <p class="text-slate-400 text-center py-8">No alerts configured yet.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($alerts as $alert): ?>
                        <div class="bg-slate-900/50 p-4 rounded-xl border <?= $alert['is_active'] ? 'border-blue-500/30' : 'border-slate-700' ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl"><?= $alert_types[$alert['alert_type']]['icon'] ?? '🔔' ?></span>
                                    <div>
                                        <p class="font-bold text-white"><?= $alert_types[$alert['alert_type']]['name'] ?? 'Alert' ?></p>
                                        <p class="text-xs text-slate-400">Threshold: <?= $alert['threshold'] ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php if ($alert['is_active']): ?>
                                        <span class="text-xs bg-green-500/20 text-green-400 px-2 py-1 rounded-full font-bold">ACTIVE</span>
                                    <?php else: ?>
                                        <span class="text-xs bg-slate-700 text-slate-400 px-2 py-1 rounded-full font-bold">PAUSED</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-2 mt-3">
                                <form method="POST" class="flex-1">
                                    <input type="hidden" name="action" value="toggle_alert">
                                    <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>">
                                    <input type="hidden" name="is_active" value="<?= $alert['is_active'] ? 0 : 1 ?>">
                                    <button type="submit" class="w-full text-xs py-2 rounded-lg font-bold uppercase tracking-widest <?= $alert['is_active'] ? 'bg-yellow-500/20 text-yellow-400 hover:bg-yellow-500/30' : 'bg-green-500/20 text-green-400 hover:bg-green-500/30' ?>">
                                        <?= $alert['is_active'] ? 'Pause' : 'Activate' ?>
                                    </button>
                                </form>
                                
                                <form method="POST" class="flex-1">
                                    <input type="hidden" name="action" value="delete_alert">
                                    <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>">
                                    <button type="submit" class="w-full text-xs py-2 rounded-lg font-bold uppercase tracking-widest bg-red-500/20 text-red-400 hover:bg-red-500/30">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Alert Types Info -->
        <div class="glass-panel rounded-2xl p-6 mt-6">
            <h3 class="text-lg font-bold text-white mb-4">Alert Types</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($alert_types as $key => $type): ?>
                    <div class="bg-slate-900/50 p-4 rounded-xl">
                        <p class="text-2xl mb-2"><?= $type['icon'] ?></p>
                        <p class="font-bold text-white mb-1"><?= $type['name'] ?></p>
                        <p class="text-xs text-slate-400"><?= $type['description'] ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
