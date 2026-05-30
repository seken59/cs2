<?php
// panel/ajax.php - KO-LMS Farm Kontrol Paneli Backend

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['kolms_admin_logged_in']) || $_SESSION['kolms_admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit;
}

$dbHost = 'localhost';
$dbUser = 'cs_admin';
$dbPass = 'zz12JkE3O@10gFr1';
$dbName = 'cs_bot';

try {
    $db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası.']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $shared_secret = $_POST['shared_secret'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Kullanıcı adı ve şifre zorunludur.']);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO accounts (username, password, shared_secret, status, level, xp) VALUES (?, ?, ?, 'IDLE', 1, 0)");
        $stmt->execute([$username, $password, $shared_secret]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        // UNIQUE constraint failed hatası
        echo json_encode(['success' => false, 'message' => 'Bu hesap zaten ekli.']);
    }
    exit;
}

if ($action === 'delete') {
    $id = $_POST['id'] ?? 0;
    
    try {
        $stmt = $db->prepare("DELETE FROM accounts WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Silme hatası.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Geçersiz işlem.']);
