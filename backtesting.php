<?php
/**
 * CT-OS | copyright by cryptoteam.gr - backtesting.php
 * ----------------------------------------------------------------
 * Σκοπός: Backtesting Engine για Pair Trading Strategy
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

// Λήψη assets με historical data
$stmtAssets = $pdo->query("SELECT DISTINCT asset FROM asset_history ORDER BY asset");
$assets = $stmtAssets->fetchAll(PDO::FETCH_COLUMN);
sort($assets); // Αλφαβητική ταξινόμηση

if (empty($assets)) {
    $no_data_error = true;
}

// Backtesting result
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pair_a = $_POST['asset_a'];
    $pair_b = $_POST['asset_b'];
    $capital = floatval($_POST['capital']);
    $max_trades = intval($_POST['max_trades']);
    $leverage = intval($_POST['leverage']);
    $entry_z_score = floatval($_POST['entry_z_score']);
    $tp_dollar = floatval($_POST['tp_dollar']);
    $tp_zscore = floatval($_POST['tp_zscore']);
    $sl_dollar = floatval($_POST['sl_dollar']);
    $sl_zscore = floatval($_POST['sl_zscore']);
    $min_profit = floatval($_POST['min_profit']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    
    // Run backtesting
    $result = runBacktesting($pdo, $pair_a, $pair_b, $entry_z_score, $tp_dollar, $tp_zscore, $sl_dollar, $sl_zscore, $min_profit, $start_date, $end_date, $capital, $leverage, $max_trades);
}

/**
 * Run Backtesting Simulation
 */
