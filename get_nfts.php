<?php
/**
 * CT-OS | copyright by cryptoteam.gr - get_nfts.php
 * ----------------------------------------------------------------
 * Σκοπός: Υπηρεσία ανάκτησης NFTs (NFT Fetch Service) από το OpenSea API V2 για μια συγκεκριμένη διεύθυνση πορτοφολιού.
 */

header('Content-Type: application/json');

// Error Reporting Off για να μην "μολύνει" το JSON output με PHP Warnings
error_reporting(0);
ini_set('display_errors', 0);

$address = $_GET['address'] ?? '';

if (!$address || !preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
    echo json_encode(['error' => 'INVALID_ADDRESS', 'nfts' => []]);
    exit;
}

/**
 * Σημείωση 2026: Η OpenSea πλέον απαιτεί API Key στα περισσότερα endpoints.
 * Χρησιμοποιούμε το V2 API που είναι το standard.
 */
$url = "https://api.opensea.io/api/v2/chain/ethereum/account/{$address}/nfts";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Accept: application/json",
    "X-API-KEY: YOUR_OPENSEA_KEY_HERE" // Αν δεν έχεις, το αφήνεις κενό αλλά ίσως φας rate limit
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$nfts = [];

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['nfts']) && is_array($data['nfts'])) {
        foreach ($data['nfts'] as $asset) {
            // Φιλτράρουμε μόνο όσα έχουν όνομα και εικόνα
            if (!empty($asset['image_url'])) {
                $nfts[] = [
                    'name' => $asset['name'] ?? 'Unnamed NFT',
                    'image_url' => $asset['image_url'],
                    'collection' => $asset['collection'] ?? ''
                ];
            }
        }
    }
}

// Πάντα επιστρέφουμε έγκυρο JSON structure
echo json_encode([
    'success' => ($httpCode === 200),
    'address' => $address,
    'nfts' => $nfts
]);
exit;