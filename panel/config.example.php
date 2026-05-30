<?php
// KO-LMS Panel Example Config
// DO NOT USE THIS FILE IN PRODUCTION. COPY TO config.php AND CHANGE VALUES.

function requiredEnv($key) {
    $value = getenv($key);
    if ($value === false || $value === '' || $value === 'CHANGE_ME') {
        http_response_code(500);
        die("CRITICAL ERROR: Missing required env: " . $key);
    }
    return $value;
}

$DATABASE_IP = requiredEnv('DATABASE_IP');
$DATABASE_USR = requiredEnv('DATABASE_USR');
$DATABASE_PWD = requiredEnv('DATABASE_PWD');
$DB_NAME = requiredEnv('DB_NAME');

try {
    $db = new PDO("mysql:host=$DATABASE_IP;dbname=$DB_NAME", $DATABASE_USR, $DATABASE_PWD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed.');
}

$ENABLE_IP_CHECK = false;
$ALLOWED_IPS = ['127.0.0.1'];
?>