function runBacktesting($pdo, $pair_a, $pair_b, $entry_z_score, $tp_dollar, $tp_zscore, $sl_dollar, $sl_zscore, $min_profit, $start_date, $end_date, $capital, $leverage, $max_trades) {
    // Get historical data
    $sql = "
        SELECT h1.timestamp, h1.price as price_a, h2.price as price_b
        FROM asset_history h1
        JOIN asset_history h2 ON h1.timestamp = h2.timestamp
        WHERE h1.asset = ? AND h2.asset = ?
        AND h1.timestamp >= ? AND h1.timestamp <= ?
        ORDER BY h1.timestamp ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pair_a, $pair_b, $start_date, $end_date]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Check if data exists
    if (count($history) < 10) {
        return [
            'error' => 'Insufficient historical data. Found: ' . count($history) . ' records.',
            'debug' => [
                'pair_a' => $pair_a,
                'pair_b' => $pair_b,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'records_found' => count($history)
            ]
        ];
    }
    
    // Calculate rolling statistics
    $window_size = 100;
    $dataA = array_map('floatval', array_column($history, 'price_a'));
    $dataB = array_map('floatval', array_column($history, 'price_b'));
    $timestamps = array_column($history, 'timestamp');
    
    // Normalize prices (divide by first price to start at 1.0)
    $normalizedA = array_map(function($p) use ($dataA) { return $p / $dataA[0]; }, $dataA);
    $normalizedB = array_map(function($p) use ($dataB) { return $p / $dataB[0]; }, $dataB);
    
    // Calculate rolling Z-score and correlation
    $z_scores = [];
    $correlations = [];
    
    for ($i = $window_size; $i < count($dataA); $i++) {
        $windowA = array_slice($normalizedA, $i - $window_size, $window_size);
        $windowB = array_slice($normalizedB, $i - $window_size, $window_size);
        
        // Calculate beta and correlation
        $reg = calculateLinearRegression($windowA, $windowB);
        $beta = $reg['beta'];
        $correlation = calculateCorrelation($windowA, $windowB);
        
        // Calculate spread and Z-score
        $spread = [];
        for ($j = 0; $j < count($windowA); $j++) {
            $spread[] = $windowB[$j] - ($beta * $windowA[$j]);
        }
        
        $mean_spread = array_sum($spread) / count($spread);
        $std_spread = sqrt(array_sum(array_map(function($s) use ($mean_spread) {
            return pow($s - $mean_spread, 2);
        }, $spread)) / count($spread));
        
        $current_spread = $normalizedB[$i] - ($beta * $normalizedA[$i]);
        
        // Fix: Clamp Z-score to reasonable range to prevent calculation errors
        $z_score = ($std_spread > 0) ? ($current_spread - $mean_spread) / $std_spread : 0;
        $z_score = max(-5, min(5, $z_score)); // Clamp between -5 and 5
        
        $z_scores[] = $z_score;
        $correlations[] = $correlation;
    }
    
    // Simulate trading
    $trades = [];
    $current_capital = $capital;
    $position = null;
    $entry_z = null;
    $entry_spread = null;
    
    // Debug: Track max Z-score and correlation
    $max_z_score = 0;
    $min_z_score = 0;
    $max_correlation = 0;
    $z_score_threshold_hits = 0;
    $correlation_threshold_hits = 0;
    
    for ($i = 0; $i < count($z_scores); $i++) {
        $timestamp = $timestamps[$i + $window_size];
        $z = $z_scores[$i];
        $corr = $correlations[$i];
        $price_a = $dataA[$i + $window_size];
        $price_b = $dataB[$i + $window_size];
        
        // Debug tracking
        if (abs($z) > $max_z_score) $max_z_score = abs($z);
        if (abs($z) < $min_z_score || $min_z_score == 0) $min_z_score = abs($z);
        if ($corr > $max_correlation) $max_correlation = $corr;
        if (abs($z) >= $entry_z_score) $z_score_threshold_hits++;
        
        // Entry conditions (bot panel style - no correlation threshold)
        if ($position === null && abs($z) >= $entry_z_score && count($trades) < $max_trades) {
            $position = [
                'entry_time' => $timestamp,
                'entry_price_a' => $price_a,
                'entry_price_b' => $price_b,
                'entry_z' => $z,
                'direction' => ($z > 0) ? 'SHORT_A_LONG_B' : 'LONG_A_SHORT_B'
            ];
            $entry_z = $z;
            continue;
        }
        
        // Exit conditions
        if ($position !== null) {
            // Calculate P&L with proper position sizing
            $pnl_a = ($position['direction'] === 'LONG_A_SHORT_B') 
                ? ($price_a - $position['entry_price_a']) 
                : ($position['entry_price_a'] - $price_a);
            
            $pnl_b = ($position['direction'] === 'LONG_A_SHORT_B') 
                ? ($position['entry_price_b'] - $price_b) 
                : ($price_b - $position['entry_price_b']);
            
            // Position size calculation (half capital per leg)
            $position_size_per_leg = ($capital * $leverage) / 2;
            $pnl_a_dollar = $pnl_a * $position_size_per_leg / $position['entry_price_a'];
            $pnl_b_dollar = $pnl_b * $position_size_per_leg / $position['entry_price_b'];
            $total_pnl = $pnl_a_dollar + $pnl_b_dollar;
            $final_pnl = $total_pnl;
            
            // Exit conditions (bot panel style)
            $is_tp_dollar = ($tp_dollar > 0 && $total_pnl >= $tp_dollar);
            $is_tp_zscore = ($tp_zscore > 0 && abs($z) <= $tp_zscore && $total_pnl >= $min_profit);
            $is_sl_dollar = ($sl_dollar > 0 && $total_pnl <= -$sl_dollar);
            $is_sl_zscore = ($sl_zscore > 0 && abs($z) >= $sl_zscore);
            $is_z_convergence = (abs($z) <= 0.5 && $total_pnl >= $min_profit);
            
            // Determine exit reason
            $reason = '';
            if ($is_tp_dollar) $reason = 'TP ($)';
            elseif ($is_tp_zscore) $reason = 'TP (Z-Score)';
            elseif ($is_sl_dollar) $reason = 'SL ($)';
            elseif ($is_sl_zscore) $reason = 'SL (Z-Score)';
            elseif ($is_z_convergence) $reason = 'Z-Score Convergence';
            
            if ($reason !== '') {
                $trades[] = [
                    'entry_time' => $position['entry_time'],
                    'exit_time' => $timestamp,
                    'entry_z' => $position['entry_z'],
                    'exit_z' => $z,
                    'pnl' => $total_pnl,
                    'reason' => $reason,
                    'direction' => $position['direction']
                ];
                $current_capital += $total_pnl;
                $position = null;
            }
        }
    }
    
    // Calculate metrics
    $metrics = calculateBacktestingMetrics($trades, $capital, $current_capital);
    
    return [
        'metrics' => $metrics,
        'trades' => $trades,
        'pair' => "$pair_a/$pair_b",
        'parameters' => [
            'entry_z_score' => $entry_z_score,
            'tp_dollar' => $tp_dollar,
            'tp_zscore' => $tp_zscore,
            'sl_dollar' => $sl_dollar,
            'sl_zscore' => $sl_zscore,
            'min_profit' => $min_profit,
            'leverage' => $leverage
        ],
        'debug' => [
            'total_data_points' => count($history),
            'z_scores_calculated' => count($z_scores),
            'trades_found' => count($trades),
            'max_z_score' => $max_z_score,
            'min_z_score' => $min_z_score,
            'max_correlation' => $max_correlation,
            'z_threshold_hits' => $z_score_threshold_hits,
            'correlation_threshold_hits' => $correlation_threshold_hits
        ]
    ];
}

