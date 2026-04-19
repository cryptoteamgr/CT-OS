<?php
/**
 * CT-OS | copyright by cryptoteam.gr - admin-panel.php
 * ----------------------------------------------------------------
 * Σκοπός: Πίνακας ελέγχου διαχειριστή για τη διαχείριση χρηστών και ρυθμίσεων Bot.
 */
session_start();
require_once 'db_config.php';

// Έλεγχος αν είναι Admin (όπως στο προηγούμενο αρχείο)
// ... (auth code) ...

$query = $pdo->query("SELECT * FROM users ORDER BY id DESC");
$users = $query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>CT-OS | User Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { background: #020617; color: #f1f5f9; font-family: 'Inter', sans-serif; }
        .input-dark { background: #0f172a; border: 1px solid #1e293b; color: white; border-radius: 0.5rem; padding: 0.5rem; width: 100%; }
        .section-card { background: rgba(30, 41, 59, 0.5); border: 1px solid rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 1rem; margin-bottom: 1.5rem; }
    </style>
</head>
<body class="p-8">

    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-10">
            <h1 class="text-3xl font-black uppercase tracking-tighter">User <span class="text-blue-500">Database</span></h1>
            <span class="bg-blue-500/10 text-blue-500 px-4 py-1 rounded-full text-xs font-bold uppercase tracking-widest border border-blue-500/20">
                Total Users: <?= count($users) ?>
            </span>
        </div>

        <div class="bg-slate-900/50 border border-white/5 rounded-3xl overflow-hidden shadow-2xl">
            <table class="w-full text-left">
                <thead class="bg-white/5 text-[10px] uppercase font-bold text-slate-400">
                    <tr>
                        <th class="px-6 py-4">User Info</th>
                        <th class="px-6 py-4">Bot Status</th>
                        <th class="px-6 py-4">Wallet</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php foreach ($users as $u): ?>
                    <tr class="hover:bg-white/[0.02] transition-all">
                        <td class="px-6 py-4">
                            <div class="font-bold text-white"><?= htmlspecialchars($u['username']) ?></div>
                            <div class="text-xs text-slate-500"><?= htmlspecialchars($u['email']) ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 rounded text-[10px] font-black <?= $u['bot_status'] == 'ON' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500' ?>">
                                <?= $u['bot_status'] ?> (<?= $u['bot_mode'] ?>)
                            </span>
                        </td>
                        <td class="px-6 py-4 font-mono text-[10px] text-slate-400">
                            <?= $u['wallet_address'] ? substr($u['wallet_address'], 0, 10).'...' : 'N/A' ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button onclick='openEditModal(<?= json_encode($u) ?>)' class="bg-blue-600 hover:bg-blue-500 text-white text-[10px] font-bold px-4 py-2 rounded-lg transition-all shadow-lg shadow-blue-600/20">
                                FULL EDIT
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="editModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden z-50 overflow-y-auto">
        <div class="min-h-screen flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-white/10 w-full max-w-4xl rounded-[2.5rem] shadow-3xl p-8">
                <form id="editForm" action="api_update_user.php" method="POST">
                    <input type="hidden" name="id" id="form_id">
                    
                    <div class="flex justify-between items-center mb-8">
                        <h2 class="text-2xl font-black uppercase italic">Edit User: <span id="display_username" class="text-blue-500"></span></h2>
                        <button type="button" onclick="closeModal()" class="text-slate-500 hover:text-white font-bold uppercase text-xs">Close [X]</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        
                        <div class="section-card">
                            <h3 class="text-blue-400 font-bold uppercase text-xs mb-4 tracking-widest">1. Identity & Contact</h3>
                            <div class="space-y-4 text-xs">
                                <div><label>Username</label><input type="text" name="username" id="form_username" class="input-dark"></div>
                                <div><label>Email</label><input type="email" name="email" id="form_email" class="input-dark"></div>
                                <div><label>Phone</label><input type="text" name="phone" id="form_phone" class="input-dark"></div>
                                <div><label>Role</label>
                                    <select name="role" id="form_role" class="input-dark">
                                        <option value="user">User</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="section-card border-blue-500/20 bg-blue-500/5">
                            <h3 class="text-emerald-400 font-bold uppercase text-xs mb-4 tracking-widest">2. Bot Core Settings</h3>
                            <div class="grid grid-cols-2 gap-4 text-xs">
                                <div><label>Bot Status</label>
                                    <select name="bot_status" id="form_bot_status" class="input-dark border-emerald-500/30">
                                        <option value="OFF">OFF</option>
                                        <option value="ON">ON</option>
                                    </select>
                                </div>
                                <div><label>Mode</label>
                                    <select name="bot_mode" id="form_bot_mode" class="input-dark">
                                        <option value="DEMO">DEMO</option>
                                        <option value="LIVE">LIVE</option>
                                    </select>
                                </div>
                                <div><label>Capital Per Trade</label><input type="number" step="0.01" name="capital_per_trade" id="form_capital_per_trade" class="input-dark"></div>
                                <div><label>Leverage</label><input type="number" name="leverage" id="form_leverage" class="input-dark"></div>
                                <div><label>Z-Entry Threshold</label><input type="number" step="0.1" name="z_threshold" id="form_z_threshold" class="input-dark"></div>
                                <div><label>Z-Exit Threshold</label><input type="number" step="0.1" name="z_exit_threshold" id="form_z_exit_threshold" class="input-dark"></div>
                            </div>
                        </div>

                        <div class="section-card">
                            <h3 class="text-orange-400 font-bold uppercase text-xs mb-4 tracking-widest">3. API & Web3 Connectivity</h3>
                            <div class="space-y-4 text-xs">
                                <div><label>Binance API Key</label><input type="text" name="binance_api_key" id="form_binance_api_key" class="input-dark"></div>
                                <div><label>Binance Secret</label><input type="text" name="binance_api_secret" id="form_binance_api_secret" class="input-dark"></div>
                                <div><label>Wallet Address</label><input type="text" name="wallet_address" id="form_wallet_address" class="input-dark"></div>
                                <div><label>Telegram ID</label><input type="text" name="telegram_id" id="form_telegram_id" class="input-dark"></div>
                            </div>
                        </div>

                         <div class="section-card border-red-500/20">
                            <h3 class="text-red-400 font-bold uppercase text-xs mb-4 tracking-widest">4. Profit & Loss Rules ($)</h3>
                            <div class="grid grid-cols-2 gap-4 text-xs">
                                <div><label>Take Profit ($)</label><input type="number" step="0.01" name="tp_dollar" id="form_tp_dollar" class="input-dark"></div>
                                <div><label>Stop Loss ($)</label><input type="number" step="0.01" name="sl_dollar" id="form_sl_dollar" class="input-dark"></div>
                                <div><label>TP Z-Score</label><input type="number" step="0.1" name="tp_zscore" id="form_tp_zscore" class="input-dark"></div>
                                <div><label>SL Z-Score</label><input type="number" step="0.1" name="sl_zscore" id="form_sl_zscore" class="input-dark"></div>
                            </div>
                        </div>

                    </div>

                    <div class="mt-8 flex gap-4">
                        <button type="submit" class="flex-1 bg-emerald-600 hover:bg-emerald-500 text-white font-black py-4 rounded-2xl transition-all shadow-xl shadow-emerald-600/20 uppercase tracking-widest">Save Changes</button>
                        <button type="button" onclick="closeModal()" class="px-8 py-4 bg-slate-800 text-slate-400 font-bold rounded-2xl hover:bg-slate-700 uppercase text-xs transition-all">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openEditModal(user) {
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('display_username').innerText = user.username;
            
            // Auto-fill all fields
            for (let key in user) {
                let el = document.getElementById('form_' + key);
                if (el) el.value = user[key];
            }
        }

        function closeModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
    </script>
</body>
</html>