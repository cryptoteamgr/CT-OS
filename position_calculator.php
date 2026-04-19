<?php
/**
 * CT-OS | copyright by cryptoteam.gr - position_calculator.php
 * ----------------------------------------------------------------
 * Σκοπός: Position Size Calculator για risk-based trading
 * Υποστηρίζει: Fixed Fractional, Kelly Criterion, Volatility-based sizing
 */

require_once 'db_config.php';
require_once 'functions.php';
require_once 'auth_check.php';

$user_id = $_SESSION['user_id'];

// Λήψη στοιχείων χρήστη
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

// Λήψη API keys για live balance check
$current_mode = $user['bot_mode'] ?? 'DEMO';
$stmtAPI = $pdo->prepare("SELECT api_key, api_secret FROM api_keys WHERE user_id = ? AND account_type = ? AND is_active = 1 LIMIT 1");
$stmtAPI->execute([$user_id, $current_mode]);
$api = $stmtAPI->fetch();

$live_balance = 0;
if ($api) {
    $accountInfo = getBinanceAccountInfo(
        decrypt_data($api['api_key']),
        decrypt_data($api['api_secret']),
        $current_mode
    );
    if ($accountInfo) {
        $live_balance = $accountInfo['balance'];
    }
}

// Default values
$account_balance = $live_balance > 0 ? $live_balance : ($user['capital_per_trade'] * 10); // Default estimate
$risk_percent = 1.0; // Default 1% risk per trade
$stop_loss_percent = 5.0; // Default 5% stop loss
$leverage = $user['leverage'] ?? 10;
$method = $_POST['method'] ?? 'fixed_fractional';

// Calculation result
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_balance = floatval($_POST['account_balance']);
    $risk_percent = floatval($_POST['risk_percent']);
    $stop_loss_percent = floatval($_POST['stop_loss_percent']);
    $leverage = intval($_POST['leverage']);
    $method = $_POST['method'];
    
    switch ($method) {
        case 'fixed_fractional':
            $result = calculateFixedFractional($account_balance, $risk_percent, $stop_loss_percent, $leverage);
            break;
        case 'kelly_criterion':
            $win_rate = floatval($_POST['win_rate']);
            $avg_win = floatval($_POST['avg_win']);
            $avg_loss = floatval($_POST['avg_loss']);
            $result = calculateKellyCriterion($account_balance, $risk_percent, $stop_loss_percent, $leverage, $win_rate, $avg_win, $avg_loss);
            break;
        case 'volatility_based':
            $volatility = floatval($_POST['volatility']);
            $result = calculateVolatilityBased($account_balance, $risk_percent, $stop_loss_percent, $leverage, $volatility);
            break;
    }
}

/**
 * Fixed Fractional Position Sizing
 * Risk = Account Balance × Risk% × Leverage
 */
function calculateFixedFractional($balance, $risk_percent, $stop_loss, $leverage) {
    $risk_amount = $balance * ($risk_percent / 100);
    $position_size = $risk_amount / ($stop_loss / 100);
    $position_with_leverage = $position_size * $leverage;
    
    return [
        'method' => 'Fixed Fractional',
        'risk_amount' => $risk_amount,
        'position_size' => $position_size,
        'position_with_leverage' => $position_with_leverage,
        'margin_required' => $position_with_leverage / $leverage,
        'max_loss' => $position_with_leverage * ($stop_loss / 100),
        'risk_reward' => calculateRiskReward($stop_loss)
    ];
}

/**
 * Kelly Criterion Position Sizing
 * Kelly% = (Win% × AvgWin - Loss% × AvgLoss) / AvgWin
 */
function calculateKellyCriterion($balance, $risk_percent, $stop_loss, $leverage, $win_rate, $avg_win, $avg_loss) {
    $loss_rate = 1 - $win_rate;
    
    // Kelly formula
    $kelly_percent = (($win_rate * $avg_win) - ($loss_rate * $avg_loss)) / $avg_win;
    
    // Cap Kelly at 25% to avoid over-trading
    $kelly_percent = max(0, min($kelly_percent, 0.25));
    
    // Use the smaller of Kelly or user risk
    $effective_risk = min($kelly_percent * 100, $risk_percent);
    
    $risk_amount = $balance * ($effective_risk / 100);
    $position_size = $risk_amount / ($stop_loss / 100);
    $position_with_leverage = $position_size * $leverage;
    
    return [
        'method' => 'Kelly Criterion',
        'kelly_percent' => $kelly_percent * 100,
        'effective_risk' => $effective_risk,
        'risk_amount' => $risk_amount,
        'position_size' => $position_size,
        'position_with_leverage' => $position_with_leverage,
        'margin_required' => $position_with_leverage / $leverage,
        'max_loss' => $position_with_leverage * ($stop_loss / 100),
        'risk_reward' => calculateRiskReward($stop_loss)
    ];
}