/**
 * Calculate Backtesting Metrics
 */
function calculateBacktestingMetrics($trades, $initial_capital, $final_capital) {
    if (empty($trades)) {
        return [
            'total_trades' => 0,
            'net_profit' => 0,
            'win_rate' => 0,
            'sharpe_ratio' => 0,
            'max_drawdown' => 0,
            'profit_factor' => 0,
            'avg_trade_duration' => 0,
            'total_return' => 0
        ];
    }
    
    $total_trades = count($trades);
    $wins = 0;
    $losses = 0;
    $gross_wins = 0;
    $gross_losses = 0;
    $pnl_curve = [];
    $running_pnl = 0;
    $max_pnl = 0;
    $max_drawdown = 0;
    $total_duration = 0;
    
    foreach ($trades as $trade) {
        $pnl = floatval($trade['pnl']);
        $running_pnl += $pnl;
        $pnl_curve[] = $pnl;
        
        if ($pnl > 0) {
            $wins++;
            $gross_wins += $pnl;
        } else {
            $losses++;
            $gross_losses += abs($pnl);
        }
        
        if ($running_pnl > $max_pnl) {
            $max_pnl = $running_pnl;
        }
        if ($max_pnl > 0) {
            $drawdown = ($max_pnl - $running_pnl) / $max_pnl;
            if ($drawdown > $max_drawdown) {
                $max_drawdown = $drawdown;
            }
        }
        
        $duration = strtotime($trade['exit_time']) - strtotime($trade['entry_time']);
        $total_duration += $duration;
    }
    
    $win_rate = ($total_trades > 0) ? ($wins / $total_trades) * 100 : 0;
    $profit_factor = ($gross_losses > 0) ? $gross_wins / $gross_losses : ($gross_wins > 0 ? 999 : 0);
    $net_profit = $final_capital - $initial_capital;
    $total_return = ($net_profit / $initial_capital) * 100;
    
    // Sharpe Ratio
    if (count($pnl_curve) > 1) {
        $mean = array_sum($pnl_curve) / count($pnl_curve);
        $std_dev = sqrt(array_sum(array_map(function($p) use ($mean) {
            return pow($p - $mean, 2);
        }, $pnl_curve)) / count($pnl_curve));
        $sharpe_ratio = ($std_dev > 0) ? ($mean / $std_dev) * sqrt(252) : 0;
    } else {
        $sharpe_ratio = 0;
    }
    
    $avg_duration = ($total_trades > 0) ? ($total_duration / $total_trades) / 3600 : 0;
    
    return [
        'total_trades' => $total_trades,
        'net_profit' => $net_profit,
        'win_rate' => $win_rate,
        'sharpe_ratio' => $sharpe_ratio,
        'max_drawdown' => $max_drawdown * 100,
        'profit_factor' => is_infinite($profit_factor) ? ($gross_wins > 0 ? 999 : 0) : $profit_factor,
        'avg_trade_duration' => $avg_duration,
        'total_return' => $total_return,
        'initial_capital' => $initial_capital,
        'final_capital' => $final_capital
    ];
}

/**
 * Calculate Linear Regression
 */
function calculateLinearRegression($x, $y) {
    $n = count($x);
    if ($n == 0) return ['beta' => 0, 'alpha' => 0];
    $meanX = array_sum($x) / $n;
    $meanY = array_sum($y) / $n;
    $num = 0; $den = 0;
    for ($i = 0; $i < $n; $i++) {
        $num += ($x[$i] - $meanX) * ($y[$i] - $meanY);
        $den += pow($x[$i] - $meanX, 2);
    }
    $beta = ($den == 0) ? 0 : $num / $den;
    $alpha = $meanY - ($beta * $meanX);
    return ['beta' => $beta, 'alpha' => $alpha];
}

