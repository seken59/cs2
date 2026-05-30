<?php
// KO-LMS Panel Example Config
// DO NOT USE THIS FILE IN PRODUCTION. COPY TO config.php AND CHANGE VALUES.

$DATABASE_IP = getenv('DATABASE_IP') ?: 'CHANGE_ME';
$DATABASE_USR = getenv('DATABASE_USR') ?: 'CHANGE_ME';
$DATABASE_PWD = getenv('DATABASE_PWD') ?: 'CHANGE_ME';
$DB_NAME = getenv('DB_NAME') ?: 'CHANGE_ME';

if (empty($DATABASE_IP) || empty($DATABASE_USR) || empty($DATABASE_PWD) || empty($DB_NAME)) {
    http_response_code(500);
    exit('Server configuration error: Database credentials missing.');
}

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
