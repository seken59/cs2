<?php
require_once 'core.php';
$currentPage = 'accounts.php';

$username = isset($_GET['username']) ? trim($_GET['username']) : '';
if (!$username) {
    header("Location: accounts.php");
    exit;
}

// Fetch Account Info (Assuming there is some accounts table or we just rely on drop events)
// For this demo we'll fetch stats from drop_events
$stats = $db->prepare("
    SELECT COUNT(id) as total_drops, SUM(total_estimated_usd) as total_value 
    FROM drop_events WHERE username = ?
");
$stats->execute([$username]);
$accStats = $stats->fetch(PDO::FETCH_ASSOC);

// Fetch Drops
$dropsStmt = $db->prepare("
    SELECT de.detected_at, di.market_hash_name, di.exterior_tr, di.float_value, di.price_usd, di.price_source
    FROM drop_events de
    LEFT JOIN drop_items di ON de.id = di.drop_event_id
    WHERE de.username = ?
    ORDER BY de.detected_at DESC
");
$dropsStmt->execute([$username]);
$drops = $dropsStmt->fetchAll(PDO::FETCH_ASSOC);

render_header("Hesap Detayı: " . htmlspecialchars($username));
?>

<div class="p-6 max-w-7xl mx-auto space-y-6">
    <div class="flex items-center space-x-4 mb-6">
        <a href="accounts.php" class="w-10 h-10 rounded-full bg-white/5 hover:bg-white/10 flex items-center justify-center text-slate-400 transition-colors">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-3xl font-bold tracking-tight"><?= htmlspecialchars($username) ?></h1>
            <p class="text-slate-400 mt-1">Hesap profili ve detaylı envanter/drop geçmişi.</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="glass-panel p-5 rounded-2xl border border-white/5">
            <div class="text-sm font-medium text-slate-400 mb-1">Toplam Drop Event</div>
            <div class="text-3xl font-black text-white"><?= $accStats['total_drops'] ?></div>
        </div>
        <div class="glass-panel p-5 rounded-2xl border border-white/5">
            <div class="text-sm font-medium text-slate-400 mb-1">Toplam Kazanılan (USD)</div>
            <div class="text-3xl font-black text-emerald-400">$<?= number_format($accStats['total_value'] ?: 0, 2) ?></div>
        </div>
    </div>

    <!-- Drop Tab -->
    <div class="glass-panel rounded-2xl overflow-hidden border border-white/5">
        <div class="p-4 border-b border-white/5 bg-white/5">
            <h2 class="font-bold text-lg"><i class="fas fa-box-open mr-2 text-emerald-400"></i> Drop Geçmişi</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-black/20 text-slate-300">
                    <tr>
                        <th class="px-6 py-4 font-semibold">Tarih</th>
                        <th class="px-6 py-4 font-semibold">Item</th>
                        <th class="px-6 py-4 font-semibold">Dış Görünüş / Float</th>
                        <th class="px-6 py-4 font-semibold">Değer</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if (count($drops) === 0): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-slate-400">
                            Bu hesaba ait drop bulunamadı.
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php foreach($drops as $drop): ?>
                    <tr class="hover:bg-white/5 transition-colors">
                        <td class="px-6 py-4 text-slate-400">
                            <?= date('Y-m-d H:i', strtotime($drop['detected_at'])) ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-white"><?= htmlspecialchars($drop['market_hash_name'] ?? 'Bilinmeyen Item') ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <?php if($drop['exterior_tr']): ?>
                                <div class="text-xs text-slate-300 mb-1"><span class="text-slate-500">D:</span> <?= htmlspecialchars($drop['exterior_tr']) ?></div>
                            <?php endif; ?>
                            <?php if($drop['float_value']): ?>
                                <div class="text-xs text-slate-300"><span class="text-slate-500">A:</span> <?= number_format($drop['float_value'], 9) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php if($drop['price_usd']): ?>
                                <div class="font-bold text-emerald-400">$<?= number_format($drop['price_usd'], 2) ?></div>
                                <div class="text-[10px] text-slate-500"><?= htmlspecialchars($drop['price_source']) ?></div>
                            <?php else: ?>
                                <span class="text-slate-500 text-xs italic">Bilinmiyor</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php render_footer(); ?>
