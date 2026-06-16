<?php
// panel/settings.php
require_once __DIR__ . '/layout.php';

// Check if POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_settings') {
        // Toggle (checkbox) formlarinda, secili olmayan checkboxlar POST edilmez.
        // Veritabanindaki tum ayarlari cekip, eger POST'ta yoksa ve boolean bir alansa 0 yapmaliyiz.
        $all_keys = $db->query("SELECT setting_key FROM system_settings")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($all_keys as $key) {
            $value = $_POST['settings'][$key] ?? null;
            
            // Eger deger null gelmisse, ve checkbox (boolean) olabilecek bir alansa onu 0 kabul et
            if ($value === null && (strpos($key, '_enabled') !== false || strpos($key, '_mode') !== false || strpos($key, '_notifications') !== false || strpos($key, 'auto_') !== false)) {
                $value = '0';
            }
            
            if ($value !== null) {
                $stmt = $db->prepare("UPDATE system_settings SET setting_value = ?, last_updated_by = ?, updated_at = NOW() WHERE setting_key = ?");
                $stmt->execute([$value, $_SESSION['admin_username'] ?? 'admin', $key]);
            }
        }
        
        audit_log('settings_update', 'system_settings', null, $_POST['settings'] ?? []);
        $success_msg = "Ayarlar başarıyla güncellendi.";
    }
}

// Tüm ayarları çek
$settingsData = $db->query("SELECT * FROM system_settings")->fetchAll(PDO::FETCH_ASSOC);
$settings = [];
foreach($settingsData as $row) {
    $settings[$row['setting_key']] = $row;
}

// Kategorizasyon
$groups = [
    'Sistem & Güvenlik' => ['allowed_ips', 'maintenance_mode'],
    'Bildirim & Görevler' => ['telegram_notifications', 'auto_retry_failed_actions', 'max_action_retries'],
    'Performans & Çalışma' => ['batch_size', 'max_parallel_batches', 'backup_enabled']
];

render_header('Sistem Ayarları');
?>

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-white">Sistem ve Bildirim Ayarları</h2>
        <span class="text-sm text-slate-400">Tüm sistem kurallarını buradan yapılandırabilirsiniz.</span>
    </div>
    
    <?php if(!empty($success_msg)): ?>
        <div class="bg-green-500/20 border border-green-500 text-green-300 p-4 rounded-xl shadow-lg flex items-center">
            <i class="fas fa-check-circle mr-3 text-xl"></i> <?= htmlspecialchars($success_msg) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="settings.php" id="settingsForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="update_settings">
        
        <div class="grid grid-cols-1 gap-8">
            <?php foreach($groups as $groupName => $keys): ?>
                <div class="glass-panel p-6 rounded-2xl">
                    <h3 class="text-lg font-semibold text-primary mb-4 pb-2 border-b border-white/5">
                        <i class="fas fa-sliders-h mr-2"></i> <?= htmlspecialchars($groupName) ?>
                    </h3>
                    
                    <div class="space-y-6">
                        <?php foreach($keys as $key): 
                            if(!isset($settings[$key])) continue;
                            $setting = $settings[$key];
                            $isBoolean = (strpos($key, '_enabled') !== false || strpos($key, '_mode') !== false || strpos($key, '_notifications') !== false || strpos($key, 'auto_') !== false);
                            $val = $setting['setting_value'];
                            $title = ucwords(str_replace('_', ' ', $key));
                        ?>
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 p-4 bg-white/5 rounded-xl border border-white/5 hover:bg-white/[0.07] transition-colors">
                                <div class="flex-1">
                                    <label class="block text-sm font-bold text-slate-200 mb-1">
                                        <?= htmlspecialchars($title) ?>
                                    </label>
                                    <p class="text-xs text-slate-400"><?= htmlspecialchars($setting['description']) ?></p>
                                    
                                    <?php if($setting['updated_at']): ?>
                                        <div class="text-[10px] text-slate-500 mt-2 flex items-center">
                                            <i class="fas fa-clock mr-1"></i> <?= htmlspecialchars($setting['updated_at']) ?> - <?= htmlspecialchars($setting['last_updated_by']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="w-full md:w-auto min-w-[200px]">
                                    <?php if($isBoolean): ?>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="settings[<?= htmlspecialchars($key) ?>]" value="1" <?= $val == '1' ? 'checked' : '' ?> class="sr-only peer">
                                            <div class="w-14 h-7 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-primary"></div>
                                            <span class="ml-3 text-sm font-medium text-slate-300 peer-checked:text-primary transition-colors">
                                                <?= $val == '1' ? 'Açık' : 'Kapalı' ?>
                                            </span>
                                        </label>
                                    <?php else: ?>
                                        <input type="text" name="settings[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars($val) ?>" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-primary transition-colors text-sm">
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-8 flex justify-end sticky bottom-6 z-10">
            <button type="submit" class="bg-gradient-to-r from-primary to-blue-600 hover:from-blue-600 hover:to-primary text-white px-8 py-3 rounded-xl font-bold transition-all shadow-lg shadow-primary/20 flex items-center gap-2 w-full md:w-auto justify-center">
                <i class="fas fa-save"></i> Ayarları Kaydet ve Uygula
            </button>
        </div>
    </form>
</div>

<?php render_footer(); ?>
