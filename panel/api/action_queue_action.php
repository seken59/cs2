<?php
require_once dirname(__DIR__) . '/core.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? 0;

if (!$id || !in_array($action, ['cancel', 'retry', 'mark_dead'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz parametreler.']);
    exit;
}

try {
    if ($action === 'cancel') {
        $stmt = $db->prepare("UPDATE action_queue SET status = 'CANCELLED', last_error = 'Cancelled by admin', locked_until = NULL, worker_id = NULL, updated_at = NOW() WHERE id = ? AND status IN ('PENDING', 'PROCESSING')");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            audit_log('action_queue_cancel', 'action_queue', $id);
            echo json_encode(['success' => true, 'message' => 'Görev iptal edildi.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Görev iptal edilemedi (zaten tamamlanmış veya başarısız olabilir).']);
        }
    } elseif ($action === 'retry') {
        // Status PENDING yapılıyor, last_error siliniyor
        $stmt = $db->prepare("UPDATE action_queue SET status = 'PENDING', retry_count = 0, locked_until = NULL, worker_id = NULL, last_error = NULL, updated_at = NOW() WHERE id = ? AND status IN ('FAILED', 'DEAD', 'PROCESSING')");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            audit_log('action_queue_retry', 'action_queue', $id);
            echo json_encode(['success' => true, 'message' => 'Görev yeniden kuyruğa eklendi.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Görev yeniden denenemez durumda.']);
        }
    } elseif ($action === 'mark_dead') {
        $stmt = $db->prepare("UPDATE action_queue SET status = 'DEAD', last_error = 'Marked DEAD by admin', updated_at = NOW() WHERE id = ? AND status = 'FAILED'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            audit_log('action_queue_mark_dead', 'action_queue', $id);
            echo json_encode(['success' => true, 'message' => 'Görev DEAD olarak işaretlendi.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Görev DEAD olarak işaretlenemedi.']);
        }
    }
} catch (Throwable $e) {
    error_log("ActionQueue API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası oluştu.']);
}
