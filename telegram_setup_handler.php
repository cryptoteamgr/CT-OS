<?php
/**
 * CT-OS | copyright by cryptoteam.gr - telegram_setup_handler.php
 * ----------------------------------------------------------------
 * Σκοπός: Ο αλγόριθμος αυτόματης σύνδεσης (Smart Connect) με το Telegram Bot API. 
 * Διαχειρίζεται την επαλήθευση του Token, την αυτόματη ανίχνευση του Chat ID και την κρυπτογραφημένη αποθήκευση των στοιχείων.
 */
header('Content-Type: application/json');
session_start();
require_once 'db_config.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$api_token = isset($data['api_token']) ? trim($data['api_token']) : '';
// ΔΙΟΡΘΩΣΗ: Εξασφαλίζουμε ότι το label δεν είναι ποτέ κενό για τη βάση
$tg_label  = (isset($data['tg_label']) && !empty(trim($data['tg_label']))) ? trim($data['tg_label']) : 'My Telegram Bot';

try {
    // 1. Επαλήθευση Token
    $test_url = "https://api.telegram.org/bot{$api_token}/getMe";
    $res = json_decode(@file_get_contents($test_url), true);

    if (!$res || !$res['ok']) {
        throw new Exception("Άκυρο Bot Token. Ελέγξτε τι αντιγράψατε από τον BotFather.");
    }

    $bot_username = $res['result']['username'];

    // 2. Λήψη Chat ID (Updates)
    $update_url = "https://api.telegram.org/bot{$api_token}/getUpdates?offset=-1&limit=1";
    $updates = json_decode(@file_get_contents($update_url), true);

    $found_chat_id = null;
    if (isset($updates['result']) && !empty($updates['result'])) {
        // Ψάχνουμε το chat id είτε από κανονικό μήνυμα είτε από channel post
        $found_chat_id = $updates['result'][0]['message']['chat']['id'] ?? 
                         $updates['result'][0]['channel_post']['chat']['id'] ?? null;
    }

    if (!$found_chat_id) {
        echo json_encode([
            'success' => false, 
            'need_start' => true,
            'bot_link' => "https://t.me/{$bot_username}",
            'message' => "Το Bot βρέθηκε! Τώρα πατήστε START στο Bot σας και μετά ξαναπατήστε το κουμπί Smart Connect."
        ]);
        exit;
    }

    // 3. Κρυπτογράφηση
    $enc_token = encrypt_data($api_token);
    $enc_chat = encrypt_data((string)$found_chat_id);

    // 4. Αποθήκευση με ON DUPLICATE KEY UPDATE για να μην σκάει η SQL
    $pdo->prepare("UPDATE telegram_bots SET is_active = 0 WHERE user_id = ?")->execute([$user_id]);
    
    $sql = "INSERT INTO telegram_bots (user_id, api_label, api_token, chat_id, is_active) 
            VALUES (:uid, :label, :token, :chat, 1)
            ON DUPLICATE KEY UPDATE 
            api_label = VALUES(api_label), 
            api_token = VALUES(api_token), 
            chat_id = VALUES(chat_id), 
            is_active = 1";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':uid'   => $user_id,
        ':label' => $tg_label,
        ':token' => $enc_token,
        ':chat'  => $enc_chat
    ]);

    // Στέλνουμε και ένα Welcome Message αμέσως
    $welcome = "✅ <b>Σύνδεση Επιτυχής!</b>\nΤο CT-OS Terminal είναι πλέον συνδεδεμένο με αυτό το bot.";
    sendTelegramNotification($welcome, $user_id);

    echo json_encode(['success' => true, 'message' => "Το Bot '{$tg_label}' συνδέθηκε και αποθηκεύτηκε επιτυχώς!"]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => "Σφάλμα: " . $e->getMessage()]);
}