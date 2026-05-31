<?php
// panel/action_queue.php
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? 0;
    
    if ($action === 'cancel' && $id) {
        $stmt = $db->prepare("UPDATE action_queue SET status = 'CANCELLED', updated_at = NOW() WHERE id = ? AND status IN ('PENDING', 'PROCESSING')");
        $stmt->execute([$id]);
        audit_log('action_queue_cancel', 'action_queue', $id);
    } elseif ($action === 'retry' && $id) {
        $stmt = $db->prepare("UPDATE action_queue SET status = 'PENDING', retry_count = retry_count + 1, locked_until = NULL, updated_at = NOW() WHERE id = ? AND status IN ('FAILED', 'DEAD', 'PROCESSING')");
        $stmt->execute([$id]);
        audit_log('action_queue_retry', 'action_queue', $id);
    } elseif ($action === 'mark_dead' && $id) {
        $stmt = $db->prepare("UPDATE action_queue SET status = 'DEAD', updated_at = NOW() WHERE id = ? AND status = 'FAILED'");
        $stmt->execute([$id]);
        audit_log('action_queue_mark_dead', 'action_queue', $id);
    }
}

$statusFilter = $_GET['status'] ?? '';
$where = "";
$params = [];
if ($statusFilter) {
    $where = "WHERE status = ?";
    $params[] = $statusFilter;
}

$actions = $db->prepare("SELECT * FROM action_queue $where ORDER BY created_at DESC LIMIT 100");
$actions->execute($params);
$actions = $actions->fetchAll(PDO::FETCH_ASSOC);

render_header('Action Queue');
?>

<div class="glass-panel rounded-2xl overflow-hidden">
    <div class="p-6 border-b border-white/5 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <h2 class="text-xl font-semibold">Görev Kuyruğu (Action Queue)</h2>
        
        <div class="flex space-x-2 overflow-x-auto pb-2 w-full md:w-auto">
            <a href="action_queue.php" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $statusFilter==='' ? 'bg-primary text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">Tümü</a>
            <a href="action_queue.php?status=PENDING" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $statusFilter==='PENDING' ? 'bg-blue-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">PENDING</a>
            <a href="action_queue.php?status=PROCESSING" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $statusFilter==='PROCESSING' ? 'bg-purple-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">PROCESSING</a>
            <a href="action_queue.php?status=COMPLETED" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $statusFilter==='COMPLETED' ? 'bg-green-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">COMPLETED</a>
            <a href="action_queue.php?status=FAILED" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $statusFilter==='FAILED' ? 'bg-red-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">FAILED</a>
            <a href="action_queue.php?status=DEAD" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $statusFilter==='DEAD' ? 'bg-slate-900 text-white border border-slate-700' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">DEAD</a>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-white/5 text-slate-400 text-sm uppercase tracking-wider">
                    <th class="p-4 font-medium">ID / Action</th>
                    <th class="p-4 font-medium">Status</th>
                    <th class="p-4 font-medium">Retry / Timeout</th>
                    <th class="p-4 font-medium">Zamanlama</th>
                    <th class="p-4 font-medium text-right">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                <?php if (count($actions) === 0): ?>
                <tr><td colspan="5" class="p-8 text-center text-slate-500">Kuyrukta kayıt bulunmuyor.</td></tr>
                <?php endif; ?>
                
                <?php foreach($actions as $a): 
                    $sColor = 'text-slate-400';
                    if ($a['status'] === 'PENDING') $sColor = 'text-blue-400';
                    if ($a['status'] === 'PROCESSING') $sColor = 'text-purple-400';
                    if ($a['status'] === 'COMPLETED') $sColor = 'text-green-400';
                    if ($a['status'] === 'FAILED') $sColor = 'text-red-400';
                    if ($a['status'] === 'DEAD') $sColor = 'text-slate-500';
                ?>
                <tr class="hover:bg-white/[0.02] transition-colors group">
                    <td class="p-4">
                        <div class="font-mono text-xs text-slate-500 mb-1">#<?= $a['id'] ?></div>
                        <div class="font-bold text-slate-200"><?= htmlspecialchars($a['action_type']) ?></div>
                        <div class="text-[10px] text-slate-500 truncate max-w-[200px]" title="<?= htmlspecialchars($a['payload']) ?>"><?= htmlspecialchars($a['payload']) ?></div>
                    </td>
                    <td class="p-4 font-semibold <?= $sColor ?>"><?= $a['status'] ?></td>
                    <td class="p-4 text-sm text-slate-300">
                        <div>Retry: <?= $a['retry_count'] ?>/<?= $a['max_retry'] ?></div>
                        <div class="text-xs text-slate-500">Timeout: <?= $a['timeout_seconds'] ?>s</div>
                        <?php if($a['last_error']): ?>
                            <div class="text-[10px] text-red-400 truncate max-w-[150px]" title="<?= htmlspecialchars($a['last_error']) ?>"><?= htmlspecialchars($a['last_error']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="p-4 text-xs text-slate-400">
                        <div>Oluşturulma: <?= $a['created_at'] ?></div>
                        <div>Güncelleme: <?= $a['updated_at'] ?></div>
                    </td>
                    <td class="p-4 text-right space-x-2">
                        <?php if(in_array($a['status'], ['PENDING', 'PROCESSING'])): ?>
                            <button onclick="queueAction('cancel', <?= $a['id'] ?>)" class="text-xs bg-slate-800 hover:bg-red-500/20 text-red-400 px-3 py-1 rounded border border-white/5 transition-colors">Cancel</button>
                        <?php endif; ?>
                        
                        <?php if(in_array($a['status'], ['FAILED', 'DEAD', 'PROCESSING'])): ?>
                            <button onclick="queueAction('retry', <?= $a['id'] ?>)" class="text-xs bg-slate-800 hover:bg-blue-500/20 text-blue-400 px-3 py-1 rounded border border-white/5 transition-colors">Retry</button>
                        <?php endif; ?>

                        <?php if($a['status'] === 'FAILED'): ?>
                            <button onclick="queueAction('mark_dead', <?= $a['id'] ?>)" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-1 rounded border border-white/5 transition-colors">Mark DEAD</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<form id="queueForm" method="POST" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" id="q_action">
    <input type="hidden" name="id" id="q_id">
</form>

<script>
function queueAction(action, id) {
    if(action === 'cancel' && !confirm('Bu işlemi iptal etmek istediğinize emin misiniz?')) return;
    document.getElementById('q_action').value = action;
    document.getElementById('q_id').value = id;
    document.getElementById('queueForm').submit();
}
</script>

<?php render_footer(); ?>
