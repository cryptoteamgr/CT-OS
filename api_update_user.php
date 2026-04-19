<?php
/**
 * CT-OS | copyright by cryptoteam.gr - api_update_user.php
 * ----------------------------------------------------------------
 * Σκοπός: API επεξεργασίας και μαζικής ενημέρωσης στοιχείων χρήστη και ρυθμίσεων Bot.
 */
session_start();
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    
    // Συλλέγουμε τα πεδία που θέλουμε να αναβαθμίσουμε
    $fields = [
        'username', 'email', 'phone', 'role', 
        'bot_status', 'bot_mode', 'capital_per_trade', 'leverage',
        'z_threshold', 'z_exit_threshold', 'binance_api_key', 
        'binance_api_secret', 'wallet_address', 'telegram_id',
        'tp_dollar', 'sl_dollar', 'tp_zscore', 'sl_zscore'
    ];

    $updates = [];
    $values = [];

    require_once 'functions.php'; // Απαραίτητο για την encrypt_data()

    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $val = $_POST[$field];

            // ΚΡΥΠΤΟΓΡΑΦΗΣΗ: Αν το πεδίο είναι API Key ή Secret, το κρυπτογραφούμε
            if ($field === 'binance_api_key' || $field === 'binance_api_secret') {
                if (!empty($val)) {
                    $val = encrypt_data($val);
                }
            }

            $updates[] = "$field = ?";
            $values[] = $val;
        }
    }

    $values[] = $id; // Για το WHERE id = ?
    
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        header("Location: admin_users.php?success=1");
    } catch (Exception $e) {
        die("Error updating user: " . $e->getMessage());
    }
}