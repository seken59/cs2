<?php
// panel/health.php
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'collect_health') {
    enqueue_action('COLLECT_SYSTEM_HEALTH', []);
    audit_log('health_check_request');
    $msg = "Sistem sağlığı analizi talebi (Collect System Health) kuyruğa eklendi. Sonuçlar birazdan güncellenecektir.";
}

// En güncel metricleri alalım
$healthData = $db->query("
    SELECT t1.* FROM system_health t1
    JOIN (SELECT metric_name, MAX(collected_at) as max_date FROM system_health GROUP BY metric_name) t2
    ON t1.metric_name = t2.metric_name AND t1.collected_at = t2.max_date
")->fetchAll(PDO::FETCH_ASSOC);

render_header('Sistem Sağlığı');
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-white">Sistem Sağlık Raporu</h2>
        <p class="text-slate-400 text-sm mt-1">Worker tarafından toplanan son sistem metrikleri</p>
    </div>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="collect_health">
        <button type="submit" class="bg-primary hover:bg-blue-600 text-white px-6 py-2 rounded-xl font-medium transition-all flex items-center">
            <i class="fas fa-sync-alt mr-2"></i> Yeni Analiz İste
        </button>
    </form>
</div>

<?php if(isset($msg)): ?>
    <div class="mb-6 bg-blue-500/20 border border-blue-500 text-blue-300 p-4 rounded-xl flex items-center">
        <i class="fas fa-info-circle text-xl mr-3"></i> <?= htmlspecialchars($msg) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php if (count($healthData) === 0): ?>
        <div class="col-span-full glass-panel p-8 rounded-2xl text-center text-slate-500">
            <i class="fas fa-heartbeat text-4xl mb-4 opacity-50"></i>
            <p>Henüz sağlık verisi toplanmamış.</p>
        </div>
    <?php endif; ?>

    <?php foreach($healthData as $h): 
        $statusColor = 'text-green-400';
        $border = 'border-green-500/30';
        $bg = 'bg-green-500/5';
        $icon = 'fa-check-circle';
        
        if ($h['status'] === 'WARNING') {
            $statusColor = 'text-yellow-400';
            $border = 'border-yellow-500/30';
            $bg = 'bg-yellow-500/5';
            $icon = 'fa-exclamation-triangle';
        } elseif ($h['status'] === 'CRITICAL') {
            $statusColor = 'text-red-400';
            $border = 'border-red-500/30';
            $bg = 'bg-red-500/5';
            $icon = 'fa-times-circle';
        }
    ?>
    <div class="glass-panel p-6 rounded-2xl border <?= $border ?> <?= $bg ?>">
        <div class="flex justify-between items-start mb-4">
            <div class="font-bold text-slate-200"><?= htmlspecialchars($h['metric_name']) ?></div>
            <i class="fas <?= $icon ?> <?= $statusColor ?> text-xl"></i>
        </div>
        <div class="text-2xl font-semibold <?= $statusColor ?> mb-2">
            <?= htmlspecialchars($h['metric_value']) ?>
        </div>
        <div class="text-[10px] text-slate-500 flex justify-between">
            <span>Durum: <?= $h['status'] ?></span>
            <span><i class="fas fa-clock"></i> <?= $h['collected_at'] ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php render_footer(); ?>
