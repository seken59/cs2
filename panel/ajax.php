<?php
// panel/ajax.php - KO-LMS Farm Kontrol Paneli Backend

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['kolms_admin_logged_in']) || $_SESSION['kolms_admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']);
    exit;
}

require_once 'config.php';

$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
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

