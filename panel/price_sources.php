<?php
require_once 'core.php';
$currentPage = 'price_sources.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'flush_cache') {
        $db->exec("TRUNCATE TABLE item_price_cache");
        audit_log('price_cache_flushed');
        $msg = "Fiyat önbelleği temizlendi.";
    } elseif ($action === 'refresh_missing') {
        enqueue_action('REFRESH_ITEM_PRICE', []);
        audit_log('refresh_missing_prices');
        $msg = "Eksik fiyatları yenileme işlemi worker kuyruğuna alındı.";
    }
}

$sources = $db->query("SELECT * FROM price_sources")->fetchAll(PDO::FETCH_ASSOC);

$cacheStats = $db->query("
    SELECT 
        COUNT(*) as total_cached,
        SUM(CASE WHEN expires_at > NOW() THEN 1 ELSE 0 END) as active_cached
    FROM item_price_cache
")->fetch(PDO::FETCH_ASSOC);

render_header('Fiyat Kaynakları');
?>

<div class="p-6 max-w-7xl mx-auto space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Fiyat Kaynakları</h1>
            <p class="text-slate-400 mt-1">CSFloat, Steam Market ve önbellek yönetimi.</p>
        </div>
        <div class="flex space-x-3">
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="refresh_missing">
                <button type="submit" class="bg-blue-600/20 text-blue-400 border border-blue-500/30 px-4 py-2 rounded-lg hover:bg-blue-600/40 transition-colors font-medium">
                    <i class="fas fa-sync-alt mr-2"></i> Eksikleri Yenile
                </button>
            </form>
            <form method="POST" class="inline" onsubmit="return confirm('Tüm cache silinecek. Emin misiniz?');">
                <input type="hidden" name="action" value="flush_cache">
                <button type="submit" class="bg-red-600/20 text-red-400 border border-red-500/30 px-4 py-2 rounded-lg hover:bg-red-600/40 transition-colors font-medium">
                    <i class="fas fa-trash-alt mr-2"></i> Cache Temizle
                </button>
            </form>
        </div>
    </div>

    <?php if(isset($msg)): ?>
        <div class="bg-blue-500/20 border border-blue-500 text-blue-300 p-4 rounded-xl">
            <i class="fas fa-info-circle mr-2"></i> <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Cache Stats -->
        <div class="glass-panel p-6 rounded-2xl border border-white/5">
            <h2 class="text-lg font-bold mb-4">Önbellek (Cache) Durumu</h2>
            <div class="flex justify-between items-center p-4 bg-white/5 rounded-xl border border-white/5 mb-3">
                <div class="text-slate-400">Aktif Cache Kaydı</div>
                <div class="text-xl font-bold text-white"><?= $cacheStats['active_cached'] ?></div>
            </div>
            <div class="flex justify-between items-center p-4 bg-white/5 rounded-xl border border-white/5 mb-3">
                <div class="text-slate-400">Toplam Tutulan Kayıt</div>
                <div class="text-xl font-bold text-white"><?= $cacheStats['total_cached'] ?></div>
            </div>
            <p class="text-xs text-slate-500 mt-2"><i class="fas fa-info-circle"></i> Fiyatlar rate-limit aşımını engellemek için varsayılan 6 saat önbellekte tutulur.</p>
        </div>

        <!-- Source Status -->
        <div class="glass-panel p-6 rounded-2xl border border-white/5">
            <h2 class="text-lg font-bold mb-4">API Kaynakları</h2>
            <div class="space-y-4">
                <?php foreach($sources as $src): ?>
                <div class="p-4 rounded-xl border <?= $src['enabled'] ? 'border-emerald-500/30 bg-emerald-500/5' : 'border-slate-700 bg-white/5' ?>">
                    <div class="flex justify-between items-center mb-2">
                        <div class="font-bold text-white"><?= htmlspecialchars($src['source_name']) ?></div>
                        <div>
                            <?php if($src['enabled']): ?>
                                <span class="px-2 py-1 bg-emerald-500/20 text-emerald-400 text-[10px] uppercase font-bold rounded">Aktif</span>
                            <?php else: ?>
                                <span class="px-2 py-1 bg-slate-500/20 text-slate-400 text-[10px] uppercase font-bold rounded">Devre Dışı</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs text-slate-400">
                        <div>Rate Limit: <span class="text-white"><?= $src['rate_limit_per_minute'] ?>/dk</span></div>
                        <div>ENV Key: <span class="text-white"><?= htmlspecialchars($src['api_key_env_name']) ?></span></div>
                        <div class="col-span-2">
                            Son Başarı: 
                            <span class="<?= $src['last_success_at'] ? 'text-emerald-400' : 'text-slate-500' ?>">
                                <?= $src['last_success_at'] ?: 'Bilinmiyor' ?>
                            </span>
                        </div>
                        <?php if($src['last_error']): ?>
                        <div class="col-span-2 text-red-400 mt-1">
                            Hata: <?= htmlspecialchars($src['last_error']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
