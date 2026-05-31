<?php
// panel/dashboard.php
require_once __DIR__ . '/layout.php';

$stats = [
    'total_accounts' => $db->query("SELECT COUNT(*) FROM accounts")->fetchColumn(),
    'farming_accounts' => $db->query("SELECT COUNT(*) FROM accounts WHERE status = 'FARMING'")->fetchColumn(),
    'dropped_accounts' => $db->query("SELECT COUNT(*) FROM accounts WHERE status = 'DROPPED'")->fetchColumn(),
    'idle_accounts' => $db->query("SELECT COUNT(*) FROM accounts WHERE status = 'IDLE'")->fetchColumn(),
    'active_batches' => $db->query("SELECT COUNT(*) FROM farm_batches WHERE status = 'RUNNING'")->fetchColumn(),
    'pending_actions' => $db->query("SELECT COUNT(*) FROM action_queue WHERE status = 'PENDING'")->fetchColumn(),
    'failed_actions' => $db->query("SELECT COUNT(*) FROM action_queue WHERE status = 'FAILED'")->fetchColumn(),
    'open_alerts' => $db->query("SELECT COUNT(*) FROM system_alerts WHERE status = 'OPEN' AND severity = 'CRITICAL'")->fetchColumn()
];

$recent_actions = $db->query("SELECT * FROM action_queue ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$recent_alerts = $db->query("SELECT * FROM system_alerts ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

render_header('NOC Dashboard');
?>

<!-- Stats Grid -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="glass-panel p-4 rounded-xl border-l-4 border-blue-500">
        <div class="text-xs text-slate-400 uppercase tracking-wider mb-1">Toplam Hesap</div>
        <div class="text-2xl font-bold text-white"><?= $stats['total_accounts'] ?></div>
        <div class="text-xs text-slate-500 mt-1">IDLE: <?= $stats['idle_accounts'] ?></div>
    </div>
    
    <div class="glass-panel p-4 rounded-xl border-l-4 border-purple-500">
        <div class="text-xs text-slate-400 uppercase tracking-wider mb-1">Farming Hesap</div>
        <div class="text-2xl font-bold text-purple-400"><?= $stats['farming_accounts'] ?></div>
        <div class="text-xs text-slate-500 mt-1">Düşüren: <?= $stats['dropped_accounts'] ?></div>
    </div>
    
    <div class="glass-panel p-4 rounded-xl border-l-4 border-indigo-500">
        <div class="text-xs text-slate-400 uppercase tracking-wider mb-1">Aktif Batch</div>
        <div class="text-2xl font-bold text-indigo-400"><?= $stats['active_batches'] ?></div>
        <div class="text-xs text-slate-500 mt-1">Çalışan container grupları</div>
    </div>

    <div class="glass-panel p-4 rounded-xl border-l-4 <?= $stats['open_alerts'] > 0 ? 'border-red-500' : 'border-green-500' ?>">
        <div class="text-xs text-slate-400 uppercase tracking-wider mb-1">Kritik Alarmlar</div>
        <div class="text-2xl font-bold <?= $stats['open_alerts'] > 0 ? 'text-red-400' : 'text-green-400' ?>"><?= $stats['open_alerts'] ?></div>
        <div class="text-xs text-slate-500 mt-1">Açık durumda</div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Action Queue Overview -->
    <div class="glass-panel rounded-2xl overflow-hidden flex flex-col">
        <div class="p-4 border-b border-white/5 flex justify-between items-center bg-white/[0.02]">
            <h3 class="font-semibold text-slate-200"><i class="fas fa-tasks text-blue-400 mr-2"></i> Action Queue Durumu</h3>
            <div class="flex space-x-2 text-xs">
                <span class="px-2 py-1 bg-blue-500/20 text-blue-400 rounded">Pending: <?= $stats['pending_actions'] ?></span>
                <span class="px-2 py-1 bg-red-500/20 text-red-400 rounded">Failed: <?= $stats['failed_actions'] ?></span>
            </div>
        </div>
        <div class="p-0 flex-1">
            <ul class="divide-y divide-white/5">
                <?php if(!$recent_actions): ?>
                    <li class="p-4 text-center text-sm text-slate-500">Son işlem bulunmuyor.</li>
                <?php endif; ?>
                <?php foreach($recent_actions as $ac): ?>
                    <li class="p-4 hover:bg-white/[0.02] transition-colors">
                        <div class="flex justify-between mb-1">
                            <span class="font-mono text-sm font-bold text-slate-300"><?= htmlspecialchars($ac['action_type']) ?></span>
                            <span class="text-xs px-2 py-0.5 rounded-full <?= $ac['status']=='PENDING'?'bg-blue-500/20 text-blue-400':($ac['status']=='FAILED'?'bg-red-500/20 text-red-400':'bg-slate-800 text-slate-400') ?>"><?= $ac['status'] ?></span>
                        </div>
                        <div class="text-xs text-slate-500 truncate"><?= htmlspecialchars($ac['payload']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="p-3 border-t border-white/5 text-center bg-white/[0.02]">
            <a href="action_queue.php" class="text-sm text-primary hover:text-blue-400 font-medium">Tümünü Gör <i class="fas fa-arrow-right text-xs ml-1"></i></a>
        </div>
    </div>

    <!-- System Alerts Overview -->
    <div class="glass-panel rounded-2xl overflow-hidden flex flex-col">
        <div class="p-4 border-b border-white/5 flex justify-between items-center bg-white/[0.02]">
            <h3 class="font-semibold text-slate-200"><i class="fas fa-bell text-yellow-400 mr-2"></i> Son Alarmlar</h3>
        </div>
        <div class="p-0 flex-1">
            <ul class="divide-y divide-white/5">
                <?php if(!$recent_alerts): ?>
                    <li class="p-4 text-center text-sm text-slate-500">Sistem sağlıklı, alarm yok.</li>
                <?php endif; ?>
                <?php foreach($recent_alerts as $al): ?>
                    <li class="p-4 hover:bg-white/[0.02] transition-colors">
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-bold <?= $al['severity']=='CRITICAL'?'text-red-400':($al['severity']=='WARNING'?'text-yellow-400':'text-blue-400') ?>">
                                <?= htmlspecialchars($al['alert_type']) ?>
                            </span>
                            <span class="text-xs text-slate-500"><?= $al['created_at'] ?></span>
                        </div>
                        <div class="text-xs text-slate-300 truncate"><?= htmlspecialchars($al['message']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="p-3 border-t border-white/5 text-center bg-white/[0.02]">
            <a href="alerts.php" class="text-sm text-primary hover:text-blue-400 font-medium">Tümünü Gör <i class="fas fa-arrow-right text-xs ml-1"></i></a>
        </div>
    </div>
</div>

<?php render_footer(); ?>
