<?php
/**
 * CT-OS | copyright by cryptoteam.gr - ecrecover_helper.php
 * ----------------------------------------------------------------
 * Σκοπός: Τοπική ανάκτηση της διεύθυνσης πορτοφολιού (Ethereum Address) από κρυπτογραφημένες υπογραφές.
 */

$base = dirname(__FILE__) . '/vendor/';

// 1. Φόρτωση Keccak
if (file_exists($base . 'kornrunner/keccak/src/Keccak.php')) {
    require_once $base . 'kornrunner/keccak/src/Keccak.php';
}

// 2. BigInteger Alias Fix
require_once $base . 'simplito/bigint-wrapper-php/lib/BigInteger.php';
if (!class_exists('BI\BigInteger') && class_exists('BigInteger')) {
    class_alias('BigInteger', 'BI\BigInteger');
}

// 3. Autoloader για Elliptic & BN
spl_autoload_register(function ($class) use ($base) {
    $map = [
        'Elliptic\\' => 'simplito/elliptic-php/lib/',
        'BN\\'       => 'simplito/bn-php/lib/',
    ];
    foreach ($map as $prefix => $path) {
        if (strpos($class, $prefix) === 0) {
            $relative_class = substr($class, strlen($prefix));
            $file = $base . $path . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

use Elliptic\EC;
use kornrunner\Keccak;

function ecrecover($message, $signature) {
    try {
        // 1. Ethereum Signed Message Header
        $msg_prefix = "\x19Ethereum Signed Message:\n" . strlen($message) . $message;
        $hash = Keccak::hash($msg_prefix, 256);
        
        // 2. Formatting υπογραφής (Αφαίρεση 0x)
        if (strpos($signature, '0x') === 0) {
            $signature = substr($signature, 2);
        }
        
        if (strlen($signature) !== 130) return false;
        
        $r = substr($signature, 0, 64);
        $s = substr($signature, 64, 64);
        $v = hexdec(substr($signature, 128, 2));
        
        // 3. Recovery ID calculation
        $recid = ($v >= 27) ? $v - 27 : $v;

        $ec = new EC('secp256k1');
        
        // 4. Ανάκτηση Public Key - ΠΡΟΣΟΧΗ: encode('hex', false) για uncompressed
        $pubKey = $ec->recoverPubKey($hash, ['r' => $r, 's' => $s], $recid);
        $pubKeyHex = $pubKey->encode('hex', false); 
        
        // 5. Μετατροπή σε Address: 
        // Παίρνουμε το pubKeyHex, αφαιρούμε το '04' (πρώτο byte), 
        // κάνουμε keccak hash και κρατάμε τα τελευταία 40 hex ψηφία.
        $pubKeyBin = hex2bin(substr($pubKeyHex, 2));
        $addressHash = Keccak::hash($pubKeyBin, 256);
        
        return '0x' . substr($addressHash, -40);
        
    } catch (Exception $e) {
        return false;
    }
}