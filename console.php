<?php
/**
 * CT-OS | copyright by cryptoteam.gr - console.php
 * ----------------------------------------------------------------
 * Σκοπός: Η κεντρική κονσόλα διαχείρισης (Shell/UI) του συστήματος.
 */
 ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db_config.php';

// Έλεγχος Αυθεντικοποίησης
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: login.php");
    exit;
}

// Λήψη στοιχείων χρήστη από τη βάση για δυναμικό Avatar
$stmt = $pdo->prepare("SELECT username, role, avatar_url FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$userName = $user['username'] ?? 'User';
$userRole = $user['role'] ?? 'TRADER';
$avatarImg = $user['avatar_url']; 
$isAdmin = (isset($user['role']) && strcasecmp($user['role'], 'admin') === 0);
$initial = strtoupper(substr($userName, 0, 1));
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT OS - Management Console</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root { --sidebar-width: 280px; --accent-blue: #3b82f6; --accent-yellow: #eab308; }
        body {
            background-color: #020617;
            color: #f1f5f9;
            font-family: 'Inter', sans-serif;
            display: flex;
            height: 100vh;
            margin: 0;
            overflow: hidden;
        }
        .sidebar {
            width: var(--sidebar-width);
            background: #0f172a;
            border-right: 1px solid #1e293b;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            z-index: 30;
        }
        .mobile-drawer {
            position: fixed;
            left: -100%;
            top: 0;
            width: 85%;
            max-width: 320px;
            height: 100%;
            background: #0f172a;
            z-index: 50;
            transition: left 0.3s ease;
            border-right: 1px solid #1e293b;
            display: flex;
            flex-direction: column;
        }
        .mobile-drawer.open { left: 0; }
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            margin: 0.25rem 0.75rem;
            border-radius: 0.75rem;
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
        }
        .nav-link:hover { background: #1e293b; color: #fff; }
        .nav-link.active { background: var(--accent-blue); color: #fff; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
        
        

        .demo-link { 
            border: 1px solid rgba(234, 179, 8, 0.3); 
            background: rgba(234, 179, 8, 0.05); 
            color: #eab308;
        }
        .demo-link:hover { border-color: #eab308; background: rgba(234, 179, 8, 0.1); }
        .demo-link.active { background: var(--accent-yellow); color: #000; box-shadow: 0 4px 12px rgba(234, 179, 8, 0.3); }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; }
        iframe { width: 100%; height: 100%; border: none; background: #020617; }
        .overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px); z-index: 40; display: none;
        }
        .overlay.active { display: block; }
        .user-avatar {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .sidebar-nav::-webkit-scrollbar { width: 4px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; }
    </style>
</head>
<body>
    <div id="overlay" class="overlay" onclick="toggleMenu()"></div>

    <aside class="sidebar hidden lg:flex">
        <div class="p-6">
            <h1 class="text-xl font-black italic tracking-tighter text-blue-500 uppercase">CT-OS</h1>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto sidebar-nav">
            <div class="px-6 py-2">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Core Engine</p>
            </div>

            <a href="payment.php" target="contentFrame" class="nav-link active" onclick="handleNavClick(this)">
                <span class="mr-3 text-lg">📊</span> Dashboard
            </a>
            
            

            <a href="terminal.php" target="contentFrame" class="nav-link demo-link" onclick="handleNavClick(this)">
                <span class="mr-3 text-lg">🤖</span> Terminal
            </a>

            <a href="binance_bot.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">
                <span class="mr-3 text-lg">⚡</span> Binance Bot
            </a>

            <div class="px-6 py-4 mt-2 border-t border-slate-800/50">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Strategy Terminals</p>
            </div>

            
            <a href="trade-journal.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">
                <span class="mr-3 text-lg">📓</span> Trade Journal
            </a>
            <a href="alerts.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">
                <span class="mr-3 text-lg">🔔</span> Smart Alerts
            </a>
            <a href="backtesting.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">
                <span class="mr-3 text-lg">🔙</span> Backtesting
            </a>
            <a href="performance_dashboard.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">
                <span class="mr-3 text-lg">📊</span> Performance Dashboard
            </a>
            <a href="position_calculator.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">
                <span class="mr-3 text-lg">🧮</span> Position Calculator
            </a>
            <a href="api_settings.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">
                <span class="mr-3 text-lg">⚙️</span> Settings
            </a>

            <?php if ($isAdmin): ?>
            <div class="px-6 py-4 mt-2 border-t border-slate-800/50">
                <p class="text-[10px] font-bold text-blue-400 uppercase tracking-widest italic">Admin Control</p>
            </div>
            <a href="admin_broadcast.php" target="contentFrame" class="nav-link border border-blue-500/20 bg-blue-500/5" onclick="handleNavClick(this)">
                <span class="mr-3 text-lg">🧪</span> Cryptoteam Bot
            </a>
            
            <a href="admin-panel.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">
                <span class="mr-3 text-lg">👥</span> Manage Users
            </a>
            <a href="daily_summary.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">
                <span class="mr-3 text-lg">🔑</span> Global API Keys
            </a>
            <a href="view_logs.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">
                <span class="mr-3 text-lg">📜</span> View Logs
            </a>
            <a href="manage-categories.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">
                <span class="mr-3 text-lg">🏷️</span> Manage Categories
            </a>
            <?php endif; ?>
        </nav>

        <div class="p-4 border-t border-slate-800 bg-slate-900/50">
            <a href="profile/index.php" target="contentFrame" class="flex items-center gap-3 mb-4 p-2 hover:bg-slate-800 rounded-xl transition-all group">
                <div class="user-avatar w-10 h-10 rounded-xl flex items-center justify-center font-black text-white shadow-lg uppercase">
                    <?php if($avatarImg): ?>
                        <img src="<?= $avatarImg ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?= $initial ?>
                    <?php endif; ?>
                </div>
                <div class="overflow-hidden">
                    <p class="text-xs font-bold truncate text-white group-hover:text-blue-400 transition-colors"><?= htmlspecialchars($userName) ?></p>
                    <p class="text-[9px] text-blue-400 uppercase font-black tracking-widest italic">Edit Profile 👤</p>
                </div>
            </a>

            <a href="logout.php" class="block w-full text-center py-2.5 bg-red-500/10 text-red-500 text-[10px] font-black uppercase tracking-widest rounded-lg border border-red-500/20 hover:bg-red-500 hover:text-white transition-all mb-4">
                Logout System
            </a>

            <div class="pt-4 border-t border-slate-800/50">
                <p class="text-[9px] text-slate-500 font-bold text-center leading-relaxed uppercase tracking-wider">
                    (C)opyright 2026 by <span class="text-blue-500">cryptoteam.gr</span>
                </p>
            </div>
        </div>
    </aside>

    <aside id="mobile-drawer" class="mobile-drawer lg:hidden">
        <div class="p-6 flex justify-between items-center border-b border-slate-800/50 mb-4">
            <h1 class="text-xl font-black italic text-blue-500 uppercase">CT-OS</h1>
            <button onclick="toggleMenu()" class="text-slate-400 text-xl">✕</button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto">
            <a href="payment.php" target="contentFrame" class="nav-link active" onclick="handleNavClick(this)">
    <span class="mr-3 text-lg">📊</span> Dashboard
</a>
            <a href="profile/index.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">👤 My Profile</a>
            
            <a href="terminal.php" target="contentFrame" class="nav-link demo-link" onclick="handleNavClick(this)">🤖 Terminal</a>
            <a href="binance_bot.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">⚡ Binance Bot</a>

            <?php if ($isAdmin): ?>
                <div class="px-6 py-4 mt-2 border-t border-slate-800/50">
                    <p class="text-[10px] font-bold text-blue-400 uppercase tracking-widest">Admin Control</p>
                </div>
                <a href="admin_broadcast.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">🧪 Cryptoteam Bot</a>
                
                <a href="admin-panel.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">👥 Manage Users</a>
                <a href="daily_summary.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">🔑 Daily Summary</a>
            <?php endif; ?>

            <div class="px-6 py-4 mt-2 border-t border-slate-800/50">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Trade</p>
            </div>

            
            <a href="trade-journal.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">📓 Journal</a>
            <a href="alerts.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">🔔 Alerts</a>
            <a href="backtesting.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">🔙 Backtesting</a>
            <a href="performance_dashboard.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">📊 Performance</a>
            <a href="position_calculator.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">🧮 Calculator</a>
            <a href="api_settings.php" target="contentFrame" class="nav-link" onclick="handleNavClick(this)">⚙️ Settings</a>
        </nav>

        <div class="p-6 border-t border-slate-800 bg-slate-900/50 text-center">
            <a href="logout.php" class="block w-full text-center py-3 bg-red-600 text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-lg mb-4">
                🚪 LOGOUT
            </a>
            <p class="text-[9px] text-slate-500 font-bold uppercase">(C) 2026 cryptoteam.gr</p>
        </div>
    </aside>

    <main class="main-content">
        <header class="lg:hidden h-16 bg-[#0f172a] border-b border-slate-800 flex items-center justify-between px-4">
            <button onclick="toggleMenu()" class="text-white text-2xl">☰</button>
            <h1 class="text-sm font-black italic text-blue-500 uppercase">CT-OS</h1>
            <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-[10px] font-bold uppercase overflow-hidden">
                <?php if($avatarImg): ?>
                    <img src="<?= $avatarImg ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <?= $initial ?>
                <?php endif; ?>
            </div>
        </header>

        <iframe name="contentFrame" id="contentFrame" src="terminal.php"></iframe>
    </main>

    <script>
        // Διαχείριση Mobile Menu
        function toggleMenu() {
            document.getElementById('mobile-drawer').classList.toggle('open');
            document.getElementById('overlay').classList.toggle('active');
        }

        // Διαχείριση Click στα Links
        function handleNavClick(clickedLink) {
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            clickedLink.classList.add('active');
            if (window.innerWidth < 1024) {
                toggleMenu();
            }
        }

        // Συγχρονισμός Active Link με το περιεχόμενο του Iframe
        const frame = document.getElementById('contentFrame');
        setInterval(() => {
            try {
                const currentFile = frame.contentWindow.location.pathname.split('/').pop();
                if (!currentFile) return;
                document.querySelectorAll('.nav-link').forEach(link => {
                    const linkTarget = link.getAttribute('href');
                    if (linkTarget === currentFile) {
                        link.classList.add('active');
                    } else {
                        link.classList.remove('active');
                    }
                });
            } catch(e) {}
        }, 1000);

        /**
         * AUTO MONITOR TRIGGER (The "Heartbeat")
         * Εκτελεί το cron_monitor.php κάθε 10 δευτερόλεπτα
         */
       function triggerMonitor() {
            fetch('cron_monitor.php', { cache: 'no-store' })
                .then(response => response.text())
                .catch(err => console.log('Monitor pending...'));
        }

        // Εκτέλεση monitor κάθε 10 δευτερόλεπτα
        setInterval(triggerMonitor, 10000);

        /**
         * GLOBAL BROADCAST CHECK
         * Ελέγχει για ειδοποιήσεις και typing status κάθε 15 δευτερόλεπτα
         */
        setInterval(() => {
            if (typeof fetchBroadcast === "function") {
                fetchBroadcast();
            }
        }, 15000);
    </script>
    
    <?php include 'footer.php'; ?>
</body>
</html>