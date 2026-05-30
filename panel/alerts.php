<?php
session_start();
if (!isset($_SESSION['kolms_admin_logged_in']) || $_SESSION['kolms_admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        die("Invalid CSRF token.");
    }

    if (isset($_POST['action']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $newStatus = $_POST['action'] === 'ack' ? 'ACKED' : 'RESOLVED';
        $timeCol = $_POST['action'] === 'ack' ? 'acknowledged_at' : 'resolved_at';
        $db->prepare("UPDATE system_alerts SET status = ?, $timeCol = NOW() WHERE id = ?")->execute([$newStatus, $id]);
        
        // Audit log kaydı
        $admin_user = 'admin'; // Session'dan çekilebilir
        $ip = $_SERVER['REMOTE_ADDR'];
        try {
            $db->prepare("INSERT INTO admin_audit_log (admin_user, ip_address, action, target_type, target_id, created_at) VALUES (?, ?, ?, 'ALERT', ?, NOW())")
               ->execute([$admin_user, $ip, "alert_" . $_POST['action'], $id]);
        } catch(Exception $e){}

        header("Location: alerts.php");
        exit;
    }
}

$stmt = $db->query("SELECT * FROM system_alerts ORDER BY created_at DESC LIMIT 50");
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Sistem Alarmlari (V9)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white">
<div class="container mt-4">
    <h2>Sistem Alarmlari (NOC)</h2>
    <a href="index.php" class="btn btn-secondary mb-3">Geri Don</a>
    <table class="table table-dark table-striped">
        <thead><tr><th>ID</th><th>Severity</th><th>Type</th><th>Message</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
            <?php foreach($alerts as $a): ?>
            <tr>
                <td><?= $a['id'] ?></td>
                <td><span class="badge bg-<?= $a['severity']=='CRITICAL'?'danger':($a['severity']=='WARNING'?'warning text-dark':'info') ?>"><?= $a['severity'] ?></span></td>
                <td><?= htmlspecialchars($a['alert_type']) ?></td>
                <td><?= htmlspecialchars($a['message']) ?></td>
                <td><?= $a['status'] ?></td>
                <td><?= $a['created_at'] ?></td>
                <td>
                    <?php if($a['status'] === 'OPEN'): ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                        <button name="action" value="ack" class="btn btn-sm btn-primary">ACK</button>
                    </form>
                    <?php endif; ?>
                    <?php if($a['status'] !== 'RESOLVED'): ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                        <button name="action" value="resolve" class="btn btn-sm btn-success">RESOLVE</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
