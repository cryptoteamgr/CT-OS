<?php
/**
 * CT-OS | copyright by cryptoteam.gr - api_fetch_broadcast.php
 */
session_start();
$user_id = $_SESSION['user_id'] ?? null;
// ΚΛΕΙΣΙΜΟ SESSION ΑΜΕΣΩΣ: Ελευθερώνει τον browser να φορτώσει το Dashboard
session_write_close(); 

require_once 'db_config.php'; // Απαραίτητο για το $pdo
header('Content-Type: application/json');

if (!$user_id) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Προσθήκη για μέγιστη ταχύτητα στην SQL
    $pdo->query("SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");

    // 1. Λήψη ΜΟΝΟ των νέων notifications
    $stmt = $pdo->prepare("SELECT id, message, type FROM system_notifications 
                           WHERE (user_id IS NULL OR user_id = 0 OR user_id = ?) 
                           AND is_shown = 0 
                           ORDER BY id ASC LIMIT 5");
    $stmt->execute([$user_id]);
    $broadcasts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Μάρκαρε ως "Διαβασμένα"
    if (!empty($broadcasts)) {
        $ids = array_column($broadcasts, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmtUpdate = $pdo->prepare("UPDATE system_notifications SET is_shown = 1 WHERE id IN ($placeholders)");
        $stmtUpdate->execute($ids);
    }

    // 3. Typing Indicator
    $stmtTyping = $pdo->query("SELECT is_active FROM system_status WHERE id = 1 AND last_update > NOW() - INTERVAL 10 SECOND LIMIT 1");
    $typingData = $stmtTyping->fetch(PDO::FETCH_ASSOC);
    $is_typing = $typingData ? (int)($typingData['is_active'] ?? 0) : 0;

    echo json_encode([
        'notifs' => $broadcasts ?: [],
        'is_typing' => $is_typing
    ]);

} catch (PDOException $e) {
    echo json_encode(['notifs' => [], 'is_typing' => 0, 'error' => 'Latency']);
}