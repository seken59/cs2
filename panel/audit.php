<?php
// panel/audit.php
require_once __DIR__ . '/layout.php';

$logs = $db->query("SELECT * FROM admin_audit_log ORDER BY created_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);

render_header('Audit Log (İşlem Kayıtları)');
?>

<div class="glass-panel rounded-2xl overflow-hidden">
    <div class="p-6 border-b border-white/5 flex justify-between items-center">
        <h2 class="text-xl font-semibold">Admin Sistem İşlem Kayıtları</h2>
        <div class="text-sm text-slate-400">Son 200 İşlem</div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-sm">
            <thead>
                <tr class="bg-white/5 text-slate-400 uppercase tracking-wider">
                    <th class="p-3 font-medium">Tarih</th>
                    <th class="p-3 font-medium">Admin / IP</th>
                    <th class="p-3 font-medium">İşlem (Action)</th>
                    <th class="p-3 font-medium">Hedef</th>
                    <th class="p-3 font-medium w-1/4">Detay (JSON)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                <?php if (count($logs) === 0): ?>
                <tr><td colspan="5" class="p-8 text-center text-slate-500">Henüz kayıt bulunmuyor.</td></tr>
                <?php endif; ?>
                
                <?php foreach($logs as $log): 
                    $actionColor = 'text-blue-400';
                    if (strpos($log['action'], 'delete') !== false || strpos($log['action'], 'stop') !== false || strpos($log['action'], 'abort') !== false) {
                        $actionColor = 'text-red-400';
                    } elseif (strpos($log['action'], 'add') !== false || strpos($log['action'], 'success') !== false) {
                        $actionColor = 'text-green-400';
                    } elseif (strpos($log['action'], 'update') !== false || strpos($log['action'], 'reset') !== false) {
                        $actionColor = 'text-yellow-400';
                    }
                ?>
                <tr class="hover:bg-white/[0.02] transition-colors">
                    <td class="p-3 text-xs text-slate-400 whitespace-nowrap">
                        <?= $log['created_at'] ?>
                    </td>
                    <td class="p-3">
                        <div class="font-bold text-slate-200"><?= htmlspecialchars($log['admin_user']) ?></div>
                        <div class="text-[10px] text-slate-500 font-mono"><?= htmlspecialchars($log['ip_address']) ?></div>
                    </td>
                    <td class="p-3 font-bold <?= $actionColor ?>">
                        <?= htmlspecialchars($log['action']) ?>
                    </td>
                    <td class="p-3 text-slate-300">
                        <?php if($log['target_type']): ?>
                            <span class="px-2 py-0.5 bg-white/5 rounded text-xs border border-white/10"><?= htmlspecialchars($log['target_type']) ?></span>
                            <?php if($log['target_id']): ?>
                                <span class="text-xs text-slate-400 ml-1">ID: <?= htmlspecialchars($log['target_id']) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-slate-600">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3">
                        <?php if($log['details'] && $log['details'] !== 'null'): ?>
                            <div class="bg-slate-900 p-2 rounded text-[10px] font-mono text-slate-400 overflow-x-auto max-h-20 border border-slate-800">
                                <?= htmlspecialchars($log['details']) ?>
                            </div>
                        <?php else: ?>
                            <span class="text-slate-600">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
