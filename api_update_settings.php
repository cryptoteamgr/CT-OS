<?php
/**
 * CT-OS | copyright by cryptoteam.gr - api_update_settings.php
 * ----------------------------------------------------------------
 * Σκοπός: Διεπαφή ενημέρωσης ρυθμίσεων με σωστό υπολογισμό Exposure (Stat-Arb).
 */

header('Content-Type: application/json');
session_start();

require_once 'db_config.php';
require_once 'functions.php';

// 1. Έλεγχος Αυθεντικοποίησης
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['key'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

// Καθαρισμός κλειδιού
$key = strtolower(trim((string)$data['key']));
$value = $data['value'];

// 2. [WHITELIST] - Επιτρεπόμενα κλειδιά από το UI (Προστέθηκε το exit_sl_z)
$allowed = [
    'bot_mode',
    'bot_status',
    'z_threshold',
    'z_exit_threshold',
    'sl_zscore',
    'z_sl_threshold', 
    'exit_sl_z',        // Σύνδεση με το UI για το Stop Loss Z-Score
    'capital_per_trade',
    'leverage',
    'tp_dollar',
    'sl_dollar',
    'master_tp',
    'master_sl',
    'tp_zscore',
    'master_exit_z',
    'max_open_trades'
];

if (!in_array($key, $allowed)) {
    echo json_encode(['success' => false, 'message' => "Η ρύθμιση '$key' δεν επιτρέπεται."]);
    exit;
}

// 3. [MAPPING] - Σύνδεση UI Key με Database Column (Πλήρης χάρτης)
$db_column = $key; 

if ($key === 'master_tp')      $db_column = 'tp_dollar';
if ($key === 'master_sl')      $db_column = 'sl_dollar';
if ($key === 'tp_zscore')      $db_column = 'z_exit_threshold';
if ($key === 'master_exit_z')  $db_column = 'z_exit_threshold';
if ($key === 'z_exit_threshold') $db_column = 'z_exit_threshold';
if ($key === 'z_sl_threshold') $db_column = 'sl_zscore'; 
if ($key === 'sl_zscore')      $db_column = 'sl_zscore';
if ($key === 'exit_sl_z')      $db_column = 'sl_zscore'; // ΔΙΟΡΘΩΣΗ: Αντιστοίχιση UI -> DB

try {
    // 4. Sanitize & Validation
    if (is_numeric($value)) {
        $value = floatval($value);
    }

    if ($db_column === 'leverage') {
        $value = max(1, min(125, intval($value)));
    }

    if ($db_column === 'capital_per_trade' && is_numeric($value)) {
        $value = max(1.0, floatval($value)); 
    }

    if ($db_column === 'max_open_trades') {
        $value = max(1, intval($value));
    }

    // 5. Εκτέλεση του Update στη Βάση Δεδομένων
    $stmt = $pdo->prepare("UPDATE users SET `$db_column` = :val WHERE id = :uid");
    $success = $stmt->execute([':val' => $value, ':uid' => $user_id]);

    if ($success) {
        // Ενημέρωση Session για άμεση χρήση από την PHP
        if(!isset($_SESSION['user_settings'])) $_SESSION['user_settings'] = [];
        $_SESSION['user_settings'][$db_column] = $value; 

        // 6. ΣΤΑΤΙΣΤΙΚΟΣ ΥΠΟΛΟΓΙΣΜΟΣ EXPOSURE & MARGIN
        $stmtStats = $pdo->prepare("SELECT capital_per_trade, leverage FROM users WHERE id = ?");
        $stmtStats->execute([$user_id]);
        $user_row = $stmtStats->fetch(PDO::FETCH_ASSOC);
        
        $cap = floatval($user_row['capital_per_trade'] ?? 0);
        $lev = intval($user_row['leverage'] ?? 1);
        
        // Σωστή λογική: Το Capital που ορίζει ο χρήστης είναι το MARGIN.
        // Το Exposure είναι Margin * Leverage.
        $total_exposure = $cap * $lev; 
        $margin_needed = $cap; // Το Margin είναι αυτό που δέσμευσε ο χρήστης

        echo json_encode([
            'success' => true, 
            'key' => $key, 
            'db_column' => $db_column,
            'new_value' => $value,
            'new_exposure' => number_format($total_exposure, 2, '.', ''),
            'est_margin' => number_format($margin_needed, 2, '.', ''),
            'message' => "Η ρύθμιση αποθηκεύτηκε με επιτυχία!"
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Αποτυχία ενημέρωσης στη βάση.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}