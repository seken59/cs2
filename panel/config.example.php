<?php
// KO-LMS Panel Configuration Example
// DO NOT PUT REAL SECRETS HERE. Copy to config.php and keep it safe.

$DATABASE_IP = getenv('DATABASE_IP') ?: 'localhost';
$DATABASE_USR = getenv('DATABASE_USR') ?: 'root';
$DATABASE_PWD = getenv('DATABASE_PWD') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'kocs2_db';

if (empty($DATABASE_USR) || empty($DATABASE_PWD) || empty($DB_NAME)) {
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

