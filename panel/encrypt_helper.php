<?php
// panel/encrypt_helper.php
function encrypt_secret($plaintext) {
    global $SYSTEM_KEY;
    if (empty($plaintext)) return null;
    
    // Hash SYSTEM_KEY to 32 bytes (256-bit) for AES-256
    $key = hash('sha256', $SYSTEM_KEY, true);
    $iv = random_bytes(12); // GCM standard is 12 bytes
    $tag = '';
    
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    
    if ($ciphertext === false) return null;
    
    return bin2hex($iv) . ':' . bin2hex($ciphertext) . ':' . bin2hex($tag);
}

function decrypt_secret($encrypted) {
    global $SYSTEM_KEY;
    if (empty($encrypted) || strpos($encrypted, ':') === false) return null;
    
    $parts = explode(':', $encrypted);
    if (count($parts) !== 3) return null;
    
    $key = hash('sha256', $SYSTEM_KEY, true);
    $iv = hex2bin($parts[0]);
    $ciphertext = hex2bin($parts[1]);
    $tag = hex2bin($parts[2]);
    
    $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $plaintext === false ? null : $plaintext;
}
