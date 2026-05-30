<?php
// KO-LMS Panel Configuration Example
// DO NOT PUT REAL SECRETS HERE. Copy to config.php and keep it safe.

$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'kocs2_db';

if (empty($DB_USER) || empty($DB_PASS) || empty($DB_NAME)) {
    http_response_code(500);
    exit('Server configuration error: Database credentials missing.');
}

try {
    $db = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USER, $DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed.');
}

$ENABLE_IP_CHECK = false;
$ALLOWED_IPS = ['127.0.0.1'];
?>
