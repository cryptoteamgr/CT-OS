<?php
/**
 * CT-OS | copyright by cryptoteam.gr - api_get_scanner.php
 */
ini_set('display_errors', 0); 
error_reporting(E_ALL);

session_start();
$user_id = $_SESSION['user_id'] ?? null;
session_write_close(); 

header('Content-Type: application/json');

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once 'db_config.php';

try {
    // 1. Βελτιστοποίηση ανάγνωσης (μηδενίζει το lag αν η βάση είναι φορτωμένη)
    $pdo->query("SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");

    // 2. Επιλέγουμε τα 16 πιο "καυτά" pairs (αυτά που είναι κοντά σε σήμα)
    $stmt = $pdo->prepare("
        SELECT asset_a, asset_b, last_z_score, last_beta, last_update 
        FROM pair_universe 
        WHERE is_active = 1
        ORDER BY ABS(last_z_score) DESC 
        LIMIT 16
    ");
    $stmt->execute();
    $pairs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedPairs = [];
    foreach ($pairs as $p) {
        $zVal = (float)($p['last_z_score'] ?? 0);
        $bVal = (float)($p['last_beta'] ?? 1.0);
        
        $formattedPairs[] = [
            'asset_a'     => strtoupper($p['asset_a']),
            'asset_b'     => strtoupper($p['asset_b']),
            'z_score'     => number_format($zVal, 2, '.', ''), 
            'beta'        => number_format($bVal, 2, '.', ''),
            // Προσθήκη για να ξέρουμε αν τα δεδομένα είναι "φρέσκα" (τελευταία 2 λεπτά)
            'is_stale'    => (time() - strtotime($p['last_update'] ?? 'now') > 120),
            'last_update' => (!empty($p['last_update'])) ? date('H:i:s', strtotime($p['last_update'])) : 'N/A'
        ];
    }

    echo json_encode(['success' => true, 'pairs' => $formattedPairs]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB Latency Error']);
}