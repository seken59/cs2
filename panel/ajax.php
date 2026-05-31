<?php
// panel/ajax.php
require_once __DIR__ . '/core.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek.']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'add_account') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');
    $secret = trim($_POST['shared_secret'] ?? '');

    if (!$user || !$pass) {
        echo json_encode(['success' => false, 'message' => 'Kullanıcı adı ve şifre zorunludur.']);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO accounts (username, password, shared_secret) VALUES (?, ?, ?)");
        $stmt->execute([$user, $pass, $secret]);
        audit_log('account_add', 'accounts', $db->lastInsertId(), ['username' => $user]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(['success' => false, 'message' => 'Bu kullanıcı adı zaten mevcut.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Veritabanı hatası.']);
        }
    }
    exit;
}

if ($action === 'account_delete') {
    $id = $_POST['id'] ?? 0;
    if ($id) {
        $db->prepare("DELETE FROM accounts WHERE id = ?")->execute([$id]);
        audit_log('account_delete', 'accounts', $id);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Geçersiz ID']);
    }
    exit;
}

if ($action === 'account_reset') {
    $id = $_POST['id'] ?? 0;
    if ($id) {
        $db->prepare("UPDATE accounts SET status = 'IDLE', locked_until = NULL, batch_id = NULL WHERE id = ?")->execute([$id]);
        audit_log('account_reset', 'accounts', $id);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Geçersiz ID']);
    }
    exit;
}

if ($action === 'account_clear_error') {
    $id = $_POST['id'] ?? 0;
    if ($id) {
        $db->prepare("UPDATE accounts SET last_error = NULL WHERE id = ?")->execute([$id]);
        audit_log('account_clear_error', 'accounts', $id);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Geçersiz ID']);
    }
    exit;
}

if ($action === 'emergency_stop') {
    // 1. Maintenance Mode aç
    $db->prepare("UPDATE system_settings SET setting_value = '1' WHERE setting_key = 'maintenance_mode'")->execute();
    
    // 2. Aktif batchleri ABORTING statüsüne çek
    $db->prepare("UPDATE farm_batches SET status = 'ABORTED' WHERE status = 'RUNNING'")->execute();
    
    // 3. Action Queue'ya Emergency Stop gönder (Tüm containerlara STOP komutu iletmesi için worker okuyacak)
    enqueue_action('EMERGENCY_STOP', []);
    
    // 4. Alert oluştur
    $db->prepare("INSERT INTO system_alerts (severity, alert_type, message, status, created_at) VALUES ('CRITICAL', 'EMERGENCY_STOP', 'Sistem yöneticisi tarafından Acil Durdurma Protokolü başlatıldı. Tüm operasyonlar durduruldu.', 'OPEN', NOW())")->execute();
    
    // 5. Audit log
    audit_log('emergency_stop_triggered', 'system', 'ALL');
    
    echo json_encode(['success' => true, 'message' => 'Emergency Stop aktif edildi.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Bilinmeyen işlem.']);
