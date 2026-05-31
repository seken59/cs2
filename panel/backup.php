<?php
// panel/backup.php
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'run_backup') {
        enqueue_action('RUN_BACKUP', []);
        audit_log('backup_request');
        $msg = "Manuel yedekleme işlemi kuyruğa alındı. İşlem bitince listede görünecektir. (ID: " . $db->lastInsertId() . ")";
    } elseif ($action === 'run_restore_test') {
        enqueue_action('RUN_RESTORE_TEST', []);
        audit_log('restore_test_request');
        $msg = "Geri yükleme testi (dry-run) kuyruğa alındı. (ID: " . $db->lastInsertId() . ")";
    }
}

$backups = $db->query("SELECT * FROM backup_runs ORDER BY started_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

render_header('Backup & Restore Yönetimi');
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="glass-panel p-6 rounded-2xl md:col-span-2">
        <h2 class="text-xl font-semibold mb-4">Manuel İşlemler</h2>
        <?php if(isset($msg)): ?>
            <div class="mb-4 bg-blue-500/20 border border-blue-500 text-blue-300 p-3 rounded">
                <i class="fas fa-info-circle mr-2"></i> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>
        <p class="text-slate-400 text-sm mb-6">
            Yedekleme ve geri yükleme testleri doğrudan panel üzerinde çalışmaz. Host worker tarafından güvenli bir şekilde action_queue üzerinden izole ortamda çalıştırılır.
        </p>
        <form method="POST" class="flex gap-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <button type="submit" name="action" value="run_backup" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded-xl font-bold transition-all shadow-lg flex items-center">
                <i class="fas fa-database mr-2"></i> Manuel Yedek Al
            </button>
            <button type="submit" name="action" value="run_restore_test" class="bg-slate-800 hover:bg-slate-700 border border-white/10 text-white px-6 py-3 rounded-xl font-bold transition-all shadow-lg flex items-center">
                <i class="fas fa-vial mr-2"></i> Restore Test (Dry-Run)
            </button>
        </form>
    </div>
</div>

<div class="glass-panel rounded-2xl overflow-hidden">
    <div class="p-6 border-b border-white/5">
        <h2 class="text-xl font-semibold">Geçmiş Yedeklemeler (Backup Runs)</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-white/5 text-slate-400 text-sm uppercase tracking-wider">
                    <th class="p-4 font-medium">Tarih</th>
                    <th class="p-4 font-medium">Tip</th>
                    <th class="p-4 font-medium">Dosya / Boyut</th>
                    <th class="p-4 font-medium">Durum / Restore Test</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                <?php if (count($backups) === 0): ?>
                <tr><td colspan="4" class="p-8 text-center text-slate-500">Henüz yedekleme kaydı yok.</td></tr>
                <?php endif; ?>
                
                <?php foreach($backups as $b): 
                    $statusColor = 'text-slate-400';
                    if ($b['status'] === 'SUCCESS') $statusColor = 'text-green-400';
                    if ($b['status'] === 'FAILED') $statusColor = 'text-red-400';
                    if ($b['status'] === 'IN_PROGRESS') $statusColor = 'text-blue-400';
                ?>
                <tr class="hover:bg-white/[0.02] transition-colors">
                    <td class="p-4 text-sm text-slate-300">
                        <div><i class="fas fa-clock mr-1 text-slate-500"></i> B: <?= $b['started_at'] ?></div>
                        <div><i class="fas fa-flag-checkered mr-1 text-slate-500"></i> F: <?= $b['finished_at'] ?: '-' ?></div>
                    </td>
                    <td class="p-4 font-semibold text-slate-200"><?= htmlspecialchars($b['backup_type']) ?></td>
                    <td class="p-4 text-sm text-slate-300">
                        <div><?= htmlspecialchars($b['backup_file']) ?></div>
                        <div class="text-xs text-slate-500 mt-1"><?= number_format($b['size_bytes'] / 1024 / 1024, 2) ?> MB</div>
                    </td>
                    <td class="p-4">
                        <div class="font-bold <?= $statusColor ?> mb-1"><?= htmlspecialchars($b['status']) ?></div>
                        <?php if($b['error_message']): ?>
                            <div class="text-[10px] text-red-400 truncate max-w-[200px]" title="<?= htmlspecialchars($b['error_message']) ?>"><?= htmlspecialchars($b['error_message']) ?></div>
                        <?php endif; ?>
                        <?php if($b['restore_test_status']): ?>
                            <div class="text-[10px] text-slate-400 mt-1">Test: <?= htmlspecialchars($b['restore_test_status']) ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
