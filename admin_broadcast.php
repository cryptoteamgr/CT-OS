<?php
/**
 * CT-OS | copyright by cryptoteam.gr - admin_broadcast.php
 * ----------------------------------------------------------------
 * Σκοπός: Διεπαφή διαχειριστή για την αποστολή Global System Alerts.
 */

include 'header.php'; // Περιλαμβάνει auth_check.php και db_config.php

// 1. Έλεγχος Ρόλου (Security Gate)
$user_role = strtoupper($_SESSION['role'] ?? 'USER');
if ($user_role !== 'ADMIN') {
    echo "
    <div class='max-w-7xl mx-auto mt-20 px-4 text-center'>
        <div class='bg-red-500/10 border border-red-500/20 p-10 rounded-3xl inline-block'>
            <h2 class='text-red-500 font-black text-2xl uppercase italic'>Access Denied</h2>
            <p class='text-slate-500 text-xs mt-2 uppercase tracking-widest'>Admin Only Area. Your attempt has been logged.</p>
            <a href='dashboard.php' class='mt-6 inline-block bg-slate-800 text-white text-[10px] font-bold py-2 px-6 rounded-full'>Return to Base</a>
        </div>
    </div>";
    include 'footer.php';
    exit;
}

// 2. Επεξεργασία Φόρμας
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_msg'])) {
    $msg = htmlspecialchars(trim($_POST['message']));
    $type = $_POST['type']; 
    
    if (!empty($msg)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO system_notifications (message, type, created_at) VALUES (?, ?, NOW())");
           if ($stmt->execute([$msg, $type])) {
                $pdo->query("UPDATE system_status SET is_active = 0 WHERE id = 1");

                // ΚΑΘΑΡΙΣΜΟΣ: Κρατάμε μόνο τα 10 τελευταία μηνύματα βάσει ID
                $pdo->query("DELETE FROM system_notifications 
                             WHERE id NOT IN (
                                SELECT id FROM (
                                    SELECT id FROM system_notifications 
                                    ORDER BY id DESC LIMIT 10
                                ) as tmp
                             )");

                $success = "Το μήνυμα εστάλη! Οι συνδρομητές θα δουν όλες τις εκκρεμείς ειδοποιήσεις.";
            }
        } catch (Exception $e) {
            $error = "Σφάλμα βάσης: " . $e->getMessage();
        }
    } else {
        $error = "Το μήνυμα δεν μπορεί να είναι κενό.";
    }
}
?>

<div class="max-w-4xl mx-auto px-4 py-10">
    <div class="mb-10 text-center">
        <h1 class="text-4xl font-black uppercase italic tracking-tighter text-white">Broadcast <span class="text-blue-500">_Center</span></h1>
        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.4em]">Global System Notifications & Security Alerts</p>
    </div>

    <div class="bg-slate-900/80 border border-white/5 p-8 rounded-3xl shadow-2xl backdrop-blur-md">
        
        <?php if(isset($success)): ?>
            <div class="mb-6 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl text-xs font-bold uppercase tracking-wider text-center animate-pulse">
                <span class="mr-2">⚡</span> <?= $success ?>
            </div>
        <?php endif; ?>

        <?php if(isset($error)): ?>
            <div class="mb-6 bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-xl text-xs font-bold uppercase tracking-wider text-center">
                <span class="mr-2">⚠️</span> <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 block italic">Message Content</label>
                <textarea 
                    id="broadcast_msg"
                    name="message" 
                    placeholder="Πληκτρολογήστε την ανακοίνωση..." 
                    required 
                    class="w-full bg-slate-950 border border-white/10 rounded-2xl p-4 text-white placeholder-slate-700 focus:border-blue-500 outline-none transition-all h-32 resize-none font-medium shadow-inner"
                ></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                <div>
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2 block italic">Alert Severity</label>
                    <div class="relative">
                        <select name="type" class="w-full bg-slate-950 border border-white/10 rounded-xl p-3 text-white outline-none focus:border-blue-500 appearance-none cursor-pointer font-bold text-sm">
                            <option value="info">🔵 INFO (Normal Update)</option>
                            <option value="warning">🟠 WARNING (Priority)</option>
                            <option value="danger">🔴 DANGER (Critical / Kill-Switch)</option>
                        </select>
                        <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-500">▼</div>
                    </div>
                </div>

                <button type="submit" name="send_msg" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-black py-4 rounded-xl uppercase tracking-widest text-xs transition-all shadow-lg shadow-blue-500/20 active:scale-95">
                    Execute Push Broadcast
                </button>
            </div>
        </form>
    </div>

    <div class="mt-10 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="p-4 border border-white/5 rounded-2xl bg-white/5 text-center">
            <span class="text-blue-500 text-xl">📡</span>
            <h5 class="text-[9px] font-black text-slate-400 uppercase mt-2">Real-time Push</h5>
            <p class="text-[10px] text-slate-600 italic">Εμφάνιση σε < 15s σε όλους.</p>
        </div>
        <div class="p-4 border border-white/5 rounded-2xl bg-white/5 text-center">
            <span class="text-amber-500 text-xl">⏳</span>
            <h5 class="text-[9px] font-black text-slate-400 uppercase mt-2">Persistence</h5>
            <p class="text-[10px] text-slate-600 italic">Παραμένει ενεργό στη βάση.</p>
        </div>
        <div class="p-4 border border-white/5 rounded-2xl bg-white/5 text-center">
            <span class="text-emerald-500 text-xl">🛡️</span>
            <h5 class="text-[9px] font-black text-slate-400 uppercase mt-2">Admin Lock</h5>
            <p class="text-[10px] text-slate-600 italic">Πρόσβαση μόνο επιπέδου 1.</p>
        </div>
    </div>
</div>

<script>
    const msgInput = document.getElementById('broadcast_msg');
    let isTyping = false;
    let typingTimer;

    msgInput.addEventListener('input', () => {
        if (!isTyping) {
            isTyping = true;
            // Ενημέρωση βάσης ότι ο Admin ξεκίνησε να γράφει
            fetch('api_update_typing.php?status=1');
        }

        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => {
            isTyping = false;
            // Ενημέρωση βάσης ότι ο Admin σταμάτησε
            fetch('api_update_typing.php?status=0');
        }, 3000); // 3 δευτερόλεπτα αδράνειας
    });
</script>

<?php 
include 'footer.php'; 
?>