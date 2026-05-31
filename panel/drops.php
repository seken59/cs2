<?php
require_once 'core.php';
$currentPage = 'drops.php';

// Pagination and Filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$whereClause = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $whereClause .= " AND (de.username LIKE :search OR di.market_hash_name LIKE :search)";
    $params['search'] = "%$search%";
}

$totalStmt = $db->prepare("
    SELECT COUNT(*) 
    FROM drop_events de
    LEFT JOIN drop_items di ON de.id = di.drop_event_id
    $whereClause
");
$totalStmt->execute($params);
$total = $totalStmt->fetchColumn();
$pages = ceil($total / $perPage);

$stmt = $db->prepare("
    SELECT de.id as event_id, de.username, de.detected_at, de.status as event_status, de.batch_id,
           di.market_hash_name, di.exterior_tr, di.float_value, di.price_usd, di.price_source, di.id as item_id
    FROM drop_events de
    LEFT JOIN drop_items di ON de.id = di.drop_event_id
    $whereClause
    ORDER BY de.detected_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$drops = $stmt->fetchAll(PDO::FETCH_ASSOC);

render_header('Drop Geçmişi');
?>

<div class="p-6 max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Drop Geçmişi</h1>
            <p class="text-slate-400 mt-1">Hesaplardan düşen kasalar, skinler ve haftalık ödüller.</p>
        </div>
        <div class="flex space-x-3">
            <form method="GET" class="flex space-x-2">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Hesap veya Item Ara..." class="bg-black/20 border border-white/10 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-emerald-500">
                <button type="submit" class="bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 px-4 py-2 rounded-lg hover:bg-emerald-600/40 transition-colors text-sm font-medium">
                    <i class="fas fa-search mr-2"></i> Ara
                </button>
            </form>
        </div>
    </div>

    <div class="glass-panel rounded-2xl overflow-hidden border border-white/5">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-white/5 text-slate-300">
                    <tr>
                        <th class="px-6 py-4 font-semibold">Tarih</th>
                        <th class="px-6 py-4 font-semibold">Hesap</th>
                        <th class="px-6 py-4 font-semibold">Item</th>
                        <th class="px-6 py-4 font-semibold">Özellikler</th>
                        <th class="px-6 py-4 font-semibold">Fiyat (USD)</th>
                        <th class="px-6 py-4 font-semibold">Durum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if (count($drops) === 0): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-slate-400">
                            Henüz drop kaydı bulunmuyor.
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php foreach($drops as $drop): ?>
                    <tr class="hover:bg-white/5 transition-colors group">
                        <td class="px-6 py-4 text-slate-400">
                            <?= date('Y-m-d H:i', strtotime($drop['detected_at'])) ?>
                        </td>
                        <td class="px-6 py-4 font-medium">
                            <a href="account_details.php?username=<?= urlencode($drop['username']) ?>" class="hover:text-emerald-400 transition-colors">
                                <?= htmlspecialchars($drop['username']) ?>
                            </a>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-white"><?= htmlspecialchars($drop['market_hash_name'] ?? 'Bilinmeyen Item') ?></div>
                            <?php if($drop['batch_id']): ?>
                                <div class="text-[10px] text-slate-500 mt-1"><i class="fas fa-layer-group"></i> <?= htmlspecialchars($drop['batch_id']) ?></div>
                            <?php endif; ?>
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
                        <td class="px-6 py-4">
                            <?php if($drop['event_status'] === 'DETECTED'): ?>
                                <span class="px-2 py-1 bg-yellow-500/20 text-yellow-400 rounded text-xs border border-yellow-500/30">Tespit Edildi</span>
                            <?php elseif($drop['event_status'] === 'VALUED'): ?>
                                <span class="px-2 py-1 bg-blue-500/20 text-blue-400 rounded text-xs border border-blue-500/30">Fiyatlandı</span>
                            <?php elseif($drop['event_status'] === 'CLAIMED'): ?>
                                <span class="px-2 py-1 bg-green-500/20 text-green-400 rounded text-xs border border-green-500/30">Envantere Geçti</span>
                            <?php else: ?>
                                <span class="px-2 py-1 bg-slate-500/20 text-slate-400 rounded text-xs border border-slate-500/30"><?= htmlspecialchars($drop['event_status']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($pages > 1): ?>
        <div class="px-6 py-4 border-t border-white/5 flex justify-between items-center bg-black/20">
            <div class="text-sm text-slate-400">Toplam <?= $total ?> kayıt</div>
            <div class="flex space-x-1">
                <?php for($i=1; $i<=$pages; $i++): ?>
                    <a href="?page=<?= $i ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="px-3 py-1 rounded <?= $i === $page ? 'bg-emerald-600 text-white' : 'bg-white/5 text-slate-300 hover:bg-white/10' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php render_footer(); ?>
