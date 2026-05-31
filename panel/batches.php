<?php
// panel/batches.php
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $batch_id = $_POST['batch_id'] ?? '';
    
    if ($action === 'stop' && $batch_id) {
        enqueue_action('STOP_BATCH', ['batch_id' => $batch_id]);
        audit_log('batch_stop_request', 'farm_batches', $batch_id);
        $msg = "Batch durdurma (Graceful Stop) talebi kuyruğa alındı.";
    } elseif ($action === 'abort' && $batch_id) {
        enqueue_action('ABORT_BATCH', ['batch_id' => $batch_id]);
        audit_log('batch_abort_request', 'farm_batches', $batch_id);
        $msg = "Batch zorla sonlandırma (Abort) talebi kuyruğa alındı.";
    } elseif ($action === 'retry' && $batch_id) {
        enqueue_action('RETRY_BATCH', ['batch_id' => $batch_id]);
        audit_log('batch_retry_request', 'farm_batches', $batch_id);
        $msg = "Batch yeniden başlatma (Retry) talebi kuyruğa alındı.";
    } elseif ($action === 'freeze' && $batch_id) {
        enqueue_action('FREEZE_BATCH', ['batch_id' => $batch_id]);
        audit_log('batch_freeze_request', 'farm_batches', $batch_id);
        $msg = "Batch dondurma (Freeze) talebi kuyruğa alındı.";
    }
}

$batches = $db->query("SELECT * FROM farm_batches ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

render_header('Batch Yönetimi');
?>

<div class="glass-panel rounded-2xl overflow-hidden">
    <div class="p-6 border-b border-white/5 flex justify-between items-center">
        <h2 class="text-xl font-semibold">Aktif & Geçmiş Batchler</h2>
        <button onclick="location.reload()" class="text-slate-400 hover:text-white transition-colors"><i class="fas fa-sync-alt"></i> Yenile</button>
    </div>
    
    <?php if(isset($msg)): ?>
        <div class="m-6 bg-blue-500/20 border border-blue-500 text-blue-300 p-3 rounded">
            <i class="fas fa-info-circle mr-2"></i> <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-white/5 text-slate-400 text-sm uppercase tracking-wider">
                    <th class="p-4 font-medium">Batch ID</th>
                    <th class="p-4 font-medium">Durum</th>
                    <th class="p-4 font-medium">Süre / Zaman</th>
                    <th class="p-4 font-medium">Hata Durumu</th>
                    <th class="p-4 font-medium text-right">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                <?php if (count($batches) === 0): ?>
                <tr><td colspan="5" class="p-8 text-center text-slate-500">Kayıtlı batch bulunmuyor.</td></tr>
                <?php endif; ?>
                
                <?php foreach($batches as $b): 
                    $statusColor = 'text-slate-400';
                    $statusBg = 'bg-slate-500/20';
                    
                    if ($b['status'] === 'RUNNING') { $statusColor = 'text-blue-400'; $statusBg = 'bg-blue-500/20'; }
                    if ($b['status'] === 'COMPLETED') { $statusColor = 'text-green-400'; $statusBg = 'bg-green-500/20'; }
                    if ($b['status'] === 'FAILED' || $b['status'] === 'ABORTED') { $statusColor = 'text-red-400'; $statusBg = 'bg-red-500/20'; }
                ?>
                <tr class="hover:bg-white/[0.02] transition-colors group">
                    <td class="p-4">
                        <div class="font-bold text-slate-200"><?= htmlspecialchars($b['batch_id']) ?></div>
                    </td>
                    <td class="p-4">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $statusColor ?> <?= $statusBg ?>">
                            <?= htmlspecialchars($b['status']) ?>
                        </span>
                    </td>
                    <td class="p-4 text-xs text-slate-400">
                        <div>Başlangıç: <?= $b['started_at'] ?: '-' ?></div>
                        <div>Bitiş: <?= $b['finished_at'] ?: '-' ?></div>
                        <div>Süre: <?= $b['duration'] ? $b['duration'].'s' : '-' ?></div>
                    </td>
                    <td class="p-4 text-xs text-slate-400">
                        <?php if($b['last_error']): ?>
                            <span class="text-red-400"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($b['last_error']) ?></span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                        <div class="mt-1">Son Kalp Atışı: <?= $b['last_heartbeat'] ?: '-' ?></div>
                    </td>
                    <td class="p-4 text-right space-x-2">
                        <?php if($b['status'] === 'RUNNING'): ?>
                            <button onclick="batchAction('stop', '<?= htmlspecialchars($b['batch_id']) ?>')" class="text-xs bg-slate-800 hover:bg-yellow-500/20 text-yellow-400 px-3 py-1 rounded border border-white/5 transition-colors" title="Graceful Stop">Stop</button>
                            <button onclick="batchAction('abort', '<?= htmlspecialchars($b['batch_id']) ?>')" class="text-xs bg-slate-800 hover:bg-red-500/20 text-red-400 px-3 py-1 rounded border border-white/5 transition-colors" title="Force Kill">Abort</button>
                        <?php else: ?>
                            <button onclick="batchAction('retry', '<?= htmlspecialchars($b['batch_id']) ?>')" class="text-xs bg-slate-800 hover:bg-blue-500/20 text-blue-400 px-3 py-1 rounded border border-white/5 transition-colors" title="Yeniden Başlat">Retry</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<form id="batchForm" method="POST" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" id="b_action">
    <input type="hidden" name="batch_id" id="b_batch_id">
</form>

<script>
function batchAction(action, batch_id) {
    if(action === 'abort' && !confirm('DİKKAT: Bu batch\'i zorla kapatmak container seviyesinde hata bırakabilir. Emin misiniz?')) return;
    document.getElementById('b_action').value = action;
    document.getElementById('b_batch_id').value = batch_id;
    document.getElementById('batchForm').submit();
}
</script>

<?php render_footer(); ?>
