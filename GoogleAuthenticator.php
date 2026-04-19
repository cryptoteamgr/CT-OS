<?php
/**
 * CT-OS | copyright by cryptoteam.gr - GoogleAuthenticator.php
 * ----------------------------------------------------------------
 * Σκοπός: Κεντρική μηχανή 2FA (Two-Factor Authentication) βασισμένη στο πρωτόκολλο TOTP, βελτιστοποιημένη για PHP 8.x και απόλυτο συγχρονισμό με mobile apps.
 */

class PHPGangsta_GoogleAuthenticator {
    protected $_codeLength = 6;

    /**
     * Επαλήθευση του κωδικού που έδωσε ο χρήστης.
     * $discrepancy = 1 σημαίνει ανοχή 30 δευτερολέπτων (πριν/μετά).
     */
    public function verifyCode($secret, $code, $discrepancy = 1, $currentTimeSlice = null) {
        if ($currentTimeSlice === null) {
            $currentTimeSlice = floor(time() / 30);
        }

        // Ο κωδικός πρέπει να είναι ακριβώς 6 ψηφία
        if (strlen($code) != 6 || !is_numeric($code)) {
            return false;
        }

        for ($i = -$discrepancy; $i <= $discrepancy; ++$i) {
            $calculatedCode = $this->getCode($secret, $currentTimeSlice + $i);
            if ($this->timingSafeEquals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Παραγωγή του κωδικού TOTP (Time-based One-Time Password)
     */
    public function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretkey = $this->_base32Decode($secret);

        // Binary pack of time (8 bytes)
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $time, $secretkey, true);
        
        // Dynamic Truncation
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashpart = substr($hmac, $offset, 4);
        
        $value = unpack('N', $hashpart);
        $value = $value[1] & 0x7FFFFFFF;
        
        $modulo = pow(10, $this->_codeLength);
        return str_pad($value % $modulo, $this->_codeLength, '0', STR_PAD_LEFT);
    }

    /**
     * Διορθωμένο Base32 Decode (Strict RFC 4648)
     * Αυτή η μέθοδος λύνει το πρόβλημα του ασυγχρονισμού με τα κινητά.
     */
    protected function _base32Decode($secret) {
        if (empty($secret)) return '';

        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32charsFlipped = array_flip(str_split($base32chars));

        // Καθαρισμός από padding (=) και μετατροπή σε κεφαλαία
        $secret = strtoupper(str_replace(['=', ' ', '-'], '', $secret));
        
        $binaryString = "";
        foreach (str_split($secret) as $char) {
            if (!isset($base32charsFlipped[$char])) continue;
            $binaryString .= str_pad(decbin($base32charsFlipped[$char]), 5, '0', STR_PAD_LEFT);
        }

        $binArray = str_split($binaryString, 8);
        $res = "";
        foreach ($binArray as $bin) {
            if (strlen($bin) < 8) break;
            $res .= chr(bindec($bin));
        }

        return $res;
    }

    /**
     * Ασφαλής σύγκριση strings (Constant Time)
     */
    private function timingSafeEquals($safeString, $userString) {
        if (function_exists('hash_equals')) {
            return hash_equals($safeString, $userString);
        }
        $userLen = strlen($userString);
        $safeLen = strlen($safeString);
        if ($userLen != $safeLen) return false;
        $result = 0;
        for ($i = 0; $i < $userLen; $i++) {
            $result |= (ord($safeString[$i]) ^ ord($userString[$i]));
        }
        return $result === 0;
    }

    /**
     * Παραγωγή νέου τυχαίου Secret Key 16 χαρακτήρων
     */
    public function createSecret($secretLength = 16) {
        $validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        $rnd = random_bytes($secretLength);
        for ($i = 0; $i < $secretLength; $i++) {
            $secret .= $validChars[ord($rnd[$i]) % 32];
        }
        return $secret;
    }
}