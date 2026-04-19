<?php
/**
 * CT-OS | copyright by cryptoteam.gr - api_get_journal_stats.php
 * ----------------------------------------------------------------
 * Σκοπός: API υπολογισμού στατιστικών αποκλειστικά από τον πίνακα zEQZkBci_binance_trades.
 */
require_once 'auth_check.php';
require_once 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$mode = $_GET['mode'] ?? 'DEMO';

try {
    // 1. Υπολογισμός στατιστικών με Win Rate και σωστά φίλτρα
    // Áíáãíþñéóç áðü active_pairs (CT-OS local data)
    $sql = "SELECT 
                COUNT(*) as total_fills,
                SUM(final_pnl) as total_gross,
                SUM(commission_a + commission_b) as total_fees,
                COUNT(CASE WHEN final_pnl > 0 THEN 1 END) as win_count
            FROM active_pairs 
            WHERE user_id = ? AND status = 'CLOSED' AND mode = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $mode]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_fills = intval($res['total_fills'] ?? 0);
    $gross = floatval($res['total_gross'] ?? 0);
    $fees = floatval($res['total_fees'] ?? 0);
    $net = $gross - $fees;
    
    // 2. Υπολογισμός Win Rate βάσει των fills
    $win_rate = ($total_fills > 0) ? round(($res['win_count'] / $total_fills) * 100, 1) : 0;

    // 3. Επιστροφή δεδομένων
    echo json_encode([
        'success'      => true,
        // active_pairs oréç ìåôñÜé ïëïêëçñùìÝíá Pairs, ÷ùñßò ÷ñåéáóôåß ÷áìäßóé
        'total_trades' => $total_fills,
        'gross_profit' => round($gross, 2),
        'total_fees'   => round($fees, 2),
        'net_profit'   => round($net, 2),
        'win_rate'     => $win_rate . "%"
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}