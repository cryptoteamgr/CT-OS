<?php
/**
 * CT-OS | copyright by cryptoteam.gr - manage-categories.php
 * ----------------------------------------------------------------
 * Σκοπός: Διαχείριση Universe και AI Suggestions 100% SQL-Based.
 * SECURITY: Admin Only Access & Zero External API Calls.
 */
session_start();
require_once 'db_config.php';
require_once 'functions.php';

// 1. ΕΛΕΓΧΟΣ ΑΣΦΑΛΕΙΑΣ (ADMIN ONLY)
$user_id = $_SESSION['user_id'] ?? 0;
$stmt_check = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt_check->execute([$user_id]);
$current_user = $stmt_check->fetch();

if (!$current_user || strcasecmp($current_user['role'], 'admin') !== 0) {
    header('HTTP/1.0 403 Forbidden');
    die("Access Denied: Admin privileges required.");
}

// 2. ΠΡΟΣΘΗΚΗ ΝΕΟΥ ΖΕΥΓΑΡΙΟΥ
if (isset($_POST['add_pair'])) {
    $asset_a = strtoupper(trim($_POST['asset_a']));
    $asset_b = strtoupper(trim($_POST['asset_b']));
    
    if (!empty($asset_a) && !empty($asset_b) && $asset_a !== $asset_b) {
        $check = $pdo->prepare("SELECT id FROM pair_universe WHERE (asset_a = ? AND asset_b = ?) OR (asset_a = ? AND asset_b = ?)");
        $check->execute([$asset_a, $asset_b, $asset_b, $asset_a]);
        
        if ($check->rowCount() == 0) {
            $ins = $pdo->prepare("INSERT INTO pair_universe (asset_a, asset_b, is_active) VALUES (?, ?, 1)");
            $ins->execute([$asset_a, $asset_b]);
            header("Location: manage-categories.php?success=1");
        } else {
            header("Location: manage-categories.php?error=exists");
        }
        exit;
    }
}

// 3. ΔΙΑΓΡΑΦΗ ΖΕΥΓΑΡΙΟΥ
if (isset($_GET['delete_id'])) {
    $del = $pdo->prepare("DELETE FROM pair_universe WHERE id = ?");
    $del->execute([$_GET['delete_id']]);
    header("Location: manage-categories.php?deleted=1");
    exit;
}