/**
 * Volatility-Based Position Sizing
 * Higher volatility = smaller position
 */
function calculateVolatilityBased($balance, $risk_percent, $stop_loss, $leverage, $volatility) {
    // Adjust position size based on volatility
    // Volatility < 1%: 100% position
    // Volatility 1-3%: 75% position
    // Volatility 3-5%: 50% position
    // Volatility > 5%: 25% position
    
    if ($volatility < 1) {
        $volatility_factor = 1.0;
    } elseif ($volatility < 3) {
        $volatility_factor = 0.75;
    } elseif ($volatility < 5) {
        $volatility_factor = 0.5;
    } else {
        $volatility_factor = 0.25;
    }
    
    $risk_amount = $balance * ($risk_percent / 100);
    $position_size = ($risk_amount / ($stop_loss / 100)) * $volatility_factor;
    $position_with_leverage = $position_size * $leverage;
    
    return [
        'method' => 'Volatility-Based',
        'volatility_factor' => $volatility_factor,
        'risk_amount' => $risk_amount,
        'position_size' => $position_size,
        'position_with_leverage' => $position_with_leverage,
        'margin_required' => $position_with_leverage / $leverage,
        'max_loss' => $position_with_leverage * ($stop_loss / 100),
        'risk_reward' => calculateRiskReward($stop_loss)
    ];
}

/**
 * Calculate Risk-Reward Ratio
 */
