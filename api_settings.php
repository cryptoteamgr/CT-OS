<?php
/**
 * CT-OS | copyright by cryptoteam.gr - api_settings.php
 * ----------------------------------------------------------------
 * Σκοπός: Διεπαφή διαχείρισης συνδεσιμότητας (Exchange API Keys & Telegram Bot Integration).
 */

require_once 'auth_check.php';
require_once 'db_config.php';
require_once 'functions.php'; 

// Ανάκτηση δεδομένων από το Session
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Operator';

// ΚΛΕΙΣΙΜΟ SESSION ΑΜΕΣΩΣ: 
// Αυτό είναι το μυστικό. Επιτρέπει στον browser να συνεχίσει να "μιλάει" 
// με το Dashboard (π.χ. να ανανεώνει τιμές) όσο εσύ είσαι στις ρυθμίσεις.
session_write_close(); 

$message = "";

// --- 1. ACTION: TEST TELEGRAM CONNECTION ---
if (isset($_GET['test_tg'])) {
    $id = (int)$_GET['test_tg'];
    // Στέλνουμε ένα Test Message χρησιμοποιώντας την υπάρχουσα συνάρτηση στο functions.php
    $test_msg = "🔔 <b>Test Connection</b>\nΣυγχαρητήρια! Το Bot σας είναι σωστά συνδεδεμένο με το CT-OS Terminal.";
    $success = sendTelegramNotification($test_msg, $user_id);
    
    if ($success) {
        $message = "✅ TEST MESSAGE SENT SUCCESSFULLY.";
    } else {
        $message = "❌ TEST FAILED: Check if you have clicked START on your bot.";
    }
}

// --- 2. ΛΟΓΙΚΗ ΓΙΑ BINANCE API (ΜΕ AUTHENTICATION) ---
if (isset($_POST['action']) && $_POST['action'] === 'save_exchange_api') {
    $label  = htmlspecialchars(trim($_POST['api_label']));
    $key    = trim((string)$_POST['api_key']);
    $secret = trim((string)$_POST['api_secret']);
    $type   = $_POST['account_type']; 

    $base_url = ($type === 'DEMO') ? "https://testnet.binancefuture.com" : "https://fapi.binance.com";
    $timestamp = number_format(round(microtime(true) * 1000), 0, '.', '');
    $query = "timestamp=" . $timestamp;
    $signature = hash_hmac('sha256', $query, $secret);
    $url = $base_url . "/fapi/v2/account?" . $query . "&signature=" . $signature;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-MBX-APIKEY: ' . $key]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        try {
            $enc_key = encrypt_data($key); 
            $enc_secret = encrypt_data($secret);
            $pdo->prepare("UPDATE api_keys SET is_active = 0 WHERE user_id = ? AND account_type = ?")->execute([$user_id, $type]);
            $stmt = $pdo->prepare("INSERT INTO api_keys (user_id, api_label, api_key, api_secret, account_type, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$user_id, $label, $enc_key, $enc_secret, $type]);
            $message = "✅ BINANCE $type: Η ΣΥΝΔΕΣΗ ΕΠΙΚΥΡΩΘΗΚΕ ΚΑΙ ΑΠΟΘΗΚΕΥΤΗΚΕ.";
        } catch (Exception $e) { $message = "❌ DB ERROR: " . $e->getMessage(); }
    } else {
        $message = "❌ BINANCE ERROR: Άκυρα κλειδιά ή πρόβλημα σύνδεσης (Code: $http_code).";
    }
}

// --- 3. ACTIONS (ENABLE / DELETE / SWITCH) ---
if (isset($_GET['act_exchange'])) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); } // Επαναφορά session για εγγραφή
    $id = (int)$_GET['act_exchange'];
    $stmt = $pdo->prepare("SELECT account_type FROM api_keys WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $key_data = $stmt->fetch();
    if ($key_data) {
        $type = $key_data['account_type'];
        $pdo->prepare("UPDATE api_keys SET is_active = 0 WHERE user_id = ? AND account_type = ?")->execute([$user_id, $type]);
        $pdo->prepare("UPDATE api_keys SET is_active = 1 WHERE id = ? AND user_id = ?")->execute([$id, $user_id]);
    }
    header("Location: api_settings.php"); 
    exit;
}

if (isset($_GET['del_exchange'])) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); } // Επαναφορά session
    $pdo->prepare("DELETE FROM api_keys WHERE id = ? AND user_id = ?")->execute([(int)$_GET['del_exchange'], $user_id]);
    header("Location: api_settings.php"); 
    exit;
}

