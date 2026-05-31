<?php
// panel/accounts.php
require_once __DIR__ . '/layout.php';

try {
    $accounts = $db->query("SELECT * FROM accounts ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("Accounts View Error: " . $e->getMessage());
    $fatalError = "Modül yüklenemedi. Lütfen system_alerts ve PHP error loglarını kontrol edin.";
    $accounts = [];
}

render_header('Hesap Envanteri');
?>

<div class="glass-panel rounded-2xl overflow-hidden flex flex-col h-[calc(100vh-100px)]">
    <div class="p-6 border-b border-white/5 flex justify-between items-center bg-white/[0.02]">
        <h2 class="text-xl font-semibold"><i class="fas fa-users text-blue-400 mr-2"></i> Hesap Envanteri</h2>
        <div class="flex space-x-4">
            <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg text-sm font-medium transition-all shadow-lg border border-blue-500">
                <i class="fas fa-plus mr-1"></i> Hesap Ekle
            </button>
            <button onclick="location.reload()" class="text-slate-400 hover:text-white transition-colors bg-slate-800 px-4 py-2 rounded-lg border border-white/10 text-sm">
                <i class="fas fa-sync-alt"></i> Yenile
            </button>
        </div>
    </div>
    <div class="overflow-y-auto flex-1">
        <table class="w-full text-left border-collapse">
            <thead class="sticky top-0 bg-[#0b0f19] z-10">
                <tr class="bg-white/5 text-slate-400 text-sm uppercase tracking-wider">
                    <th class="p-4 font-medium border-b border-white/5">Hesap Adı</th>
                    <th class="p-4 font-medium border-b border-white/5">Durum / Batch</th>
                    <th class="p-4 font-medium border-b border-white/5">Seviye / Proxy</th>
                    <th class="p-4 font-medium border-b border-white/5 w-1/4">Haftalık XP İlerlemesi</th>
                    <th class="p-4 font-medium border-b border-white/5 text-right">İşlem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                <?php if (isset($fatalError)): ?>
                <tr><td colspan="5" class="p-8 text-center text-red-500 font-bold"><?= htmlspecialchars($fatalError) ?></td></tr>
                <?php elseif (count($accounts) === 0): ?>
                <tr><td colspan="5" class="p-8 text-center text-slate-500">Henüz hesap eklenmemiş.</td></tr>
                <?php endif; ?>
                
                <?php foreach($accounts as $acc): 
                    $xpPercent = min(100, ($acc['xp'] / 5000) * 100);
                    $statusColor = 'text-slate-400';
                    $statusBg = 'bg-slate-500/20';
                    if ($acc['status'] === 'FARMING') { $statusColor = 'text-blue-400'; $statusBg = 'bg-blue-500/20'; }
                    if ($acc['status'] === 'DROPPED') { $statusColor = 'text-green-400'; $statusBg = 'bg-green-500/20'; }
                    if ($acc['status'] === 'FAILED') { $statusColor = 'text-red-400'; $statusBg = 'bg-red-500/20'; }
                    if ($acc['status'] === 'RESERVED') { $statusColor = 'text-yellow-400'; $statusBg = 'bg-yellow-500/20'; }
                ?>
                <tr class="hover:bg-white/[0.02] transition-colors group">
                    <td class="p-4 font-medium flex items-center">
                        <img src="https://avatars.steamstatic.com/fef49e7fa7e1997310d705b2a6158ff8dc1cdfeb_full.jpg" class="w-8 h-8 rounded-full mr-3 border border-white/10" alt="Avatar">
                        <div>
                            <div class="text-slate-200"><?= htmlspecialchars($acc['username']) ?></div>
                            <?php if($acc['locked_until']): ?>
                                <div class="text-[10px] text-red-400 mt-0.5"><i class="fas fa-lock"></i> Kilitli: <?= $acc['locked_until'] ?></div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="p-4">
                        <span class="px-3 py-1 rounded-full text-[10px] uppercase font-bold <?= $statusColor ?> <?= $statusBg ?> border border-white/5">
                            <?= htmlspecialchars($acc['status']) ?>
                        </span>
                        <?php if($acc['batch_id']): ?>
                            <div class="text-[10px] text-slate-500 mt-1.5 font-mono"><i class="fas fa-layer-group text-[8px]"></i> <?= htmlspecialchars($acc['batch_id']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="p-4">
                        <div class="font-bold text-lg text-slate-200"><?= $acc['level'] ?></div>
                        <div class="text-[10px] text-slate-500 truncate max-w-[120px]" title="<?= htmlspecialchars($acc['proxy']??'') ?>"><i class="fas fa-globe"></i> <?= htmlspecialchars($acc['proxy'] ?? 'No Proxy') ?></div>
                    </td>
                    <td class="p-4">
                        <div class="flex justify-between text-xs text-slate-400 mb-1">
                            <span><span class="text-slate-300 font-medium"><?= $acc['xp'] ?></span> XP</span>
                            <span>5000 XP</span>
                        </div>
                        <div class="w-full bg-slate-900 rounded-full h-1.5 overflow-hidden mb-1 border border-slate-800">
                            <div class="bg-gradient-to-r from-blue-500 to-purple-500 h-1.5 rounded-full progress-bar" style="width: <?= $xpPercent ?>%"></div>
                        </div>
                        <?php if($acc['last_error']): ?>
                            <div class="text-[10px] text-red-400 truncate max-w-[200px]" title="<?= htmlspecialchars($acc['last_error']) ?>"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($acc['last_error']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="p-4 text-right">
                        <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="accountAction('reset', <?= $acc['id'] ?>)" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 p-2 rounded border border-white/5 transition-colors" title="IDLE Durumuna Çek"><i class="fas fa-undo"></i></button>
                            <button onclick="accountAction('clear_error', <?= $acc['id'] ?>)" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 p-2 rounded border border-white/5 transition-colors" title="Hatayı Temizle"><i class="fas fa-eraser"></i></button>
                            <button onclick="accountAction('delete', <?= $acc['id'] ?>)" class="text-xs bg-slate-800 hover:bg-red-500/20 text-red-400 p-2 rounded border border-white/5 transition-colors" title="Sil"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Account Modal -->
<div id="addModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden flex justify-center items-center z-50 p-4">
    <div class="glass-panel p-8 rounded-2xl w-full max-w-md transform transition-all border border-white/10 shadow-2xl">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold">Yeni Hesap Ekle</h2>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-slate-400 hover:text-white transition-colors"><i class="fas fa-times text-xl"></i></button>
        </div>
        
        <form id="addForm" onsubmit="submitForm(event)">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Steam Kullanıcı Adı</label>
                    <input type="text" id="username" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-blue-500 transition-colors">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Şifre</label>
                    <input type="password" id="password" required class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-blue-500 transition-colors">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Shared Secret (SDA)</label>
                    <input type="text" id="shared_secret" placeholder="MaFile içindeki shared_secret" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-blue-500 transition-colors">
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 px-4 rounded-xl shadow-lg mt-6 transition-colors border border-blue-500">
                    Hesabı Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    async function submitForm(e) {
        e.preventDefault();
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        const shared_secret = document.getElementById('shared_secret').value;
        
        const csrf_token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        const formData = new FormData();
        formData.append('action', 'add_account');
        formData.append('username', username);
        formData.append('password', password);
        formData.append('shared_secret', shared_secret);
        formData.append('csrf_token', csrf_token);

        const res = await fetch('ajax.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if(data.success) location.reload();
        else alert('Hata: ' + data.message);
    }

    async function accountAction(action, id) {
        if(action === 'delete' && !confirm('Bu hesabı silmek istediğinize emin misiniz?')) return;
        
        const csrf_token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const formData = new FormData();
        formData.append('action', 'account_' + action);
        formData.append('id', id);
        formData.append('csrf_token', csrf_token);

        const res = await fetch('ajax.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if(data.success) location.reload();
        else alert('Hata: ' + data.message);
    }
</script>

<?php render_footer(); ?>
