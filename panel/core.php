<?php
// panel/core.php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    die("Sistem konfigürasyon dosyası bulunamadı. Lütfen config.php'yi oluşturun.");
}
require_once $configPath;

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

// Check IP
$ENABLE_IP_CHECK = false; // Devre dışı
if ($ENABLE_IP_CHECK && !in_array(getUserIP(), $ALLOWED_IPS)) {
    header('HTTP/1.0 403 Forbidden');
    die("<h1>403 Forbidden</h1><p>Yetkisiz IP Adresi. Sistem kilitlendi.</p>");
}

// Auth Check (Eğer login.php de değilsek)
$currentPage = basename($_SERVER['PHP_SELF']);
if ($currentPage !== 'login.php' && $currentPage !== 'logout.php') {
    if (!isset($_SESSION['kolms_admin_logged_in']) || $_SESSION['kolms_admin_logged_in'] !== true) {
        header("Location: login.php");
        exit;
    }
}

// Check CSRF on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($currentPage !== 'login.php') {
        $csrf_token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
            header('HTTP/1.0 403 Forbidden');
            die(json_encode(['success' => false, 'message' => 'CSRF Token Hatası.']));
        }
    }
} else {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// Yardımcı Fonksiyonlar
function audit_log($action, $target_type = null, $target_id = null, $details = null) {
    global $db;
    $user = $_SESSION['admin_username'] ?? 'admin';
    $ip = getUserIP();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    try {
        $stmt = $db->prepare("INSERT INTO admin_audit_log (admin_user, ip_address, user_agent, action, target_type, target_id, details, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user, $ip, $ua, $action, $target_type, $target_id, json_encode($details)]);
    } catch(Exception $e) {}
}

function enqueue_action($action_type, $payload, $idempotency_key = null) {
    global $db;
    if (!$idempotency_key) {
        $idempotency_key = $action_type . '_' . uniqid();
    }
    try {
        $stmt = $db->prepare("INSERT INTO action_queue (idempotency_key, action_type, payload, status, created_at, updated_at) VALUES (?, ?, ?, 'PENDING', NOW(), NOW())");
        $stmt->execute([$idempotency_key, $action_type, json_encode($payload)]);
        return true;
    } catch(Exception $e) {
        if ($e->getCode() == 23000) return true; // Duplicate entry for idempotency
        return false;
    }
}

// Eksik tabloları oluştur
try {
    $db->exec("CREATE TABLE IF NOT EXISTS system_health (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        metric_name VARCHAR(100) NOT NULL,
        metric_value TEXT NOT NULL,
        status ENUM('OK','WARNING','CRITICAL') NOT NULL,
        collected_at DATETIME NOT NULL
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS admin_audit_log (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        admin_user VARCHAR(100),
        ip_address VARCHAR(45),
        user_agent TEXT,
        action VARCHAR(100),
        target_type VARCHAR(50),
        target_id VARCHAR(100),
        details JSON,
        created_at DATETIME NOT NULL
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT,
        description TEXT,
        last_updated_by VARCHAR(100),
        updated_at DATETIME
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS action_queue (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        action_type VARCHAR(100) NOT NULL,
        payload JSON,
        status ENUM('PENDING','PROCESSING','COMPLETED','FAILED','DEAD','CANCELLED') DEFAULT 'PENDING',
        idempotency_key VARCHAR(255) UNIQUE,
        worker_id VARCHAR(100),
        locked_until DATETIME,
        retry_count INT DEFAULT 0,
        max_retry INT DEFAULT 3,
        timeout_seconds INT DEFAULT 300,
        last_error TEXT,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS system_alerts (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        severity ENUM('INFO','WARNING','CRITICAL') DEFAULT 'INFO',
        alert_type VARCHAR(100),
        message TEXT,
        related_entity_type VARCHAR(50),
        related_entity_id VARCHAR(100),
        status ENUM('OPEN','ACKED','RESOLVED') DEFAULT 'OPEN',
        created_at DATETIME NOT NULL,
        acknowledged_at DATETIME,
        resolved_at DATETIME
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS worker_heartbeats (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        worker_name VARCHAR(100) UNIQUE,
        worker_type VARCHAR(50),
        status VARCHAR(50),
        heartbeat_at DATETIME NOT NULL,
        last_error TEXT,
        version VARCHAR(50),
        commit_hash VARCHAR(50)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS farm_batches (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        batch_id VARCHAR(255) UNIQUE,
        status VARCHAR(50),
        created_at DATETIME,
        started_at DATETIME,
        finished_at DATETIME,
        duration INT,
        last_heartbeat DATETIME,
        last_error TEXT
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS backup_runs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        backup_type VARCHAR(50),
        backup_file VARCHAR(255),
        status VARCHAR(50),
        size_bytes BIGINT,
        checksum_sha256 VARCHAR(64),
        started_at DATETIME,
        finished_at DATETIME,
        error_message TEXT,
        restore_test_status VARCHAR(50)
    )");
    
    // Seed system_settings if empty
    $count = $db->query("SELECT COUNT(*) FROM system_settings")->fetchColumn();
    if ($count == 0) {
        $db->exec("INSERT INTO system_settings (setting_key, setting_value, description) VALUES 
            ('maintenance_mode', '0', 'Sistem bakım modu'),
            ('batch_size', '4', 'Aynı anda kaç container çalışacak'),
            ('max_parallel_batches', '1', 'Max paralel çalışan batch sayısı'),
            ('backup_enabled', '1', 'Yedekleme aktif mi')
        ");
    }
} catch (Exception $e) {}
?>