function calculateRiskReward($stop_loss) {
    // Assuming 2:1 risk-reward by default
    return [
        'risk' => $stop_loss,
        'reward' => $stop_loss * 2,
        'ratio' => 2.0
    ];
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CT-OS | Position Size Calculator</title>
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
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-black italic tracking-tighter text-blue-600 mb-2">POSITION CALCULATOR</h1>
            <p class="text-sm text-slate-400 uppercase tracking-widest">Risk-Based Position Sizing</p>
        </div>

        <!-- Main Calculator -->
        <div class="glass-panel rounded-3xl p-8 mb-6">
            <form method="POST" class="space-y-6">
                <!-- Method Selection -->
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Calculation Method</label>
                    <select name="method" class="input-field w-full p-4 rounded-xl text-white font-bold" onchange="toggleMethodFields()">
                        <option value="fixed_fractional" <?= $method === 'fixed_fractional' ? 'selected' : '' ?>>Fixed Fractional (1-2% Risk)</option>
                        <option value="kelly_criterion" <?= $method === 'kelly_criterion' ? 'selected' : '' ?>>Kelly Criterion (Advanced)</option>
                        <option value="volatility_based" <?= $method === 'volatility_based' ? 'selected' : '' ?>>Volatility-Based (Dynamic)</option>
                    </select>
                </div>

                <!-- Common Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Account Balance (USDT)</label>
                        <input type="number" name="account_balance" value="<?= $account_balance ?>" step="0.01" class="input-field w-full p-4 rounded-xl text-white font-bold" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Risk Per Trade (%)</label>
                        <input type="number" name="risk_percent" value="<?= $risk_percent ?>" step="0.1" min="0.1" max="10" class="input-field w-full p-4 rounded-xl text-white font-bold" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Stop Loss (%)</label>
                        <input type="number" name="stop_loss_percent" value="<?= $stop_loss_percent ?>" step="0.1" min="0.5" max="20" class="input-field w-full p-4 rounded-xl text-white font-bold" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Leverage</label>
                        <input type="number" name="leverage" value="<?= $leverage ?>" min="1" max="125" class="input-field w-full p-4 rounded-xl text-white font-bold" required>
                    </div>
                </div>

                <!-- Kelly Criterion Fields -->
                <div id="kelly_fields" class="grid grid-cols-1 md:grid-cols-3 gap-4 <?= $method === 'kelly_criterion' ? '' : 'hidden' ?>">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Win Rate (%)</label>
                        <input type="number" name="win_rate" value="55" step="0.1" min="1" max="99" class="input-field w-full p-4 rounded-xl text-white font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Avg Win (%)</label>
                        <input type="number" name="avg_win" value="3" step="0.1" min="0.1" class="input-field w-full p-4 rounded-xl text-white font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Avg Loss (%)</label>
                        <input type="number" name="avg_loss" value="2" step="0.1" min="0.1" class="input-field w-full p-4 rounded-xl text-white font-bold">
                    </div>
                </div>

                <!-- Volatility-Based Fields -->
                <div id="volatility_fields" class="<?= $method === 'volatility_based' ? '' : 'hidden' ?>">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Asset Volatility (%)</label>
                    <input type="number" name="volatility" value="2.5" step="0.1" min="0.1" max="20" class="input-field w-full p-4 rounded-xl text-white font-bold">
                </div>

                <!-- Calculate Button -->
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 py-5 rounded-xl font-black uppercase text-xs tracking-widest transition-all">
                    Calculate Position Size
                </button>
            </form>
        </div>

        <!-- Results -->
        <?php if ($result): ?>
        <div class="glass-panel rounded-3xl p-8">
            <h2 class="text-2xl font-black text-blue-600 mb-6"><?= $result['method'] ?></h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-slate-900/50 p-6 rounded-xl">
                    <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Risk Amount</p>
                    <p class="text-3xl font-black text-red-400">$<?= number_format($result['risk_amount'], 2) ?></p>
                </div>
                
                <div class="bg-slate-900/50 p-6 rounded-xl">
                    <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Position Size (No Leverage)</p>
                    <p class="text-3xl font-black text-white">$<?= number_format($result['position_size'], 2) ?></p>
                </div>
                
                <div class="bg-slate-900/50 p-6 rounded-xl">
                    <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Position Size (With Leverage)</p>
                    <p class="text-3xl font-black text-blue-400">$<?= number_format($result['position_with_leverage'], 2) ?></p>
                </div>
                
                <div class="bg-slate-900/50 p-6 rounded-xl">
                    <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Margin Required</p>
                    <p class="text-3xl font-black text-green-400">$<?= number_format($result['margin_required'], 2) ?></p>
                </div>
                
                <div class="bg-slate-900/50 p-6 rounded-xl">
                    <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Max Loss</p>
                    <p class="text-3xl font-black text-red-400">$<?= number_format($result['max_loss'], 2) ?></p>
                </div>
                
                <div class="bg-slate-900/50 p-6 rounded-xl">
                    <p class="text-xs text-slate-400 uppercase tracking-widest mb-1">Risk-Reward Ratio</p>
                    <p class="text-3xl font-black text-yellow-400">1:<?= $result['risk_reward']['ratio'] ?></p>
                    <p class="text-xs text-slate-500 mt-1">Risk: <?= $result['risk_reward']['risk'] ?>% | Reward: <?= $result['risk_reward']['reward'] ?>%</p>
                </div>
            </div>
            
            <?php if ($method === 'kelly_criterion'): ?>
            <div class="mt-6 bg-blue-900/20 p-4 rounded-xl border border-blue-500/20">
                <p class="text-xs text-blue-400 uppercase tracking-widest mb-2">Kelly Criterion Analysis</p>
                <p class="text-sm text-slate-300">Optimal Kelly%: <?= number_format($result['kelly_percent'], 2) ?>%</p>
                <p class="text-sm text-slate-300">Effective Risk: <?= number_format($result['effective_risk'], 2) ?>%</p>
            </div>
            <?php endif; ?>
            
            <?php if ($method === 'volatility_based'): ?>
            <div class="mt-6 bg-purple-900/20 p-4 rounded-xl border border-purple-500/20">
                <p class="text-xs text-purple-400 uppercase tracking-widest mb-2">Volatility Adjustment</p>
                <p class="text-sm text-slate-300">Volatility Factor: <?= number_format($result['volatility_factor'], 2) ?>x</p>
                <p class="text-xs text-slate-500 mt-1">Higher volatility = smaller position for safety</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Info Box -->
        <div class="glass-panel rounded-3xl p-6 mt-6">
            <h3 class="text-sm font-bold text-slate-400 uppercase tracking-widest mb-3">Risk Management Tips</h3>
            <ul class="text-xs text-slate-500 space-y-2">
                <li>• Never risk more than 1-2% per trade</li>
                <li>• Use stop losses on every position</li>
                <li>• Kelly Criterion can be aggressive - cap at 25%</li>
                <li>• Adjust position size based on volatility</li>
                <li>• Maintain a risk-reward ratio of at least 1:2</li>
            </ul>
        </div>
    </div>

    <script>
        function toggleMethodFields() {
            const method = document.querySelector('select[name="method"]').value;
            const kellyFields = document.getElementById('kelly_fields');
            const volatilityFields = document.getElementById('volatility_fields');
            
            kellyFields.classList.add('hidden');
            volatilityFields.classList.add('hidden');
            
            if (method === 'kelly_criterion') {
                kellyFields.classList.remove('hidden');
            } else if (method === 'volatility_based') {
                volatilityFields.classList.remove('hidden');
            }
        }
    </script>
</body>
</html>
