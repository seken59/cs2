<?php
// panel/maintenance.php
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'enable') {
        enqueue_action('ENABLE_MAINTENANCE', []);
        audit_log('maintenance_enable_request');
        $msg = "Bakım modu etkinleştirme talebi kuyruğa alındı. (ID: " . $db->lastInsertId() . ")";
    } elseif ($action === 'disable') {
        enqueue_action('DISABLE_MAINTENANCE', []);
        audit_log('maintenance_disable_request');
        $msg = "Bakım modunu kapatma talebi kuyruğa alındı. (ID: " . $db->lastInsertId() . ")";
    } elseif ($action === 'drain') {
        enqueue_action('DRAIN_BATCHES', []);
        audit_log('maintenance_drain_request');
        $msg = "Drain işlemi başlatıldı. Mevcut batchler tamamlanana kadar beklenecek. (ID: " . $db->lastInsertId() . ")";
    } elseif ($action === 'stop_active') {
        enqueue_action('STOP_ACTIVE_BATCHES', []);
        audit_log('maintenance_stop_active_request');
        $msg = "Tüm aktif batchleri durdurma (Graceful Stop) talebi alındı. (ID: " . $db->lastInsertId() . ")";
    } elseif ($action === 'check_overlay') {
        enqueue_action('CHECK_OVERLAY_MOUNTS', []);
        audit_log('maintenance_check_overlay_request');
        $msg = "Overlay mount health check talebi oluşturuldu. (ID: " . $db->lastInsertId() . ")";
    }
}

$stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'");
$res = $stmt->fetch(PDO::FETCH_ASSOC);
$isMaintenance = ($res && $res['setting_value'] == '1');

$activeBatches = $db->query("SELECT COUNT(*) FROM farm_batches WHERE status = 'RUNNING'")->fetchColumn();

