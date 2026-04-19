<?php
/**
 * CT-OS | copyright by cryptoteam.gr - header.php
 * ----------------------------------------------------------------
 * Σκοπός: Το καθολικό σύστημα κεφαλίδας (Global Header) που διαχειρίζεται την ταυτοποίηση, τη δυναμική πλοήγηση και το layout του Terminal.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db_config.php';

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Έλεγχος αν η σελίδα φορτώνεται μέσα από την console.php
$is_inside_console = (basename($_SERVER['PHP_SELF']) === 'console.php');
// Έλεγχος αν η σελίδα φορτώνεται σε iframe (προαιρετικό αλλά χρήσιμο)
$is_iframe = isset($_GET['iframe']) || (isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] == 'iframe');
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT-OS | Terminal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;900&family=Fira+Code:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { background: #020617; color: #f1f5f9; font-family: 'Inter', sans-serif; }
        .glass { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.05); }
        .nav-link { font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; transition: all 0.3s; color: #94a3b8; }
        .nav-link:hover { color: #3b82f6; }
        .mono { font-family: 'Fira Code', monospace; }

        /* CSS για τα Broadcast Alerts */
        @keyframes slideInDigital {
            from { opacity: 0; transform: translateX(50px) scale(0.9); }
            to { opacity: 1; transform: translateX(0) scale(1); }
        }
        .notif-active {
            animation: slideInDigital 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
        }
        .notif-exit {
            opacity: 0;
            transform: translateX(20px);
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="<?= ($is_inside_console) ? 'p-0' : 'p-0 md:p-4' ?>">

    <?php 
    /**
     * Εμφάνιση του Navigation μόνο αν η σελίδα ΔΕΝ είναι η console.php 
     * και ΔΕΝ φορτώνεται μέσα σε iframe στην κονσόλα.
     */
    if (!$is_inside_console && !$is_iframe): 
    ?>
    <nav class="max-w-7xl mx-auto mb-6 glass rounded-2xl p-4 flex items-center justify-between shadow-2xl">
        <div class="flex items-center gap-8">
            <span class="text-xl font-black italic tracking-tighter text-white">CT<span class="text-blue-500 text-opacity-80">OS</span></span>
            
            <div class="hidden lg:flex items-center gap-6">
                <a href="master-dashboard.php" class="nav-link">Dashboard</a>
                <a href="live_trading.php" class="nav-link">Live</a>
                <a href="terminal.php" class="nav-link">Demo</a>
                <a href="pair-trading.php" class="nav-link">Pairs</a>
                <a href="hedge-terminal.php" class="nav-link">Hedge</a>
                <a href="trade-journal.php" class="nav-link">Journal</a>
                <a href="api_settings.php" class="nav-link">API</a>
            </div>
        </div>
        
        <div class="flex items-center gap-4">
            <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest hidden sm:block"><?= htmlspecialchars($_SESSION['username']) ?></span>
            <a href="logout.php" class="bg-red-500/10 text-red-500 text-[10px] font-black px-4 py-2 rounded-xl border border-red-500/20 hover:bg-red-500 hover:text-white transition-all">EXIT</a>
        </div>
    </nav>
    <?php endif; ?>

    <div class="<?= ($is_inside_console) ? '' : 'py-2' ?>">