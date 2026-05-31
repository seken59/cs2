<?php
// panel/workers.php
require_once __DIR__ . '/layout.php';

$workers = $db->query("SELECT * FROM worker_heartbeats ORDER BY heartbeat_at DESC")->fetchAll(PDO::FETCH_ASSOC);

render_header('Worker Yönetimi');
?>

<div class="glass-panel rounded-2xl overflow-hidden">
    <div class="p-6 border-b border-white/5 flex justify-between items-center">
        <h2 class="text-xl font-semibold">Aktif Worker'lar (Host/Node)</h2>
        <button onclick="location.reload()" class="text-slate-400 hover:text-white transition-colors"><i class="fas fa-sync-alt"></i> Yenile</button>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-white/5 text-slate-400 text-sm uppercase tracking-wider">
                    <th class="p-4 font-medium">Worker Name</th>
                    <th class="p-4 font-medium">Tip / Versiyon</th>
                    <th class="p-4 font-medium">Durum (Heartbeat)</th>
                    <th class="p-4 font-medium">Son Hata</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                <?php if (count($workers) === 0): ?>
                <tr><td colspan="4" class="p-8 text-center text-slate-500">Hiçbir worker heartbeat göndermemiş.</td></tr>
                <?php endif; ?>
                
                <?php foreach($workers as $w): 
                    $heartbeatTime = strtotime($w['heartbeat_at']);
                    $diffMinutes = (time() - $heartbeatTime) / 60;
                    
                    $statusColor = 'text-green-400';
                    $statusBg = 'bg-green-500/20';
                    $statusText = 'OK';
                    
                    if ($diffMinutes > 5) {
                        $statusColor = 'text-red-400';
                        $statusBg = 'bg-red-500/20';
                        $statusText = 'CRITICAL (ÖLÜ)';
                    } elseif ($diffMinutes > 2) {
                        $statusColor = 'text-yellow-400';
                        $statusBg = 'bg-yellow-500/20';
                        $statusText = 'WARNING (GECİKME)';
                    }
                ?>
                <tr class="hover:bg-white/[0.02] transition-colors">
                    <td class="p-4 font-bold text-slate-200">
                        <i class="fas fa-robot text-slate-500 mr-2"></i><?= htmlspecialchars($w['worker_name']) ?>
                    </td>
                    <td class="p-4">
                        <div class="text-sm font-semibold text-slate-300"><?= htmlspecialchars($w['worker_type']) ?></div>
                        <div class="text-xs text-slate-500 font-mono mt-1"><?= htmlspecialchars($w['version']) ?> (<?= htmlspecialchars($w['commit_hash']) ?>)</div>
                    </td>
                    <td class="p-4">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $statusColor ?> <?= $statusBg ?>">
                            <?= $statusText ?>
                        </span>
                        <div class="text-[10px] text-slate-500 mt-2"><i class="fas fa-heartbeat text-red-500/50"></i> <?= $w['heartbeat_at'] ?></div>
                    </td>
                    <td class="p-4 text-xs">
                        <?php if($w['last_error']): ?>
                            <span class="text-red-400 truncate block max-w-[250px]" title="<?= htmlspecialchars($w['last_error']) ?>"><?= htmlspecialchars($w['last_error']) ?></span>
                        <?php else: ?>
                            <span class="text-slate-500">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
