<?php
// panel/security.php
require_once __DIR__ . '/layout.php';

$admins = $db->query("SELECT id, username, is_active, created_at FROM admin_users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$logins = $db->query("SELECT * FROM login_attempts ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

render_header('Güvenlik Yönetimi');
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Admin Users -->
    <div class="glass-panel rounded-2xl overflow-hidden flex flex-col">
        <div class="p-6 border-b border-white/5 flex justify-between items-center">
            <h2 class="text-xl font-semibold"><i class="fas fa-users-cog text-blue-400 mr-2"></i> Sistem Yöneticileri</h2>
        </div>
        <div class="overflow-x-auto flex-1">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white/5 text-slate-400 text-xs uppercase tracking-wider">
                        <th class="p-4 font-medium">Kullanıcı Adı</th>
                        <th class="p-4 font-medium">Durum</th>
                        <th class="p-4 font-medium">Kayıt Tarihi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-sm">
                    <?php foreach($admins as $a): ?>
                    <tr class="hover:bg-white/[0.02]">
                        <td class="p-4 font-bold text-slate-200">
                            <i class="fas fa-user-shield text-slate-500 mr-2"></i><?= htmlspecialchars($a['username']) ?>
                        </td>
                        <td class="p-4">
                            <?php if($a['is_active']): ?>
                                <span class="px-2 py-1 bg-green-500/20 text-green-400 rounded text-xs">Aktif</span>
                            <?php else: ?>
                                <span class="px-2 py-1 bg-red-500/20 text-red-400 rounded text-xs">Pasif</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-slate-400 text-xs"><?= $a['created_at'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Login Attempts -->
    <div class="glass-panel rounded-2xl overflow-hidden flex flex-col">
        <div class="p-6 border-b border-white/5 flex justify-between items-center">
            <h2 class="text-xl font-semibold"><i class="fas fa-sign-in-alt text-yellow-400 mr-2"></i> Son Giriş Denemeleri</h2>
        </div>
        <div class="overflow-y-auto max-h-[500px]">
            <table class="w-full text-left border-collapse">
                <thead class="sticky top-0 bg-[#0b0f19] z-10">
                    <tr class="bg-white/5 text-slate-400 text-xs uppercase tracking-wider">
                        <th class="p-4 font-medium">Tarih</th>
                        <th class="p-4 font-medium">IP Adresi</th>
                        <th class="p-4 font-medium">Durum / Hata</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-sm">
                    <?php if (count($logins) === 0): ?>
                    <tr><td colspan="3" class="p-8 text-center text-slate-500">Henüz kayıt yok.</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach($logins as $l): ?>
                    <tr class="hover:bg-white/[0.02]">
                        <td class="p-4 text-xs text-slate-400 whitespace-nowrap"><?= $l['created_at'] ?></td>
                        <td class="p-4 font-mono text-xs text-slate-300"><?= htmlspecialchars($l['ip_address']) ?></td>
                        <td class="p-4">
                            <?php if($l['success']): ?>
                                <span class="text-green-400 text-xs font-bold"><i class="fas fa-check mr-1"></i> Başarılı</span>
                            <?php else: ?>
                                <div class="text-red-400 text-xs font-bold"><i class="fas fa-times mr-1"></i> Başarısız</div>
                                <div class="text-[10px] text-slate-500 mt-1"><?= htmlspecialchars($l['failure_reason']) ?></div>
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
