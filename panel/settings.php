<?php
// panel/settings.php
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_settings') {
        foreach ($_POST['settings'] ?? [] as $key => $value) {
            $stmt = $db->prepare("UPDATE system_settings SET setting_value = ?, last_updated_by = ?, updated_at = NOW() WHERE setting_key = ?");
            $stmt->execute([$value, $_SESSION['admin_username'] ?? 'admin', $key]);
        }
        audit_log('settings_update', 'system_settings', null, $_POST['settings']);
        $success_msg = "Ayarlar başarıyla güncellendi.";
    }
}

$settings = $db->query("SELECT * FROM system_settings")->fetchAll(PDO::FETCH_ASSOC);

render_header('Sistem Ayarları');
?>

<div class="glass-panel p-8 rounded-2xl max-w-4xl">
    <h2 class="text-2xl font-bold mb-6 text-white">Genel Ayarlar</h2>
    
    <?php if(!empty($success_msg)): ?>
        <div class="bg-green-500/20 border border-green-500 text-green-300 p-3 rounded mb-6">
            <i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="settings.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="update_settings">
        
        <div class="space-y-6">
            <?php foreach($settings as $setting): ?>
                <div class="bg-white/5 p-4 rounded-xl border border-white/5">
                    <label class="block text-sm font-bold text-slate-200 mb-1">
                        <?= htmlspecialchars($setting['setting_key']) ?>
                    </label>
                    <p class="text-xs text-slate-400 mb-3"><?= htmlspecialchars($setting['description']) ?></p>
                    <input type="text" name="settings[<?= htmlspecialchars($setting['setting_key']) ?>]" value="<?= htmlspecialchars($setting['setting_value']) ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-primary transition-colors">
                    
                    <?php if($setting['updated_at']): ?>
                        <div class="text-[10px] text-slate-500 mt-2 flex items-center">
                            <i class="fas fa-clock mr-1"></i> Son güncelleme: <?= htmlspecialchars($setting['updated_at']) ?> - <?= htmlspecialchars($setting['last_updated_by']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="mt-8 bg-gradient-to-r from-primary to-blue-600 hover:from-blue-600 hover:to-primary text-white px-8 py-3 rounded-xl font-bold transition-all shadow-lg w-full md:w-auto">
            <i class="fas fa-save mr-2"></i> Ayarları Kaydet
        </button>
    </form>
</div>

<?php render_footer(); ?>
