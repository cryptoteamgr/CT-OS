<?php
/**
 * CT-OS | copyright by cryptoteam.gr - performance_dashboard.php
 * ----------------------------------------------------------------
 * Σκοπός: Advanced Performance Analytics Dashboard
 * Metrics: Sharpe Ratio, Max Drawdown, Monthly P&L, Win Rate, Profit Factor
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

// Λήψη trade history
$stmt = $pdo->prepare("SELECT * FROM active_pairs WHERE user_id = ? AND status = 'CLOSED' AND mode = ? ORDER BY closed_at ASC");
$stmt->execute([$user_id, $current_mode]);
$trades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Υπολογισμός metrics
$metrics = calculatePerformanceMetrics($trades);

/**
 * Υπολογισμός Performance Metrics
 */
function calculatePerformanceMetrics($trades) {
    if (empty($trades)) {
        return [
            'total_trades' => 0,
            'net_profit' => 0,
            'win_rate' => 0,
            'sharpe_ratio' => 0,
            'max_drawdown' => 0,
            'profit_factor' => 0,
            'avg_trade_duration' => 0,
            'monthly_pnl' => [],
            'pnl_curve' => []
        ];
    }

    $total_trades = count($trades);
    $total_pnl = 0;
    $wins = 0;
    $losses = 0;
    $gross_wins = 0;
    $gross_losses = 0;
    $pnl_curve = [];
    $running_pnl = 0;
    $max_pnl = 0;
    $max_drawdown = 0;
    $monthly_pnl = [];
    $total_duration = 0;

    foreach ($trades as $trade) {
        $pnl = floatval($trade['final_pnl'] ?? 0);
        $total_pnl += $pnl;
        $running_pnl += $pnl;
        
        // P&L curve
        $pnl_curve[] = [
            'date' => $trade['closed_at'],
            'pnl' => $running_pnl
        ];

        // Win/Loss tracking
        if ($pnl > 0) {
            $wins++;
            $gross_wins += $pnl;
        } else {
            $losses++;
            $gross_losses += abs($pnl);
        }

        // Max drawdown calculation
        if ($running_pnl > $max_pnl) {
            $max_pnl = $running_pnl;
        }
        $drawdown = ($max_pnl - $running_pnl) / max($max_pnl, 1);
        if ($drawdown > $max_drawdown) {
            $max_drawdown = $drawdown;
        }

        // Monthly P&L
        $month = date('Y-m', strtotime($trade['closed_at']));
        if (!isset($monthly_pnl[$month])) {
            $monthly_pnl[$month] = 0;
        }
        $monthly_pnl[$month] += $pnl;

        // Trade duration
        if (!empty($trade['opened_at']) && !empty($trade['closed_at'])) {
            $duration = strtotime($trade['closed_at']) - strtotime($trade['opened_at']);
            $total_duration += $duration;
        }
    }

    // Win Rate
    $win_rate = $total_trades > 0 ? ($wins / $total_trades) * 100 : 0;

    // Profit Factor
    $profit_factor = $gross_losses > 0 ? $gross_wins / $gross_losses : ($gross_wins > 0 ? INF : 0);

    // Sharpe Ratio (simplified)
    if (count($pnl_curve) > 1) {
        $returns = [];
        for ($i = 1; $i < count($pnl_curve); $i++) {
            $returns[] = $pnl_curve[$i]['pnl'] - $pnl_curve[$i-1]['pnl'];
        }
        if (count($returns) > 0) {
            $mean = array_sum($returns) / count($returns);
            $std_dev = sqrt(array_sum(array_map(function($r) use ($mean) {
                return pow($r - $mean, 2);
            }, $returns)) / count($returns));
            $sharpe_ratio = $std_dev > 0 ? ($mean / $std_dev) * sqrt(252) : 0; // Annualized
        } else {
            $sharpe_ratio = 0;
        }
    } else {
        $sharpe_ratio = 0;
    }

    // Average trade duration (in hours)
    $avg_duration = $total_trades > 0 ? ($total_duration / $total_trades) / 3600 : 0;

    return [
        'total_trades' => $total_trades,
        'net_profit' => $total_pnl,
        'win_rate' => $win_rate,
        'sharpe_ratio' => $sharpe_ratio,
        'max_drawdown' => $max_drawdown * 100, // Convert to percentage
        'profit_factor' => is_infinite($profit_factor) ? ($gross_wins > 0 ? 999 : 0) : $profit_factor,
        'avg_trade_duration' => $avg_duration,
        'monthly_pnl' => $monthly_pnl,
        'pnl_curve' => $pnl_curve
    ];
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT-OS | Performance Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .metric-card {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
        }
    </style>
</head>
<body class="min-h-screen p-4 md:p-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-black italic tracking-tighter text-blue-600 mb-2">PERFORMANCE DASHBOARD</h1>
            <p class="text-sm text-slate-400 uppercase tracking-widest">Advanced Analytics (<?= $current_mode ?>)</p>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="metric-card">
                <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Net Profit</p>
                <p class="text-3xl font-black <?= $metrics['net_profit'] >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                    $<?= number_format($metrics['net_profit'], 2) ?>
                </p>
            </div>
            
            <div class="metric-card">
                <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Win Rate</p>
                <p class="text-3xl font-black text-blue-400">
                    <?= number_format($metrics['win_rate'], 1) ?>%
                </p>
            </div>
            
            <div class="metric-card">
                <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Sharpe Ratio</p>
                <p class="text-3xl font-black text-purple-400">
                    <?= number_format($metrics['sharpe_ratio'], 2) ?>
                </p>
            </div>
            
            <div class="metric-card">
                <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Max Drawdown</p>
                <p class="text-3xl font-black text-red-400">
                    <?= number_format($metrics['max_drawdown'], 1) ?>%
                </p>
            </div>
        </div>

        <!-- Secondary Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="metric-card">
                <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Total Trades</p>
                <p class="text-2xl font-black text-white">
                    <?= $metrics['total_trades'] ?>
                </p>
            </div>
            
            <div class="metric-card">
                <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Profit Factor</p>
                <p class="text-2xl font-black text-yellow-400">
                    <?= $metrics['profit_factor'] >= 999 ? '∞' : number_format($metrics['profit_factor'], 2) ?>
                </p>
            </div>
            
            <div class="metric-card">
                <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Avg Duration</p>
                <p class="text-2xl font-black text-slate-300">
                    <?= number_format($metrics['avg_trade_duration'], 1) ?>h
                </p>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">
            <!-- P&L Curve -->
            <div class="glass-panel rounded-lg p-3" style="height: 180px;">
                <h3 class="text-xs font-bold text-white mb-2">P&L Curve</h3>
                <div style="height: 140px;">
                    <canvas id="pnlChart"></canvas>
                </div>
            </div>

            <!-- Monthly P&L -->
            <div class="glass-panel rounded-lg p-3" style="height: 180px;">
                <h3 class="text-xs font-bold text-white mb-2">Monthly P&L</h3>
                <div style="height: 140px;">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Win/Loss Distribution -->
        <div class="glass-panel rounded-lg p-3 mb-4" style="height: 150px;">
            <h3 class="text-xs font-bold text-white mb-2">Win/Loss Distribution</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3" style="height: 110px;">
                <div style="height: 100px;">
                    <canvas id="winLossChart"></canvas>
                </div>
                <div class="flex items-center justify-center">
                    <div class="text-center">
                        <p class="text-3xl font-black <?= $metrics['win_rate'] >= 50 ? 'text-green-400' : 'text-red-400' ?>">
                            <?= number_format($metrics['win_rate'], 1) ?>%
                        </p>
                        <p class="text-xs text-slate-400 uppercase tracking-widest mt-1">Win Rate</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Tips -->
        <div class="glass-panel rounded-lg p-3 mt-6">
            <h3 class="text-xs font-bold text-white mb-2">Performance Analysis</h3>
            <div class="space-y-1">
                <?php if ($metrics['sharpe_ratio'] > 1): ?>
                    <p class="text-xs text-green-400">✓ Excellent risk-adjusted returns (Sharpe > 1)</p>
                <?php elseif ($metrics['sharpe_ratio'] > 0.5): ?>
                    <p class="text-xs text-yellow-400">! Moderate risk-adjusted returns (Sharpe > 0.5)</p>
                <?php else: ?>
                    <p class="text-xs text-red-400">✗ Low risk-adjusted returns (Sharpe < 0.5)</p>
                <?php endif; ?>
                
                <?php if ($metrics['max_drawdown'] < 10): ?>
                    <p class="text-xs text-green-400">✓ Low drawdown (<?php echo number_format($metrics['max_drawdown'], 1) ?>%)</p>
                <?php elseif ($metrics['max_drawdown'] < 20): ?>
                    <p class="text-xs text-yellow-400">! Moderate drawdown (<?php echo number_format($metrics['max_drawdown'], 1) ?>%)</p>
                <?php else: ?>
                    <p class="text-xs text-red-400">✗ High drawdown (<?php echo number_format($metrics['max_drawdown'], 1) ?>%)</p>
                <?php endif; ?>
                
                <?php if ($metrics['profit_factor'] > 2): ?>
                    <p class="text-xs text-green-400">✓ Excellent profit factor (<?php echo number_format($metrics['profit_factor'], 2) ?>)</p>
                <?php elseif ($metrics['profit_factor'] > 1): ?>
                    <p class="text-xs text-yellow-400">! Positive profit factor (<?php echo number_format($metrics['profit_factor'], 2) ?>)</p>
                <?php else: ?>
                    <p class="text-xs text-red-400">✗ Negative profit factor (<?php echo number_format($metrics['profit_factor'], 2) ?>)</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // P&L Curve Chart - Simplified with last 10 points
        const pnlCtx = document.getElementById('pnlChart').getContext('2d');
        const pnlData = <?php echo json_encode($metrics['pnl_curve']); ?>;
        const simplifiedPnlData = pnlData.slice(-10); // Only last 10 points
        
        new Chart(pnlCtx, {
            type: 'line',
            data: {
                labels: simplifiedPnlData.map(d => d.date.substring(5)), // Only MM-DD
                datasets: [{
                    label: 'P&L',
                    data: simplifiedPnlData.map(d => d.pnl),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3
                }]
            },
            options: {
                responsive: false,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        display: false // Hide x-axis labels
                    },
                    y: {
                        display: false // Hide y-axis labels
                    }
                }
            }
        });

        // Monthly P&L Chart - Last 6 months only
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyData = <?php echo json_encode(array_values($metrics['monthly_pnl'])); ?>;
        const monthlyLabels = <?php echo json_encode(array_keys($metrics['monthly_pnl'])); ?>;
        const last6Months = monthlyData.slice(-6);
        const last6Labels = monthlyLabels.slice(-6);
        
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: last6Labels.map(l => l.substring(5)), // Only MM-DD
                datasets: [{
                    label: 'P&L',
                    data: last6Months,
                    backgroundColor: last6Months.map(d => d >= 0 ? '#22c55e' : '#ef4444')
                }]
            },
            options: {
                responsive: false,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        display: false // Hide x-axis labels
                    },
                    y: {
                        display: false // Hide y-axis labels
                    }
                }
            }
        });

        // Win/Loss Chart - Simplified
        const winLossCtx = document.getElementById('winLossChart').getContext('2d');
        const winCount = <?php echo $metrics['total_trades'] * ($metrics['win_rate'] / 100); ?>;
        const lossCount = <?php echo $metrics['total_trades'] - ($metrics['total_trades'] * ($metrics['win_rate'] / 100)); ?>;
        
        new Chart(winLossCtx, {
            type: 'doughnut',
            data: {
                labels: ['Wins', 'Losses'],
                datasets: [{
                    data: [winCount, lossCount],
                    backgroundColor: ['#22c55e', '#ef4444']
                }]
            },
            options: {
                responsive: false,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false } // Hide legend
                }
            }
        });
    </script>
</body>
</html>
