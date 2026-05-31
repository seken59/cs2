<?php
// Dragle CS Panel Config
$DATABASE_IP = '127.0.0.1';
$DATABASE_USR = 'cs_admin';
$DATABASE_PWD = '4JEfgBjDm7IlGBJN';
$DB_NAME = 'cs_bot';
$SYSTEM_KEY = 'KVKJ6V7XZD2Q3M5C';

try {
    $db = new PDO("mysql:host=$DATABASE_IP;dbname=$DB_NAME", $DATABASE_USR, $DATABASE_PWD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed: ' . $e->getMessage());
}

$ENABLE_IP_CHECK = false;
$ALLOWED_IPS = ['127.0.0.1'];
?>
