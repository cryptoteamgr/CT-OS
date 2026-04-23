<?php
/**
 * CT-OS | Professional Admin Live Monitor v3.1
 * ----------------------------------------------------------------
 * Ξεχωριστά στατιστικά για DEMO και LIVE
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db_config.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized Access");
}

// === 1. Φέρνουμε όλους τους χρήστες με τα σωστά API keys ανά mode ===
$sql = "SELECT
            u.id,
            u.username,
            u.last_balance,
            u.last_equity,
            u.bot_mode,
            a.account_type as active_api,
            (SELECT COUNT(*) FROM active_pairs 
             WHERE user_id = u.id AND status = 'OPEN') as open_trades,
            u.capital_per_trade,
            u.leverage,
            u.tp_dollar,
            u.sl_dollar,
            u.max_open_trades,
            u.z_threshold,
            u.z_exit_threshold,
            u.sl_zscore
        FROM users u
        LEFT JOIN api_keys a 
            ON u.id = a.user_id 
           AND a.account_type = u.bot_mode 
           AND a.is_active = 1
        WHERE u.bot_mode IS NOT NULL
        ORDER BY u.bot_mode DESC, u.last_equity DESC";

try {
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("SQL Error: " . $e->getMessage());
}

// === 2. Διαχωρισμός DEMO & LIVE ===
$demoUsers = [];
$liveUsers = [];

foreach ($users as $u) {
    if ($u['bot_mode'] === 'LIVE') {
        $liveUsers[] = $u;
    } else {
        $demoUsers[] = $u;
    }
}

// === 3. Get User Pairs ===
function getUserPairs($pdo, $userId) {
    $sql = "SELECT 
                ap.asset_a,
                ap.asset_b,
                ap.side_a,
                ap.side_b,
                ap.beta_used,
                ap.entry_ratio,
                ap.current_pnl,
                ap.entry_z_score,
                ap.mode,
                ap.opened_at,
                ap.commission_a + ap.commission_b as total_fees
            FROM active_pairs ap
            WHERE ap.user_id = ? AND ap.status = 'OPEN'
            ORDER BY ap.opened_at DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// === 4. Calculate separate statistics ===
function calculateStats($userList) {
    $totalEquity = 0;
    $totalOpenTrades = 0;
    foreach ($userList as $u) {
        $totalEquity += (float)$u['last_equity'];
        $totalOpenTrades += (int)$u['open_trades'];
    }
    return [
        'equity' => $totalEquity,
        'open_trades' => $totalOpenTrades,
        'traders' => count($userList)
    ];
}

$demoStats = calculateStats($demoUsers);
$liveStats = calculateStats($liveUsers);

?>

<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>CT-OS Admin | Real-Time Monitor</title>
    <style>
        :root {
            --bg: #020617;
            --card: #0f172a;
            --border: #1e293b;
            --text: #f8fafc;
            --subtext: #94a3b8;
            --accent: #38bdf8;
            --pos: #4ade80;
            --neg: #f87171;
            --warn: #fbbf24;
        }
        body { 
            background: var(--bg); 
            color: var(--text); 
            font-family: 'Inter', sans-serif; 
            margin: 0; 
            padding: 20px; 
        }
        .container { max-width: 1400px; margin: 0 auto; }

        .section-title {
            font-size: 1.4rem;
            margin: 30px 0 15px 0;
            color: #e0f2fe;
            border-bottom: 2px solid #334155;
            padding-bottom: 8px;
        }

        /* Stats Cards */
        .stats-grid {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .stat-card {
            background: var(--card);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border);
            flex: 1;
            min-width: 280px;
        }
        .stat-card.live { border-top: 4px solid #f87171; }
        .stat-card.demo { border-top: 4px solid #4ade80; }

        .stat-card small {
            color: var(--subtext);
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 1px;
        }
        .stat-card .value {
            font-size: 1.9rem;
            font-weight: 800;
            margin-top: 6px;
            font-family: monospace;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
            margin-bottom: 40px;
        }
        th {
            background: #1e293b;
            color: var(--accent);
            padding: 14px 15px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }
        td {
            padding: 14px 15px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        tr:hover { background: #1e293b88; }

        .user-cell { display: flex; align-items: center; gap: 12px; }
        .avatar {
            width: 36px; height: 36px;
            background: #38bdf8;
            color: #020617;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 15px;
        }
        .mode-badge {
            font-size: 9px;
            padding: 2px 7px;
            border-radius: 4px;
            font-weight: bold;
        }
        .mode-live { background: #f87171; color: #450a0a; }
        .mode-demo { background: #4ade80; color: #052e16; }

        .pnl-box {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid var(--border);
            display: inline-block;
            min-width: 130px;
            text-align: center;
        }
        .active-tag {
            background: #0c4a6e;
            color: #7dd3fc;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container">

    <h1 style="margin:0 0 10px 0; color:#67e8f9;">🚀 CT-OS Master Live Monitor</h1>
    <div style="color:#64748b; font-size:13px; margin-bottom:25px;">
        Server Time: <?= date("Y-m-d H:i:s") ?> | Refresh every 60 seconds
    </div>

    <!-- ==================== LIVE SECTION ==================== -->
    <h2 class="section-title" style="color:#f87171;">🔴 LIVE ACCOUNTS</h2>
    
    <div class="stats-grid">
        <div class="stat-card live">
            <small>Total Live Equity</small>
            <div class="value">$<?= number_format($liveStats['equity'], 2) ?></div>
        </div>
        <div class="stat-card live">
            <small>Open Positions (Live)</small>
            <div class="value" style="color:#f87171;"><?= $liveStats['open_trades'] ?></div>
        </div>
        <div class="stat-card live">
            <small>Active Live Traders</small>
            <div class="value"><?= $liveStats['traders'] ?></div>
        </div>
    </div>

    <!-- Live Table -->
    <table>
        <thead>
            <tr>
                <th>Trader & Mode</th>
                <th>Portfolio (Equity)</th>
                <th>Real-Time PnL</th>
                <th>Active Positions</th>
                <th>Strategy Config</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($liveUsers as $u): 
                $bal = (float)$u['last_balance'];
                $eq = (float)$u['last_equity'];
                $pnl = $eq - $bal;
                $pnl_pc = ($bal > 0) ? ($pnl / $bal) * 100 : 0;
                $userPairs = getUserPairs($pdo, $u['id']);
            ?>
            <tr>
                <td>
                    <div class="user-cell">
                        <div class="avatar"><?= strtoupper(substr($u['username'], 0, 1)) ?></div>
                        <div>
                            <div style="font-weight: bold;">
                                <?= htmlspecialchars($u['username']) ?>
                                <span class="mode-badge mode-live">LIVE</span>
                            </div>
                            <div style="font-size: 10px; color:#64748b;">
                                ID: <?= $u['id'] ?> | API: <?= $u['active_api'] ?: '<span style="color:#f87171">MISSING</span>' ?>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="money-main">$<?= number_format($eq, 2) ?></div>
                    <div class="money-sub">Wallet: $<?= number_format($bal, 2) ?></div>
                </td>
                <td>
                    <div class="pnl-box">
                        <div style="font-weight: bold; color: <?= $pnl >= 0 ? '#4ade80' : '#f87171' ?>">
                            <?= $pnl >= 0 ? '+' : '' ?>$<?= number_format($pnl, 2) ?>
                        </div>
                        <div style="font-size: 10px; color:#94a3b8;">
                            <?= number_format($pnl_pc, 2) ?>%
                        </div>
                    </div>
                </td>
                <td>
                    <?php if($u['open_trades'] > 0): ?>
                        <span class="active-tag">🔥 <?= $u['open_trades'] ?> POSITIONS</span>
                        <!-- Pairs Details -->
                        <div style="margin-top: 8px; font-size: 9px; color: #64748b;">
                            <?php foreach($userPairs as $pair): ?>
                                <div style="background: #1e293b; padding: 4px 6px; border-radius: 4px; margin-bottom: 4px;">
                                    <strong><?= htmlspecialchars($pair['asset_a']) ?>/<?= htmlspecialchars($pair['asset_b']) ?></strong> (<?= htmlspecialchars($pair['mode']) ?>)<br>
                                    Beta: <?= number_format($pair['beta_used'], 4) ?> | 
                                    Entry Z: <?= number_format($pair['entry_z_score'], 2) ?> | 
                                    PnL: <span style="color: <?= $pair['current_pnl'] >= 0 ? '#4ade80' : '#f87171' ?>"><?= $pair['current_pnl'] >= 0 ? '+' : '' ?>$<?= number_format($pair['current_pnl'], 2) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <span style="color:#64748b; font-size:11px;">No open positions</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:11px; line-height:1.5;">
                    Capital: <span style="color:#fbbf24; font-weight:bold;">$<?= $u['capital_per_trade'] ?></span><br>
                    Max: <span style="color:#38bdf8;"><?= $u['max_open_trades'] ?></span> | 
                    Leverage: <span style="color:#fbbf24;"><?= $u['leverage'] ?>x</span><br>
                    Entry Z: <span style="color:#fbbf24;"><?= $u['z_threshold'] ?></span><br>
                    TP: <span style="color:#4ade80;">+$<?= $u['tp_dollar'] ?></span> | 
                    TP Z: <span style="color:#4ade80;"><?= $u['z_exit_threshold'] ?></span><br>
                    SL: <span style="color:#f87171;">-$<?= $u['sl_dollar'] ?></span> | 
                    SL Z: <span style="color:#f87171;"><?= $u['sl_zscore'] ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- ==================== DEMO SECTION ==================== -->
    <h2 class="section-title" style="color:#4ade80;">🟢 DEMO ACCOUNTS</h2>
    
    <div class="stats-grid">
        <div class="stat-card demo">
            <small>Total Demo Equity</small>
            <div class="value">$<?= number_format($demoStats['equity'], 2) ?></div>
        </div>
        <div class="stat-card demo">
            <small>Open Positions (Demo)</small>
            <div class="value" style="color:#4ade80;"><?= $demoStats['open_trades'] ?></div>
        </div>
        <div class="stat-card demo">
            <small>Active Demo Traders</small>
            <div class="value"><?= $demoStats['traders'] ?></div>
        </div>
    </div>

    <!-- Demo Table -->
    <table>
        <thead>
            <tr>
                <th>Trader & Mode</th>
                <th>Portfolio (Equity)</th>
                <th>Real-Time PnL</th>
                <th>Active Positions</th>
                <th>Strategy Config</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($demoUsers as $u): 
                $bal = (float)$u['last_balance'];
                $eq = (float)$u['last_equity'];
                $pnl = $eq - $bal;
                $pnl_pc = ($bal > 0) ? ($pnl / $bal) * 100 : 0;
                $userPairs = getUserPairs($pdo, $u['id']);
            ?>
            <tr>
                <td>
                    <div class="user-cell">
                        <div class="avatar"><?= strtoupper(substr($u['username'], 0, 1)) ?></div>
                        <div>
                            <div style="font-weight: bold;">
                                <?= htmlspecialchars($u['username']) ?>
                                <span class="mode-badge mode-demo">DEMO</span>
                            </div>
                            <div style="font-size: 10px; color:#64748b;">
                                ID: <?= $u['id'] ?> | API: <?= $u['active_api'] ?: '<span style="color:#f87171">MISSING</span>' ?>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="money-main">$<?= number_format($eq, 2) ?></div>
                    <div class="money-sub">Wallet: $<?= number_format($bal, 2) ?></div>
                </td>
                <td>
                    <div class="pnl-box">
                        <div style="font-weight: bold; color: <?= $pnl >= 0 ? '#4ade80' : '#f87171' ?>">
                            <?= $pnl >= 0 ? '+' : '' ?>$<?= number_format($pnl, 2) ?>
                        </div>
                        <div style="font-size: 10px; color:#94a3b8;">
                            <?= number_format($pnl_pc, 2) ?>%
                        </div>
                    </div>
                </td>
                <td>
                    <?php if($u['open_trades'] > 0): ?>
                        <span class="active-tag">🔥 <?= $u['open_trades'] ?> POSITIONS</span>
                        <!-- Pairs Details -->
                        <div style="margin-top: 8px; font-size: 9px; color: #64748b;">
                            <?php foreach($userPairs as $pair): ?>
                                <div style="background: #1e293b; padding: 4px 6px; border-radius: 4px; margin-bottom: 4px;">
                                    <strong><?= htmlspecialchars($pair['asset_a']) ?>/<?= htmlspecialchars($pair['asset_b']) ?></strong> (<?= htmlspecialchars($pair['mode']) ?>)<br>
                                    Beta: <?= number_format($pair['beta_used'], 4) ?> | 
                                    Entry Z: <?= number_format($pair['entry_z_score'], 2) ?> | 
                                    PnL: <span style="color: <?= $pair['current_pnl'] >= 0 ? '#4ade80' : '#f87171' ?>"><?= $pair['current_pnl'] >= 0 ? '+' : '' ?>$<?= number_format($pair['current_pnl'], 2) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <span style="color:#64748b; font-size:11px;">No open positions</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:11px; line-height:1.5;">
                    Capital: <span style="color:#fbbf24; font-weight:bold;">$<?= $u['capital_per_trade'] ?></span><br>
                    Max: <span style="color:#38bdf8;"><?= $u['max_open_trades'] ?></span> | 
                    Leverage: <span style="color:#fbbf24;"><?= $u['leverage'] ?>x</span><br>
                    Entry Z: <span style="color:#fbbf24;"><?= $u['z_threshold'] ?></span><br>
                    TP: <span style="color:#4ade80;">+$<?= $u['tp_dollar'] ?></span> | 
                    TP Z: <span style="color:#4ade80;"><?= $u['z_exit_threshold'] ?></span><br>
                    SL: <span style="color:#f87171;">-$<?= $u['sl_dollar'] ?></span> | 
                    SL Z: <span style="color:#f87171;"><?= $u['sl_zscore'] ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>
</body>
</html>
