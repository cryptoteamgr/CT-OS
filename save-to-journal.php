<?php
/**
 * CT-OS | copyright by cryptoteam.gr - save-to-journal.php
 * ----------------------------------------------------------------
 * Σκοπός: Το API καταχώρησης ολοκληρωμένων συναλλαγών στο ημερολόγιο (Trade Journal). 
 * Λειτουργεί ως η γέφυρα μεταξύ της εκτέλεσης (Scanner/Manual Close) και της στατιστικής ανάλυσης.
 */
/**
 * CT-OS | SAVE TO JOURNAL BRIDGE (FINAL FIXED)
 * v16.7 - Cleaned Gross/Net PnL Logic
 */
session_start();
header('Content-Type: application/json');

require_once 'db_config.php';

// 1. Authorization Check
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Session missing.']);
    exit;
}

// 2. Only POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

// 3. Data Sanitization
$pair = filter_input(INPUT_POST, 'pair', FILTER_SANITIZE_SPECIAL_CHARS);
$pnl = filter_input(INPUT_POST, 'pnl', FILTER_VALIDATE_FLOAT);
$comm = filter_input(INPUT_POST, 'commission', FILTER_VALIDATE_FLOAT) ?: 0; // Νέα λήψη
$setup = filter_input(INPUT_POST, 'setup', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'AUTO_TRADE';
$notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';

// Account Mode Logic
$post_mode = filter_input(INPUT_POST, 'mode', FILTER_SANITIZE_SPECIAL_CHARS);
$account_type = $post_mode ? strtoupper($post_mode) : ($_SESSION['journal_mode'] ?? 'LIVE');

if (!in_array($account_type, ['DEMO', 'LIVE'])) {
    $account_type = 'DEMO';
}

// 4. Validation
if (!$pair || $pnl === false) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Data. Check Pair and PnL format.']);
    exit;
}

try {
    // 1. ΕΛΕΓΧΟΣ ΓΙΑ ΔΙΠΛΟΕΓΓΡΑΦΗ (Προληπτικά πριν το INSERT)
    // Ελέγχουμε αν υπάρχει ήδη το ίδιο pair για τον ίδιο χρήστη τα τελευταία 10 δευτερόλεπτα
    $checkSql = "SELECT id FROM zEQZkBci_trade_journal 
                 WHERE user_id = :uid 
                 AND pair = :pair 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 10 SECOND) 
                 LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':uid' => $user_id, ':pair' => strtoupper($pair)]);
    
    if ($checkStmt->fetch()) {
        echo json_encode([
            'status' => 'warning', 
            'message' => 'Duplicate detected. Entry already exists for this pair in the last 10s.'
        ]);
        exit;
    }

    // 2. ΕΚΤΕΛΕΣΗ INSERT (Updated: Cleaned Gross/Net & No Slippage)
    $sql = "INSERT INTO zEQZkBci_trade_journal 
            (user_id, account_type, pair, gross_pnl, total_commission, net_pnl, setup, notes, created_at) 
            VALUES (:uid, :atype, :pair, :pnl, :comm, (:pnl - :comm), :setup, :notes, NOW())";
            
    $stmt = $pdo->prepare($sql);
    
    $params = [
        ':uid'   => $user_id,
        ':atype' => $account_type,
        ':pair'  => strtoupper($pair),
        ':pnl'   => $pnl,
        ':comm'  => $comm,
        ':setup' => $setup,
        ':notes' => $notes
    ];

    $stmt->execute($params);

    echo json_encode([
        'status' => 'success', 
        'message' => 'Trade logged successfully!',
        'details' => ['pair' => $pair, 'pnl' => $pnl, 'mode' => $account_type]
    ]);

} catch (PDOException $e) {
    // Αν για κάποιο λόγο περάσει τον πρώτο έλεγχο αλλά χτυπήσει στο UNIQUE KEY της SQL
    if ($e->getCode() == 23000) { 
        echo json_encode(['status' => 'warning', 'message' => 'Duplicate entry blocked by database.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>