if (isset($_GET['act_tg'])) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); } // Επαναφορά session
    $id = (int)$_GET['act_tg'];
    $pdo->prepare("UPDATE telegram_bots SET is_active = 0 WHERE user_id = ?")->execute([$user_id]);
    $pdo->prepare("UPDATE telegram_bots SET is_active = 1 WHERE id = ? AND user_id = ?")->execute([$id, $user_id]);
    header("Location: api_settings.php"); 
    exit;
}

if (isset($_GET['del_tg'])) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); } // Επαναφορά session
    $pdo->prepare("DELETE FROM telegram_bots WHERE id = ? AND user_id = ?")->execute([(int)$_GET['del_tg'], $user_id]);
    header("Location: api_settings.php"); 
    exit;
}

// ----------------------------------------------------------------
// Fetch Lists (Εδώ ΔΕΝ χρειάζεται session_start, διαβάζουμε μόνο από SQL)
// ----------------------------------------------------------------

$ex_keys = $pdo->prepare("SELECT * FROM api_keys WHERE user_id = ? ORDER BY account_type DESC, id DESC");
$ex_keys->execute([$user_id]);
$ex_keys = $ex_keys->fetchAll();

$tg_bots = $pdo->prepare("SELECT * FROM telegram_bots WHERE user_id = ? ORDER BY id DESC");
$tg_bots->execute([$user_id]);
$tg_bots = $tg_bots->fetchAll();
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT-OS | CONNECTIVITY TERMINAL</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { background-color: #020617; font-family: 'Inter', sans-serif; color: white; }
        .glass-card { background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px); }
        .input-field { background: #0a0f1d; border: 1px solid #1e293b; transition: all 0.3s; }
        .input-field:focus { border-color: #3b82f6; box-shadow: 0 0 10px rgba(59, 130, 246, 0.2); }
    </style>
</head>
<body class="p-6">
    <div class="max-w-6xl mx-auto">
        <div class="mb-10 flex justify-between items-end border-b border-white/5 pb-8">
            <div>
                <h1 class="text-4xl font-black tracking-tighter text-white uppercase italic">Connectivity<span class="text-blue-500">_Terminal</span></h1>
                <p class="text-[10px] text-slate-500 font-bold tracking-[0.4em] uppercase">Secure Multi-Key Manager</p>
            </div>
            <div class="text-right">
                <span class="text-[9px] text-slate-500 block uppercase italic">Operator: <?= htmlspecialchars($username) ?></span>
                <span class="text-xs font-bold text-emerald-400">● SECURITY ENCRYPTED</span>
            </div>
        </div>

        <?php if($message): ?>
            <div class="bg-blue-600/10 border border-blue-500/20 p-4 rounded-2xl mb-8 text-blue-400 text-xs font-black uppercase text-center"><?= $message ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            <div class="space-y-6">
                <div class="glass-card p-6 rounded-3xl">
                    <h3 class="text-xs font-black text-slate-300 uppercase mb-6 tracking-widest flex items-center gap-2">
                        <span class="w-2 h-2 bg-yellow-500 rounded-full animate-pulse"></span> Binance Exchange Vault
                    </h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="save_exchange_api">
                        <div class="grid grid-cols-2 gap-3">
                            <input type="text" name="api_label" placeholder="LABEL" required class="input-field p-3 rounded-xl text-[10px] outline-none text-white">
                            <select name="account_type" class="input-field p-3 rounded-xl text-[10px] outline-none text-white">
                                <option value="LIVE">MAINNET (LIVE)</option>
                                <option value="DEMO">TESTNET (DEMO)</option>
                            </select>
                        </div>
                        <input type="password" name="api_key" placeholder="API KEY" required class="input-field w-full p-3 rounded-xl text-[10px] outline-none text-white">
                        <input type="password" name="api_secret" placeholder="API SECRET" required class="input-field w-full p-3 rounded-xl text-[10px] outline-none text-white">
                        <button class="w-full bg-yellow-600 hover:bg-yellow-500 py-3 rounded-xl font-black uppercase text-[10px] tracking-widest transition-all text-white active:scale-95">Verify & Link Account</button>
                    </form>
                </div>

                <div class="space-y-3">
                    <?php foreach($ex_keys as $ex): ?>
                        <div class="glass-card p-4 rounded-2xl flex justify-between items-center border-l-4 <?= $ex['is_active'] ? ($ex['account_type'] == 'LIVE' ? 'border-emerald-500' : 'border-yellow-500') : 'border-slate-800' ?>">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-black uppercase italic"><?= htmlspecialchars($ex['api_label']) ?></span>
                                    <span class="text-[8px] px-1.5 py-0.5 rounded bg-white/5 text-slate-500 font-bold"><?= $ex['account_type'] ?></span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <?php if(!$ex['is_active']): ?>
                                    <a href="?act_exchange=<?= $ex['id'] ?>" class="text-[8px] font-bold bg-slate-800 hover:bg-blue-600 px-3 py-1.5 rounded-lg uppercase transition-all">Enable</a>
                                <?php endif; ?>
                                <a href="?del_exchange=<?= $ex['id'] ?>" class="text-slate-600 hover:text-red-500 px-2" onclick="return confirm('Delete API?')">✕</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="space-y-6">
                <div class="glass-card p-6 rounded-3xl">
                    <h3 class="text-xs font-black text-slate-300 uppercase mb-6 tracking-widest flex items-center gap-2">
                        <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span> Telegram Notification Bot
                    </h3>
                    <form id="tgForm" class="space-y-4" onsubmit="return false;">
                        <input type="text" name="tg_label" placeholder="BOT NAME (Internal e.g. My Alerts)" required class="input-field w-full p-3 rounded-xl text-[10px] outline-none text-white">
                        <input type="password" name="telegram_token" placeholder="BOT TOKEN (από BotFather)" required class="input-field w-full p-3 rounded-xl text-[10px] outline-none text-white">
                        
                        <button type="button" id="setupBtn" onclick="startTelegramSetup()" class="w-full bg-blue-600 hover:bg-blue-500 py-3 rounded-xl font-black uppercase text-[10px] tracking-widest transition-all text-white active:scale-95 flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
                            <span id="btnText">Smart Connect & Sync</span>
                        </button>
                        <p class="text-[9px] text-slate-500 text-center italic mt-2">* Βάλτε το Token, πατήστε το κουμπί και μετά <b>START</b> στο Bot που θα ανοίξει.</p>
                    </form>
                </div>

                <div class="space-y-3">
                    <?php foreach($tg_bots as $tg): ?>
                        <div class="glass-card p-4 rounded-2xl flex justify-between items-center border-l-4 <?= $tg['is_active'] ? 'border-blue-500' : 'border-slate-800' ?>">
                            <div>
                                <span class="text-[10px] font-black uppercase italic"><?= htmlspecialchars($tg['api_label']) ?></span>
                                <p class="text-[9px] text-slate-600 font-mono mt-1">Status: <?= $tg['is_active'] ? 'PRIMARY' : 'INACTIVE' ?></p>
                            </div>
                            <div class="flex gap-2 items-center">
                                <?php if($tg['is_active']): ?>
                                    <a href="?test_tg=<?= $tg['id'] ?>" class="text-[8px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 px-3 py-1.5 rounded-lg uppercase hover:bg-emerald-500 hover:text-white transition-all">Test Connection</a>
                                <?php else: ?>
                                    <a href="?act_tg=<?= $tg['id'] ?>" class="text-[8px] font-bold bg-slate-800 hover:bg-blue-600 px-3 py-1.5 rounded-lg uppercase transition-all">Set Default</a>
                                <?php endif; ?>
                                <a href="?del_tg=<?= $tg['id'] ?>" class="text-slate-600 hover:text-red-500 px-2" onclick="return confirm('Delete Bot?')">✕</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    async function startTelegramSetup() {
        const tokenInput = document.getElementsByName('telegram_token')[0];
        const labelInput = document.getElementsByName('tg_label')[0];
        const btnText = document.getElementById('btnText');
        
        const token = tokenInput ? tokenInput.value.trim() : '';
        const label = labelInput ? labelInput.value.trim() : 'My Bot';
        
        if (!token) { alert('❌ Παρακαλώ εισάγετε το Bot Token!'); return; }

        btnText.innerText = "Connecting...";

        try {
            const response = await fetch('telegram_setup_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ api_token: token, tg_label: label })
            });
            
            const data = await response.json();
            
            if (data.need_start) {
                window.open(data.bot_link, '_blank');
                alert(data.message);
                btnText.innerText = "Waiting for START...";
            } else if (data.success) {
                alert('✅ ' + data.message);
                location.reload();
            } else {
                alert('⚠️ ' + data.message);
                btnText.innerText = "Smart Connect & Sync";
            }
        } catch (e) {
            alert('❌ Σφάλμα σύνδεσης με τον setup handler.');
            btnText.innerText = "Smart Connect & Sync";
        }
    }
    </script>
</body>
</html>