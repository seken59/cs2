<?php
require_once dirname(__DIR__) . '/core.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

try {
    $statusFilter = $_GET['status'] ?? '';
    $where = "";
    $params = [];
    if ($statusFilter) {
        $where = "WHERE status = ?";
        $params[] = $statusFilter;
    }

    $stmt = $db->prepare("SELECT id, action_type, payload, status, retry_count, max_retry, timeout_seconds, last_error, created_at, updated_at FROM action_queue $where ORDER BY created_at DESC LIMIT 100");
    $stmt->execute($params);
    $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter secret payloads just in case
    foreach ($actions as &$a) {
        if (strpos($a['action_type'], 'ADD_ACCOUNT') !== false || strpos($a['action_type'], 'SECRET') !== false) {
            $a['payload'] = '{"hidden":"secret payload redacted"}';
        }
    }

    echo json_encode(['success' => true, 'data' => $actions]);
} catch (Throwable $e) {
    error_log("ActionQueueList API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası oluştu.']);
}
