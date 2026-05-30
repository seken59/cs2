<?php
// panel/index.php - KO-LMS Farm Kontrol Paneli
session_start();

if (!isset($_SESSION['kolms_admin_logged_in']) || $_SESSION['kolms_admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'config.php';

try {
    // $db config.php'den geliyor. Sadece tabloyu oluşturuyoruz:
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Tabloyu oluştur (Eğer orchestrator henüz çalışmadıysa)
    $db->exec("CREATE TABLE IF NOT EXISTS accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) UNIQUE,
        password VARCHAR(255),
        shared_secret VARCHAR(255),
        proxy VARCHAR(255),
        status VARCHAR(50) DEFAULT 'IDLE',
        level INT DEFAULT 1,
        xp INT DEFAULT 0,
        batch_id VARCHAR(255) DEFAULT NULL,
        role VARCHAR(50) DEFAULT NULL,
        locked_until DATETIME DEFAULT NULL,
        last_run_at DATETIME DEFAULT NULL,
        heartbeat_at DATETIME DEFAULT NULL,
        last_error VARCHAR(255) DEFAULT NULL
    )");
} catch (Exception $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// İstatistikleri çek
$totalAccounts = $db->query("SELECT COUNT(*) FROM accounts")->fetchColumn();
$farmingAccounts = $db->query("SELECT COUNT(*) FROM accounts WHERE status = 'FARMING'")->fetchColumn();
$droppedAccounts = $db->query("SELECT COUNT(*) FROM accounts WHERE status = 'DROPPED'")->fetchColumn();

// Tüm hesapları çek
$accounts = $db->query("SELECT * FROM accounts ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <title>KO-LMS | CS2 Farm Orchestrator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: '#0f172a',
                        darker: '#0b0f19',
                        primary: '#3b82f6',
                        accent: '#8b5cf6'
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0b0f19; color: #f1f5f9; }
        .glass-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.05); }
        .gradient-text { background: linear-gradient(to right, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .progress-bar { transition: width 0.5s ease-in-out; }
    </style>
</head>
<body class="min-h-screen p-6">

    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <header class="flex justify-between items-center mb-10">
            <div>
                <h1 class="text-4xl font-bold tracking-tight"><span class="gradient-text">KO-LMS</span> Farm Control</h1>
                <p class="text-slate-400 mt-1">CS2 Tam Otonom Pasif Gelir Yönetimi</p>
            </div>
            <div class="flex items-center space-x-4">
                <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-primary hover:bg-blue-600 text-white px-6 py-2 rounded-lg font-medium transition-all shadow-[0_0_15px_rgba(59,130,246,0.5)]">
                    <i class="fas fa-plus mr-2"></i> Hesap Ekle
                </button>
            </div>
        </header>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
            <div class="glass-panel p-6 rounded-2xl">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm font-medium uppercase tracking-wider">Toplam Hesap</p>
                        <h3 class="text-3xl font-bold mt-2"><?= $totalAccounts ?></h3>
                    </div>
                    <div class="p-3 bg-slate-800 rounded-lg text-primary"><i class="fas fa-users text-xl"></i></div>
                </div>
            </div>
            <div class="glass-panel p-6 rounded-2xl relative overflow-hidden">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-green-500/10 rounded-full blur-2xl"></div>
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm font-medium uppercase tracking-wider">Kasa Düşüren</p>
                        <h3 class="text-3xl font-bold mt-2 text-green-400"><?= $droppedAccounts ?></h3>
                    </div>
                    <div class="p-3 bg-green-500/20 rounded-lg text-green-400"><i class="fas fa-box-open text-xl"></i></div>
                </div>
            </div>
            <div class="glass-panel p-6 rounded-2xl relative overflow-hidden">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-blue-500/10 rounded-full blur-2xl"></div>
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm font-medium uppercase tracking-wider">Aktif Kasan (Farming)</p>
                        <h3 class="text-3xl font-bold mt-2 text-blue-400"><?= $farmingAccounts ?></h3>
                    </div>
                    <div class="p-3 bg-blue-500/20 rounded-lg text-blue-400"><i class="fas fa-crosshairs text-xl animate-pulse"></i></div>
                </div>
            </div>
            <div class="glass-panel p-6 rounded-2xl relative overflow-hidden">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-500/10 rounded-full blur-2xl"></div>
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-slate-400 text-sm font-medium uppercase tracking-wider">Sunucu Yükü</p>
                        <h3 class="text-3xl font-bold mt-2 text-purple-400">Batch: 4</h3>
                    </div>
                    <div class="p-3 bg-purple-500/20 rounded-lg text-purple-400"><i class="fas fa-server text-xl"></i></div>
                </div>
            </div>
        </div>

        <!-- Accounts Table -->
        <div class="glass-panel rounded-2xl overflow-hidden">
            <div class="p-6 border-b border-white/5 flex justify-between items-center">
                <h2 class="text-xl font-semibold">Hesap Envanteri</h2>
                <button onclick="location.reload()" class="text-slate-400 hover:text-white transition-colors"><i class="fas fa-sync-alt"></i> Yenile</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-white/5 text-slate-400 text-sm uppercase tracking-wider">
                            <th class="p-4 font-medium">Hesap Adı</th>
                            <th class="p-4 font-medium">Durum</th>
                            <th class="p-4 font-medium">Seviye</th>
                            <th class="p-4 font-medium w-1/4">Haftalık XP İlerlemesi</th>
                            <th class="p-4 font-medium text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php if (count($accounts) === 0): ?>
                        <tr><td colspan="5" class="p-8 text-center text-slate-500">Henüz hesap eklenmemiş.</td></tr>
                        <?php endif; ?>
                        
                        <?php foreach($accounts as $acc): 
                            $xpPercent = min(100, ($acc['xp'] / 5000) * 100);
                            $statusColor = 'text-slate-400';
                            $statusBg = 'bg-slate-500/20';
                            if ($acc['status'] === 'FARMING') { $statusColor = 'text-blue-400'; $statusBg = 'bg-blue-500/20'; }
                            if ($acc['status'] === 'DROPPED') { $statusColor = 'text-green-400'; $statusBg = 'bg-green-500/20'; }
                        ?>
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="p-4 font-medium flex items-center">
                                <img src="https://avatars.steamstatic.com/fef49e7fa7e1997310d705b2a6158ff8dc1cdfeb_full.jpg" class="w-8 h-8 rounded-full mr-3 border border-white/10" alt="Avatar">
                                <?= htmlspecialchars($acc['username']) ?>
                            </td>
                            <td class="p-4">
                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $statusColor ?> <?= $statusBg ?>">
                                    <?= htmlspecialchars($acc['status']) ?>
                                </span>
                            </td>
                            <td class="p-4 font-bold text-lg"><?= $acc['level'] ?></td>
                            <td class="p-4">
                                <div class="flex justify-between text-xs text-slate-400 mb-1">
                                    <span><?= $acc['xp'] ?> XP</span>
                                    <span>5000 XP</span>
                                </div>
                                <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                                    <div class="bg-gradient-to-r from-blue-500 to-purple-500 h-2 rounded-full progress-bar" style="width: <?= $xpPercent ?>%"></div>
                                </div>
                            </td>
                            <td class="p-4 text-right">
                                <button onclick="deleteAccount(<?= $acc['id'] ?>)" class="text-red-400 hover:text-red-300 p-2 rounded hover:bg-red-500/10 transition-colors"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Account Modal -->
    <div id="addModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden flex justify-center items-center z-50">
        <div class="glass-panel p-8 rounded-2xl w-full max-w-md transform transition-all">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold">Yeni Hesap Ekle</h2>
                <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-slate-400 hover:text-white"><i class="fas fa-times text-xl"></i></button>
            </div>
            
            <form id="addForm" onsubmit="submitForm(event)">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-1">Steam Kullanıcı Adı</label>
                        <input type="text" id="username" required class="w-full bg-slate-800/50 border border-white/10 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-1">Şifre</label>
                        <input type="password" id="password" required class="w-full bg-slate-800/50 border border-white/10 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-1">Shared Secret (SDA)</label>
                        <input type="text" id="shared_secret" placeholder="MaFile içindeki shared_secret" class="w-full bg-slate-800/50 border border-white/10 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                    </div>
                    <button type="submit" class="w-full bg-gradient-to-r from-primary to-accent hover:opacity-90 text-white font-bold py-3 rounded-lg shadow-lg mt-6 transition-opacity">
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
            formData.append('action', 'add');
            formData.append('username', username);
            formData.append('password', password);
            formData.append('shared_secret', shared_secret);
            formData.append('csrf_token', csrf_token);

            const res = await fetch('ajax.php', { method: 'POST', body: formData, headers: {'X-CSRF-TOKEN': csrf_token} });
            const data = await res.json();
            
            if(data.success) {
                location.reload();
            } else {
                alert('Hata: ' + data.message);
            }
        }

        async function deleteAccount(id) {
            if(!confirm('Bu hesabı silmek istediğinize emin misiniz?')) return;
            
            const csrf_token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            formData.append('csrf_token', csrf_token);

            const res = await fetch('ajax.php', { method: 'POST', body: formData, headers: {'X-CSRF-TOKEN': csrf_token} });
            const data = await res.json();
            
            if(data.success) {
                location.reload();
            } else {
                alert('Hata: ' + data.message);
            }
        }
    </script>
</body>
</html>

