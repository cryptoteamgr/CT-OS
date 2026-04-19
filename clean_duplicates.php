<?php
/**
 * CT-OS | DATABASE MAINTENANCE
 * Καθαρισμός διπλότυπων OPEN trades. Κρατάει μόνο το πιο πρόσφατο ανά χρήστη και ζευγάρι.
 */
require_once 'db_config.php';

try {
    // SQL Logic: Διαγράφει εγγραφές που έχουν το ίδιο user_id, asset_a, asset_b 
    // αλλά ΜΙΚΡΟΤΕΡΟ id από το μέγιστο (δηλαδή το πιο παλιό).
    $sql = "DELETE t1 FROM active_pairs t1
            INNER JOIN active_pairs t2 
            WHERE t1.id < t2.id 
            AND t1.user_id = t2.user_id 
            AND t1.asset_a = t2.asset_a 
            AND t1.asset_b = t2.asset_b 
            AND t1.status = 'OPEN' 
            AND t2.status = 'OPEN'";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $count = $stmt->rowCount();
    
    echo json_encode([
        "status" => "success",
        "message" => "Database optimized. Removed $count duplicate entries."
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}