// 4. ΛΗΨΗ ΖΕΥΓΑΡΙΩΝ ΑΠΟ ΤΗ ΒΑΣΗ
$pairs = $pdo->query("SELECT * FROM pair_universe ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// 5. ΣΥΝΑΡΤΗΣΗ ΣΤΑΤΙΣΤΙΚΗΣ ΣΥΣΧΕΤΙΣΗΣ (Pearson)
function calculateCorrelation($x, $y) {
    $n = min(count($x), count($y)); 
    if ($n <= 1) return 0;
    
    $x = array_slice($x, 0, $n);
    $y = array_slice($y, 0, $n);

    $meanX = array_sum($x) / $n; $meanY = array_sum($y) / $n;
    $num = 0; $denX = 0; $denY = 0;
    for ($i = 0; $i < $n; $i++) {
        $num += ($x[$i] - $meanX) * ($y[$i] - $meanY);
        $denX += pow($x[$i] - $meanX, 2);
        $denY += pow($y[$i] - $meanY, 2);
    }
    return ($denX * $denY == 0) ? 0 : $num / sqrt($denX * $denY);
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>CT-OS | Category Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #020617; color: #f8fafc; }
        .card-pro { background: #0f172a; border: 1px solid #1e293b; border-radius: 1rem; }
        .tab-btn { padding: 10px 25px; font-weight: 800; font-size: 11px; transition: 0.3s; cursor: pointer; border-radius: 12px; text-transform: uppercase; letter-spacing: 1px; }
        .tab-btn.active { background: #3b82f6; color: white; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body class="p-6 md:p-12">

    <div class="max-w-6xl mx-auto">
        <header class="flex flex-col md:flex-row justify-between items-center mb-10 gap-6">
            <div>
                <h1 class="text-3xl font-black uppercase italic tracking-tighter text-white">
                    <span class="text-blue-500">Universe</span> Control
                </h1>
                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.3em] mt-1">Pair Management System v2.0</p>
            </div>
            <a href="global-trades.php" class="bg-slate-800 hover:bg-blue-600 px-6 py-3 rounded-xl text-[10px] font-black uppercase transition-all tracking-widest border border-slate-700">
                <i class="fas fa-th-large mr-2"></i> Terminal Grid
            </a>
        </header>

        <div class="flex gap-4 mb-8">
            <div onclick="openTab('universe')" id="btn-universe" class="tab-btn active border border-slate-700">SQL Database</div>
            <div onclick="openTab('ai')" id="btn-ai" class="tab-btn border border-slate-700 text-blue-400">AI Scanner (Local)</div>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div class="mb-6 p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 rounded-xl text-xs font-bold uppercase flex items-center gap-3">
                <i class="fas fa-check-circle"></i> Pair injected successfully into universe.
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['error'])): ?>
            <div class="mb-6 p-4 bg-red-500/10 border border-red-500/20 text-red-500 rounded-xl text-xs font-bold uppercase flex items-center gap-3">
                <i class="fas fa-exclamation-triangle"></i> Pair already exists in database.
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['deleted'])): ?>
            <div class="mb-6 p-4 bg-orange-500/10 border border-orange-500/20 text-orange-500 rounded-xl text-xs font-bold uppercase flex items-center gap-3">
                <i class="fas fa-trash-alt"></i> Pair removed from universe.
            </div>
        <?php endif; ?>

        <div id="universe" class="tab-content active">
            <div class="card-pro p-6 mb-8 border-b-4 border-blue-500 shadow-xl">
                <h2 class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-4 italic">Manual Pair Injection</h2>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input type="text" name="asset_a" placeholder="Asset A (e.g. BTC)" class="bg-slate-950 border border-slate-800 p-4 rounded-xl text-sm uppercase font-bold text-white focus:border-blue-500 outline-none transition-all" required>
                    <input type="text" name="asset_b" placeholder="Asset B (e.g. ETH)" class="bg-slate-950 border border-slate-800 p-4 rounded-xl text-sm uppercase font-bold text-white focus:border-blue-500 outline-none transition-all" required>
                    <button type="submit" name="add_pair" class="bg-blue-600 hover:bg-blue-500 p-4 rounded-xl font-black text-xs uppercase transition-all shadow-lg active:scale-95">
                        Inject New Pair
                    </button>
                </form>
            </div>

            <div class="card-pro overflow-hidden border border-white/5 shadow-2xl">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-800/40 text-[10px] font-black uppercase text-slate-500 tracking-widest border-b border-white/5">
                            <th class="p-6">UID</th>
                            <th class="p-6">Pair Configuration</th>
                            <th class="p-6">Status</th>
                            <th class="p-6 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php foreach ($pairs as $row): ?>
                        <tr class="hover:bg-white/[0.02] transition-all">
                            <td class="p-6 text-slate-600 font-mono text-xs">#<?= $row['id'] ?></td>
                            <td class="p-6">
                                <span class="text-sm font-black text-white italic uppercase tracking-tighter"><?= $row['asset_a'] ?> / <?= $row['asset_b'] ?></span>
                            </td>
                            <td class="p-6">
                                <span class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span class="text-[10px] font-bold uppercase text-slate-400">Tracking</span>
                                </span>
                            </td>
                            <td class="p-6 text-center">
                                <a href="?delete_id=<?= $row['id'] ?>" onclick="return confirm('🚨 Are you sure?')" class="text-slate-600 hover:text-red-500 transition-colors">
                                    <i class="fas fa-trash-alt text-lg"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="ai" class="tab-content">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php
                $threshold = 0.85; 
                $stmtHistory = $pdo->query("SELECT DISTINCT asset FROM asset_history");
                $available_assets = $stmtHistory->fetchAll(PDO::FETCH_COLUMN);

                $asset_data = [];
                foreach ($available_assets as $asset) {
                    $stmt = $pdo->prepare("SELECT price FROM asset_history WHERE asset = ? ORDER BY timestamp DESC LIMIT 150");
                    $stmt->execute([$asset]);
                    $prices = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    if (count($prices) >= 150) {
                        $asset_data[$asset] = array_reverse($prices);
                    }
                }

                $suggestions = [];
                $assets_keys = array_keys($asset_data);
                $count_keys = count($assets_keys);

                for ($i = 0; $i < $count_keys; $i++) {
                    for ($j = $i + 1; $j < $count_keys; $j++) {
                        $a = $assets_keys[$i];
                        $b = $assets_keys[$j];
                        $min_count = min(count($asset_data[$a]), count($asset_data[$b]));

                        $exists = false;
                        foreach ($pairs as $p) {
                            if (($p['asset_a'] == $a && $p['asset_b'] == $b) || ($p['asset_a'] == $b && $p['asset_b'] == $a)) {
                                $exists = true; break;
                            }
                        }
                        if ($exists) continue;

                        $correlation = calculateCorrelation($asset_data[$a], $asset_data[$b]);

                        if ($correlation >= $threshold) {
                            $ratios = [];
                            for ($k = 0; $k < $min_count; $k++) {
                                $ratios[] = $asset_data[$a][$k] / $asset_data[$b][$k];
                            }
                            
                            $mean = array_sum($ratios) / count($ratios);
                            $variance = 0;
                            foreach ($ratios as $r) $variance += pow($r - $mean, 2);
                            $stdDev = sqrt($variance / count($ratios));
                            $current_ratio = end($asset_data[$a]) / end($asset_data[$b]);
                            $z_score = ($stdDev == 0) ? 0 : ($current_ratio - $mean) / $stdDev;

                            $suggestions[] = [
                                'pair' => "$a / $b",
                                'corr' => round($correlation, 4),
                                'z_score' => round($z_score, 2),
                                'asset_a' => $a,
                                'asset_b' => $b
                            ];
                        }
                    }
                }

                if (empty($suggestions)): ?>
                    <div class="col-span-2 card-pro p-20 text-center border-dashed border-2 border-slate-800">
                        <i class="fas fa-microchip text-4xl text-slate-800 mb-4"></i>
                        <p class="text-slate-500 text-xs font-black uppercase">No new high-correlation orphans detected.</p>
                    </div>
                <?php else: 
                    usort($suggestions, fn($a, $b) => abs($b['z_score']) <=> abs($a['z_score']));
                    foreach ($suggestions as $sug): 
                        $z_class = abs($sug['z_score']) >= 2 ? 'text-orange-500' : 'text-emerald-500';
                    ?>
                        <div class="card-pro p-6 flex justify-between items-center border-l-4 border-blue-500 bg-white/[0.02]">
                            <div>
                                <h3 class="text-white font-black text-lg uppercase italic tracking-tighter"><?= $sug['pair'] ?></h3>
                                <p class="text-[10px] font-bold uppercase tracking-widest mt-1">
                                    <span class="text-slate-500">Correlation:</span> <span class="text-white"><?= $sug['corr'] ?></span>
                                    <span class="mx-2 text-slate-800">|</span>
                                    <span class="text-slate-500">Z-Score:</span> <span class="<?= $z_class ?>"><?= $sug['z_score'] ?></span>
                                </p>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="asset_a" value="<?= $sug['asset_a'] ?>">
                                <input type="hidden" name="asset_b" value="<?= $sug['asset_b'] ?>">
                                <button type="submit" name="add_pair" class="bg-blue-600 hover:bg-emerald-600 p-3 rounded-lg text-[10px] font-black uppercase transition-all shadow-lg active:scale-95">
                                    Inject
                                </button>
                            </form>
                        </div>
                    <?php endforeach; 
                endif; ?>
            </div>
        </div>
    </div>

    <script>
        function openTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            document.getElementById('btn-' + tabName).classList.add('active');
        }
    </script>
</body>
</html>