$lastAction = $db->query("SELECT action_type, status, last_error FROM action_queue WHERE action_type IN ('ENABLE_MAINTENANCE', 'DISABLE_MAINTENANCE', 'DRAIN_BATCHES', 'STOP_ACTIVE_BATCHES') ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

render_header('Bakım Yönetimi');
?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Durum Paneli -->
    <div class="glass-panel rounded-2xl overflow-hidden p-6">
        <h2 class="text-xl font-semibold mb-6">Mevcut Durum</h2>
        
        <?php if(isset($msg)): ?>
            <div class="mb-6 bg-blue-500/20 border border-blue-500 text-blue-300 p-3 rounded">
                <i class="fas fa-info-circle mr-2"></i> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if($lastAction): ?>
            <div class="mb-6 p-4 rounded-xl border <?= $lastAction['status'] === 'FAILED' ? 'border-red-500/30 bg-red-500/5 text-red-400' : ($lastAction['status'] === 'COMPLETED' ? 'border-green-500/30 bg-green-500/5 text-green-400' : 'border-blue-500/30 bg-blue-500/5 text-blue-400') ?>">
                <div class="text-xs text-slate-400 mb-1">Son Operasyon:</div>
                <div class="font-bold mb-1"><?= htmlspecialchars($lastAction['action_type']) ?> - <?= htmlspecialchars($lastAction['status']) ?></div>
                <?php if($lastAction['last_error']): ?>
                    <div class="text-[10px] opacity-80"><?= htmlspecialchars($lastAction['last_error']) ?></div>
                <?php endif; ?>
                <?php if($lastAction['status'] === 'PROCESSING'): ?>
                    <div class="text-[10px] text-yellow-400 mt-2"><i class="fas fa-exclamation-triangle"></i> İşlem devam ediyor. Uzun sürerse Host Worker loglarını kontrol edin.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="space-y-4">
            <div class="flex justify-between items-center p-4 bg-white/5 rounded-xl border border-white/5">
                <div>
                    <div class="text-sm text-slate-400">Bakım Modu (Maintenance Mode)</div>
                    <div class="text-lg font-bold <?= $isMaintenance ? 'text-yellow-400' : 'text-green-400' ?>">
                        <?= $isMaintenance ? 'AKTİF' : 'KAPALI' ?>
                    </div>
                </div>
                <div>
                    <?php if($isMaintenance): ?>
                        <i class="fas fa-tools text-3xl text-yellow-400"></i>
                    <?php else: ?>
                        <i class="fas fa-check-circle text-3xl text-green-400"></i>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="flex justify-between items-center p-4 bg-white/5 rounded-xl border border-white/5">
                <div>
                    <div class="text-sm text-slate-400">Aktif Çalışan Batch Sayısı</div>
                    <div class="text-lg font-bold text-blue-400"><?= $activeBatches ?></div>
                </div>
                <i class="fas fa-layer-group text-3xl text-blue-400"></i>
            </div>
        </div>
    </div>

    <!-- Aksiyon Paneli -->
    <div class="glass-panel rounded-2xl overflow-hidden p-6">
        <h2 class="text-xl font-semibold mb-6">Operasyon Kontrolü</h2>
        
        <form id="maintenanceForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" id="m_action">
            
            <div class="space-y-3">
                <?php if($isMaintenance): ?>
                    <button type="button" onclick="mAction('disable')" class="w-full bg-green-600 hover:bg-green-500 text-white font-bold py-3 px-4 rounded-xl transition-all shadow-lg text-left flex items-center justify-between group">
                        <span><i class="fas fa-play mr-2"></i> Bakım Modunu Kapat (Sistemi Aç)</span>
                        <i class="fas fa-chevron-right opacity-50 group-hover:opacity-100 transition-opacity"></i>
                    </button>
                <?php else: ?>
                    <button type="button" onclick="mAction('enable')" class="w-full bg-yellow-600 hover:bg-yellow-500 text-white font-bold py-3 px-4 rounded-xl transition-all shadow-lg text-left flex items-center justify-between group">
                        <span><i class="fas fa-tools mr-2"></i> Bakım Modunu Başlat (Sistemi Durdur)</span>
                        <i class="fas fa-chevron-right opacity-50 group-hover:opacity-100 transition-opacity"></i>
                    </button>
                <?php endif; ?>
                
                <hr class="border-white/10 my-4">

                <button type="button" onclick="mAction('drain')" class="w-full bg-slate-800 border border-white/10 hover:bg-slate-700 text-white font-medium py-3 px-4 rounded-xl transition-all text-left flex justify-between items-center group">
                    <span><i class="fas fa-hourglass-half text-blue-400 mr-2"></i> Drain Başlat (Mevcutları bekle, yeni açma)</span>
                    <i class="fas fa-chevron-right opacity-50 group-hover:opacity-100 transition-opacity"></i>
                </button>

                <button type="button" onclick="mAction('stop_active')" class="w-full bg-slate-800 border border-white/10 hover:bg-slate-700 text-white font-medium py-3 px-4 rounded-xl transition-all text-left flex justify-between items-center group">
                    <span><i class="fas fa-stop text-red-400 mr-2"></i> Tüm Aktif Batchleri Durdur (Graceful)</span>
                    <i class="fas fa-chevron-right opacity-50 group-hover:opacity-100 transition-opacity"></i>
                </button>

                <button type="button" onclick="mAction('check_overlay')" class="w-full bg-slate-800 border border-white/10 hover:bg-slate-700 text-white font-medium py-3 px-4 rounded-xl transition-all text-left flex justify-between items-center group">
                    <span><i class="fas fa-hdd text-purple-400 mr-2"></i> Overlay Mount Health Check İste</span>
                    <i class="fas fa-chevron-right opacity-50 group-hover:opacity-100 transition-opacity"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function mAction(action) {
    let confirmMsg = 'Bu işlemi onaylıyor musunuz?';
    if(action === 'enable') confirmMsg = 'Bakım modu açılacak. Yeni batch başlatılmayacak. Onaylıyor musunuz?';
    if(action === 'stop_active') confirmMsg = 'Tüm aktif batchler durdurulacak! Emin misiniz?';
    
    if(!confirm(confirmMsg)) return;
    document.getElementById('m_action').value = action;
    document.getElementById('maintenanceForm').submit();
}
</script>

<?php render_footer(); ?>
