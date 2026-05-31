<?php
// panel/alerts.php
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? 0;
    
    if ($action === 'ack' && $id) {
        $stmt = $db->prepare("UPDATE system_alerts SET status = 'ACKED', acknowledged_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        audit_log('alert_ack', 'system_alerts', $id);
    } elseif ($action === 'resolve' && $id) {
        $stmt = $db->prepare("UPDATE system_alerts SET status = 'RESOLVED', resolved_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        audit_log('alert_resolve', 'system_alerts', $id);
    } elseif ($action === 'reopen' && $id) {
        $stmt = $db->prepare("UPDATE system_alerts SET status = 'OPEN', acknowledged_at = NULL, resolved_at = NULL WHERE id = ?");
        $stmt->execute([$id]);
        audit_log('alert_reopen', 'system_alerts', $id);
    }
}

$statusFilter = $_GET['status'] ?? 'OPEN';
$where = "WHERE 1=1";
$params = [];
if ($statusFilter !== 'ALL') {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}

$alerts = $db->prepare("SELECT * FROM system_alerts $where ORDER BY created_at DESC LIMIT 100");
$alerts->execute($params);
$alerts = $alerts->fetchAll(PDO::FETCH_ASSOC);

render_header('Sistem Alarmları');
?>

<div class="glass-panel rounded-2xl overflow-hidden">
    <div class="p-6 border-b border-white/5 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <h2 class="text-xl font-semibold">Aktif Alarmlar</h2>
        
        <div class="flex space-x-2 overflow-x-auto pb-2 w-full md:w-auto">
            <a href="alerts.php?status=ALL" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $statusFilter==='ALL' ? 'bg-primary text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">Tümü</a>
            <a href="alerts.php?status=OPEN" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $statusFilter==='OPEN' ? 'bg-red-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">OPEN</a>
            <a href="alerts.php?status=ACKED" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $statusFilter==='ACKED' ? 'bg-yellow-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">ACKED</a>
            <a href="alerts.php?status=RESOLVED" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $statusFilter==='RESOLVED' ? 'bg-green-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">RESOLVED</a>
        </div>
    </div>
    
    <div class="p-6">
        <div class="space-y-4">
            <?php if (count($alerts) === 0): ?>
                <div class="text-center p-8 text-slate-500 bg-white/5 rounded-xl border border-white/5">
                    Şu an gösterilecek alarm bulunmuyor. Her şey yolunda!
                </div>
            <?php endif; ?>

            <?php foreach($alerts as $al): 
                $icon = 'fa-info-circle text-blue-400';
                $border = 'border-blue-500/30';
                $bg = 'bg-blue-500/5';
                
                if ($al['severity'] === 'WARNING') {
                    $icon = 'fa-exclamation-triangle text-yellow-400';
                    $border = 'border-yellow-500/30';
                    $bg = 'bg-yellow-500/5';
                } elseif ($al['severity'] === 'CRITICAL') {
                    $icon = 'fa-times-circle text-red-500';
                    $border = 'border-red-500/30';
                    $bg = 'bg-red-500/5';
                }
                
                if ($al['status'] === 'RESOLVED') {
                    $border = 'border-white/10';
                    $bg = 'bg-white/5';
                    $icon = 'fa-check-circle text-green-500';
                }
            ?>
            <div class="p-4 rounded-xl border <?= $border ?> <?= $bg ?> flex flex-col md:flex-row justify-between items-start md:items-center gap-4 transition-all hover:bg-white/10">
                <div class="flex items-start gap-4">
                    <i class="fas <?= $icon ?> text-2xl mt-1"></i>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-bold text-white"><?= htmlspecialchars($al['alert_type']) ?></span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 border border-white/10 text-slate-300"><?= $al['status'] ?></span>
                        </div>
                        <p class="text-sm text-slate-300 mb-2"><?= htmlspecialchars($al['message']) ?></p>
                        <div class="text-[10px] text-slate-500 flex gap-4">
                            <span><i class="fas fa-clock mr-1"></i> <?= $al['created_at'] ?></span>
                            <?php if($al['related_entity_type']): ?>
                                <span><i class="fas fa-link mr-1"></i> <?= htmlspecialchars($al['related_entity_type']) ?>: <?= htmlspecialchars($al['related_entity_id']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <?php if($al['status'] === 'OPEN'): ?>
                        <button onclick="alertAction('ack', <?= $al['id'] ?>)" class="px-4 py-2 bg-yellow-500/20 hover:bg-yellow-500/40 text-yellow-400 rounded-lg text-sm font-medium transition-colors border border-yellow-500/20">ACK</button>
                    <?php endif; ?>
                    
                    <?php if(in_array($al['status'], ['OPEN', 'ACKED'])): ?>
                        <button onclick="alertAction('resolve', <?= $al['id'] ?>)" class="px-4 py-2 bg-green-500/20 hover:bg-green-500/40 text-green-400 rounded-lg text-sm font-medium transition-colors border border-green-500/20">Resolve</button>
                    <?php endif; ?>
                    
                    <?php if($al['status'] === 'RESOLVED'): ?>
                        <button onclick="alertAction('reopen', <?= $al['id'] ?>)" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-sm font-medium transition-colors border border-white/10">Reopen</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<form id="alertForm" method="POST" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" id="a_action">
    <input type="hidden" name="id" id="a_id">
</form>

<script>
function alertAction(action, id) {
    document.getElementById('a_action').value = action;
    document.getElementById('a_id').value = id;
    document.getElementById('alertForm').submit();
}
</script>

<?php render_footer(); ?>