/**
 * Calculate Correlation
 */
function calculateCorrelation($x, $y) {
    $n = count($x);
    if ($n == 0) return 0;
    $meanX = array_sum($x) / $n;
    $meanY = array_sum($y) / $n;
    $num = 0; $denX = 0; $denY = 0;
    for ($i = 0; $i < $n; $i++) {
        $num += ($x[$i] - $meanX) * ($y[$i] - $meanY);
        $denX += pow($x[$i] - $meanX, 2);
        $denY += pow($y[$i] - $meanY, 2);
    }
    $den = sqrt($denX) * sqrt($denY);
    return ($den == 0) ? 0 : $num / $den;
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT-OS | Backtesting Engine</title>
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
        .input-field {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
        }
        .input-field:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body class="min-h-screen p-4 md:p-8">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-black italic tracking-tighter text-blue-600 mb-2">BACKTESTING ENGINE</h1>
            <p class="text-sm text-slate-400 uppercase tracking-widest">Historical Strategy Simulation (<?= $current_mode ?>)</p>
        </div>

        <!-- Warning if no historical data -->
        <?php if (isset($no_data_error) && $no_data_error): ?>
        <div class="glass-panel rounded-3xl p-6 mb-6 bg-red-900/20 border border-red-500/20">
            <h3 class="text-sm font-bold text-red-400 uppercase tracking-widest mb-2">⚠️ No Historical Data Available</h3>
            <p class="text-xs text-slate-300 mb-3">The asset_history table is empty. Backtesting requires historical price data.</p>
            <div class="text-xs text-slate-400 space-y-1">
                <p>• Make sure price_aggregator.php is running as a cron job</p>
                <p>• Check that the cron job is collecting data from Binance</p>
                <p>• Wait for at least 24 hours of data collection</p>
            </div>
        </div>
        <?php else: ?>
        <div class="glass-panel rounded-3xl p-4 mb-6">
            <p class="text-xs text-slate-400">Assets with historical data: <span class="text-green-400 font-bold"><?= count($assets) ?></span></p>
        </div>
        <?php endif; ?>

        <!-- Backtesting Form -->
        <div class="glass-panel rounded-3xl p-8 mb-6">
            <form method="POST" class="space-y-6">
                <!-- Asset Selection -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Asset A</label>
                        <select name="asset_a" class="input-field w-full p-4 rounded-xl text-white font-bold" required>
                            <?php foreach ($assets as $asset): ?>
                                <option value="<?= $asset ?>"><?= $asset ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Asset B</label>
                        <select name="asset_b" class="input-field w-full p-4 rounded-xl text-white font-bold" required>
                            <?php foreach ($assets as $asset): ?>
                                <option value="<?= $asset ?>"><?= $asset ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Date Range -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Start Date</label>
                        <input type="date" name="start_date" value="<?= date('Y-m-d', strtotime('-30 days')) ?>" class="input-field w-full p-4 rounded-xl text-white font-bold" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">End Date</label>
                        <input type="date" name="end_date" value="<?= date('Y-m-d') ?>" class="input-field w-full p-4 rounded-xl text-white font-bold" required>
                    </div>
                </div>

                <!-- Strategy Parameters (Bot Panel Style) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Capital ($)</label>
                        <input type="number" name="capital" value="200" step="1" min="10" class="input-field w-full p-4 rounded-xl text-white font-bold" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Max Trades</label>
                        <input type="number" name="max_trades" value="100" step="1" min="1" class="input-field w-full p-4 rounded-xl text-white font-bold" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Leverage (x)</label>
                        <input type="number" name="leverage" value="5" step="1" min="1" max="125" class="input-field w-full p-4 rounded-xl text-white font-bold" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Entry Z-Score</label>
                        <input type="number" name="entry_z_score" value="1.5" step="0.1" min="0.5" max="5.0" class="input-field w-full p-4 rounded-xl text-white font-bold" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">TP ($)</label>
                        <input type="number" name="tp_dollar" value="40" step="1" min="1" class="input-field w-full p-4 rounded-xl text-white font-bold" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">TP (Z-Score)</label>
                        <input type="number" name="tp_zscore" value="0" step="0.1" min="0" max="5.0" class="input-field w-full p-4 rounded-xl text-white font-bold" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">SL ($)</label>
                        <input type="number" name="sl_dollar" value="140" step="1" min="1" class="input-field w-full p-4 rounded-xl text-white font-bold" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">SL (Z-Score)</label>
                        <input type="number" name="sl_zscore" value="10" step="0.1" min="0" max="100" class="input-field w-full p-4 rounded-xl text-white font-bold" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Min Profit ($)</label>
                        <input type="number" name="min_profit" value="2" step="0.5" min="0" class="input-field w-full p-4 rounded-xl text-white font-bold" required>
                    </div>
                </div>

                <!-- Run Button -->
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 py-5 rounded-xl font-black uppercase text-xs tracking-widest transition-all">
                    Run Backtesting
                </button>
            </form>
        </div>

        <!-- Results -->
        <?php if ($result && !isset($result['error'])): ?>
        <div class="glass-panel rounded-3xl p-8 mb-6">
            <h2 class="text-2xl font-black text-blue-600 mb-6">Backtesting Results: <?= $result['pair'] ?></h2>
            
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-slate-900/50 p-6 rounded-xl">
                    <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Total Return</p>
                    <p class="text-3xl font-black <?= $result['metrics']['total_return'] >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                        <?= number_format($result['metrics']['total_return'], 2) ?>%
                    </p>
                </div>
                
                <div class="bg-slate-900/50 p-6 rounded-xl">
                    <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Net Profit</p>
                    <p class="text-3xl font-black <?= $result['metrics']['net_profit'] >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                        $<?= number_format($result['metrics']['net_profit'], 2) ?>
                    </p>
                </div>
                
                <div class="bg-slate-900/50 p-6 rounded-xl">
                    <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Win Rate</p>
                    <p class="text-3xl font-black text-blue-400">
                        <?= number_format($result['metrics']['win_rate'], 1) ?>%
                    </p>
                </div>
                
                <div class="bg-slate-900/50 p-6 rounded-xl">
                    <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Sharpe Ratio</p>
                    <p class="text-3xl font-black text-purple-400">
                        <?= number_format($result['metrics']['sharpe_ratio'], 2) ?>
                    </p>
                </div>
            </div>

            <!-- Secondary Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-slate-900/50 p-4 rounded-xl">
                    <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Total Trades</p>
                    <p class="text-xl font-black text-white"><?= $result['metrics']['total_trades'] ?></p>
                </div>
                
                <div class="bg-slate-900/50 p-4 rounded-xl">
                    <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Profit Factor</p>
                    <p class="text-xl font-black text-yellow-400">
                        <?= $result['metrics']['profit_factor'] >= 999 ? '∞' : number_format($result['metrics']['profit_factor'], 2) ?>
                    </p>
                </div>
                
                <div class="bg-slate-900/50 p-4 rounded-xl">
                    <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Max Drawdown</p>
                    <p class="text-xl font-black text-red-400">
                        <?= number_format($result['metrics']['max_drawdown'], 1) ?>%
                    </p>
                </div>
                
                <div class="bg-slate-900/50 p-4 rounded-xl">
                    <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Avg Duration</p>
                    <p class="text-xl font-black text-slate-300">
                        <?= number_format($result['metrics']['avg_trade_duration'], 1) ?>h
                    </p>
                </div>
            </div>

            <!-- Parameters Used -->
            <div class="bg-slate-900/30 p-4 rounded-xl mb-6">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Parameters Used</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                    <p><span class="text-slate-500">Entry Z:</span> <?= $result['parameters']['entry_z_score'] ?></p>
                    <p><span class="text-slate-500">TP ($):</span> $<?= $result['parameters']['tp_dollar'] ?></p>
                    <p><span class="text-slate-500">TP (Z):</span> <?= $result['parameters']['tp_zscore'] ?></p>
                    <p><span class="text-slate-500">SL ($):</span> $<?= $result['parameters']['sl_dollar'] ?></p>
                    <p><span class="text-slate-500">SL (Z):</span> <?= $result['parameters']['sl_zscore'] ?></p>
                    <p><span class="text-slate-500">Min Profit:</span> $<?= $result['parameters']['min_profit'] ?></p>
                    <p><span class="text-slate-500">Leverage:</span> <?= $result['parameters']['leverage'] ?>x</p>
                </div>
            </div>

            <!-- Debug Information -->
            <div class="bg-slate-900/30 p-4 rounded-xl mb-6">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Debug Information</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs mb-3">
                    <p><span class="text-slate-500">Data Points:</span> <?= $result['debug']['total_data_points'] ?></p>
                    <p><span class="text-slate-500">Z-Scores:</span> <?= $result['debug']['z_scores_calculated'] ?></p>
                    <p><span class="text-slate-500">Trades:</span> <?= $result['debug']['trades_found'] ?></p>
                    <p><span class="text-slate-500">Max Z:</span> <?= number_format($result['debug']['max_z_score'], 2) ?></p>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs mb-3">
                    <p><span class="text-slate-500">Min Z:</span> <?= number_format($result['debug']['min_z_score'], 2) ?></p>
                    <p><span class="text-slate-500">Max Corr:</span> <?= number_format($result['debug']['max_correlation'] * 100, 1) ?>%</p>
                    <p><span class="text-slate-500">Z Hits:</span> <?= $result['debug']['z_threshold_hits'] ?></p>
                    <p><span class="text-slate-500">Corr Hits:</span> <?= $result['debug']['correlation_threshold_hits'] ?></p>
                </div>
                <?php if ($result['debug']['max_z_score'] < $result['parameters']['entry_z_score']): ?>
                <div class="bg-yellow-900/20 p-3 rounded-xl border border-yellow-500/20">
                    <p class="text-xs text-yellow-400">⚠️ Max Z-score (<?= number_format($result['debug']['max_z_score'], 2) ?>) is below threshold (<?= $result['parameters']['entry_z_score'] ?>). Try lowering the Entry Z-Score.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Trade History -->
            <div class="bg-slate-900/30 p-4 rounded-xl">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Trade History (Last 100)</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-slate-500">
                                <th class="text-left py-2">Entry Time</th>
                                <th class="text-left py-2">Exit Time</th>
                                <th class="text-left py-2">Entry Z</th>
                                <th class="text-left py-2">Exit Z</th>
                                <th class="text-left py-2">P&L</th>
                                <th class="text-left py-2">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($result['trades'], -100) as $trade): ?>
                                <tr class="border-t border-slate-800">
                                    <td class="py-2"><?= substr($trade['entry_time'], 0, 16) ?></td>
                                    <td class="py-2"><?= substr($trade['exit_time'], 0, 16) ?></td>
                                    <td class="py-2"><?= number_format($trade['entry_z'], 2) ?></td>
                                    <td class="py-2"><?= number_format($trade['exit_z'], 2) ?></td>
                                    <td class="py-2 <?= $trade['pnl'] >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                                        $<?= number_format($trade['pnl'], 2) ?>
                                    </td>
                                    <td class="py-2"><?= $trade['reason'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php elseif ($result && isset($result['error'])): ?>
        <div class="glass-panel rounded-3xl p-8 mb-6">
            <p class="text-red-400 font-bold mb-4">Error: <?= $result['error'] ?></p>
            <?php if (isset($result['debug'])): ?>
            <div class="bg-slate-900/50 p-4 rounded-xl">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Debug Information</h3>
                <div class="text-xs text-slate-300 space-y-1">
                    <p>Pair A: <?= $result['debug']['pair_a'] ?></p>
                    <p>Pair B: <?= $result['debug']['pair_b'] ?></p>
                    <p>Start Date: <?= $result['debug']['start_date'] ?></p>
                    <p>End Date: <?= $result['debug']['end_date'] ?></p>
                    <p>Records Found: <?= $result['debug']['records_found'] ?></p>
                </div>
            </div>
            <div class="mt-4 p-4 bg-yellow-900/20 rounded-xl border border-yellow-500/20">
                <p class="text-xs text-yellow-400">💡 Tip: Make sure the price_aggregator.php is running to collect historical data. Also check that the selected pair has data in the asset_history table.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Info Box -->
        <div class="glass-panel rounded-3xl p-6 mt-6">
            <h3 class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-3">Backtesting Tips</h3>
            <ul class="text-xs text-slate-500 space-y-2">
                <li>• Use at least 30 days of historical data for reliable results</li>
                <li>• Test different Z-score thresholds (1.5 - 3.0)</li>
                <li>• Correlation threshold should be > 80% for better results</li>
                <li>• Backtesting is not a guarantee of future performance</li>
                <li>• Always test with paper trading before going live</li>
            </ul>
        </div>
    </div>
</body>
</html>
