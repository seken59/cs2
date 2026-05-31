<?php
require_once 'core.php';
$currentPage = 'revenue_report.php';

// Fetch aggregations
$thisWeek = $db->query("SELECT SUM(total_estimated_usd) FROM drop_events WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$thisMonth = $db->query("SELECT SUM(total_estimated_usd) FROM drop_events WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

$topItems = $db->query("
    SELECT di.market_hash_name, MAX(di.price_usd) as max_price, COUNT(di.id) as drop_count
    FROM drop_items di
    WHERE di.price_usd > 0
    GROUP BY di.market_hash_name
    ORDER BY max_price DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$topAccounts = $db->query("
    SELECT de.username, SUM(de.total_estimated_usd) as total_earned, COUNT(de.id) as drop_count
    FROM drop_events de
    GROUP BY de.username
    ORDER BY total_earned DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$missingPriceCount = $db->query("
    SELECT COUNT(*) FROM drop_items WHERE price_usd IS NULL OR price_usd = 0
")->fetchColumn();

render_header('Gelir Raporu');
?>

<div class="p-6 max-w-7xl mx-auto space-y-6">
    <div>
        <h1 class="text-3xl font-bold tracking-tight">Gelir Raporu</h1>
        <p class="text-slate-400 mt-1">Sistem genelindeki tahmini kazançlar ve drop değer analizleri.</p>
    </div>

    <!-- Overview Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="glass-panel p-5 rounded-2xl border border-white/5 relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <i class="fas fa-calendar-week text-6xl text-emerald-500"></i>
            </div>
            <div class="text-sm font-medium text-slate-400 mb-1">Bu Hafta (Tahmini)</div>
            <div class="text-3xl font-black text-emerald-400">$<?= number_format($thisWeek ?: 0, 2) ?></div>
        </div>
        
        <div class="glass-panel p-5 rounded-2xl border border-white/5 relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <i class="fas fa-calendar-alt text-6xl text-emerald-500"></i>
            </div>
            <div class="text-sm font-medium text-slate-400 mb-1">Bu Ay (Tahmini)</div>
            <div class="text-3xl font-black text-emerald-400">$<?= number_format($thisMonth ?: 0, 2) ?></div>
        </div>

        <div class="glass-panel p-5 rounded-2xl border border-white/5 relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <i class="fas fa-question-circle text-6xl text-yellow-500"></i>
            </div>
            <div class="text-sm font-medium text-slate-400 mb-1">Fiyatı Bulunamayan Item</div>
            <div class="text-3xl font-black text-yellow-400"><?= $missingPriceCount ?></div>
            <?php if($missingPriceCount > 0): ?>
                <div class="mt-2 text-xs text-yellow-500/70">Kuyruğa gönderildi veya API hatası</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Accounts -->
        <div class="glass-panel p-6 rounded-2xl border border-white/5">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-trophy text-yellow-400 mr-2"></i> En Çok Kazandıran Hesaplar
            </h2>
            <div class="space-y-3">
                <?php foreach($topAccounts as $index => $acc): ?>
                <div class="flex items-center justify-between p-3 rounded-xl bg-white/5 hover:bg-white/10 transition-colors">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-full bg-slate-800 flex items-center justify-center font-bold text-slate-300">
                            <?= $index + 1 ?>
                        </div>
                        <div>
                            <a href="account_details.php?username=<?= urlencode($acc['username']) ?>" class="font-bold text-white hover:text-emerald-400 transition-colors"><?= htmlspecialchars($acc['username']) ?></a>
                            <div class="text-xs text-slate-400"><?= $acc['drop_count'] ?> Drop</div>
                        </div>
                    </div>
                    <div class="font-bold text-emerald-400">
                        $<?= number_format($acc['total_earned'], 2) ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(count($topAccounts)===0): ?>
                    <div class="text-center text-slate-500 py-4">Veri bulunmuyor.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Items -->
        <div class="glass-panel p-6 rounded-2xl border border-white/5">
            <h2 class="text-xl font-bold mb-4 flex items-center">
                <i class="fas fa-gem text-blue-400 mr-2"></i> En Değerli Itemlar (Kayıtlardaki)
            </h2>
            <div class="space-y-3">
                <?php foreach($topItems as $item): ?>
                <div class="flex items-center justify-between p-3 rounded-xl bg-white/5 hover:bg-white/10 transition-colors">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-lg bg-slate-800 flex items-center justify-center text-slate-400">
                            <i class="fas fa-box"></i>
                        </div>
                        <div>
                            <div class="font-bold text-white text-sm"><?= htmlspecialchars($item['market_hash_name']) ?></div>
                            <div class="text-xs text-slate-400"><?= $item['drop_count'] ?> kez düştü</div>
                        </div>
                    </div>
                    <div class="font-bold text-emerald-400">
                        $<?= number_format($item['max_price'], 2) ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(count($topItems)===0): ?>
                    <div class="text-center text-slate-500 py-4">Veri bulunmuyor.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
