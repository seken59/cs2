<?php
// panel/layout.php
require_once __DIR__ . '/core.php';

function render_header($title = "Dashboard") {
    $csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '');
    echo <<<HTML
<!DOCTYPE html>
<html lang="tr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{$csrf}">
    <title>Dragle NOC | {$title}</title>
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
    </style>
</head>
<body class="min-h-screen flex">
    <!-- Sidebar -->
    <aside class="w-64 glass-panel border-r border-white/5 flex-shrink-0 min-h-screen hidden md:flex flex-col">
        <div class="p-6 border-b border-white/5">
            <h1 class="text-2xl font-bold tracking-tight"><span class="gradient-text">Dragle</span> NOC</h1>
            <p class="text-xs text-slate-400 mt-1">CS Operations Center</p>
        </div>
        <nav class="p-4 flex-1 space-y-1 overflow-y-auto text-sm">
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-white/5 hover:text-white rounded-lg transition-colors"><i class="fas fa-chart-line w-6"></i> Dashboard</a>
            <a href="accounts.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-white/5 hover:text-white rounded-lg transition-colors"><i class="fas fa-users w-6"></i> Hesaplar</a>
            <a href="batches.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-white/5 hover:text-white rounded-lg transition-colors"><i class="fas fa-layer-group w-6"></i> Batchler</a>
            <a href="action_queue.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-white/5 hover:text-white rounded-lg transition-colors"><i class="fas fa-tasks w-6"></i> Action Queue</a>
            <a href="workers.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-white/5 hover:text-white rounded-lg transition-colors"><i class="fas fa-server w-6"></i> Workerlar</a>
            <a href="alerts.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-white/5 hover:text-white rounded-lg transition-colors"><i class="fas fa-bell w-6"></i> Alerts</a>
            <a href="backup.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-white/5 hover:text-white rounded-lg transition-colors"><i class="fas fa-database w-6"></i> Backup/Restore</a>
            <a href="maintenance.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-white/5 hover:text-white rounded-lg transition-colors"><i class="fas fa-tools w-6"></i> Maintenance</a>
            <a href="health.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-white/5 hover:text-white rounded-lg transition-colors"><i class="fas fa-heartbeat w-6"></i> Sistem Sağlığı</a>
            <a href="settings.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-white/5 hover:text-white rounded-lg transition-colors"><i class="fas fa-cog w-6"></i> Ayarlar</a>
            <a href="audit.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-white/5 hover:text-white rounded-lg transition-colors"><i class="fas fa-history w-6"></i> Audit Log</a>
            <a href="security.php" class="flex items-center px-4 py-3 text-slate-300 hover:bg-white/5 hover:text-white rounded-lg transition-colors"><i class="fas fa-shield-alt w-6"></i> Güvenlik</a>
        </nav>
        <div class="p-4 border-t border-white/5">
            <button class="w-full bg-red-500/10 hover:bg-red-500/20 text-red-400 font-bold py-2 px-4 rounded-lg transition-colors border border-red-500/20 text-sm" onclick="emergencyStop()">
                <i class="fas fa-exclamation-triangle"></i> EMERGENCY STOP
            </button>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-h-screen overflow-hidden bg-[#0b0f19]">
        <header class="h-16 glass-panel border-b border-white/5 flex items-center justify-between px-6 flex-shrink-0 z-10">
            <h2 class="text-xl font-semibold">{$title}</h2>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-slate-400">Admin</span>
                <a href="logout.php" class="text-slate-400 hover:text-red-400 transition-colors"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
            </div>
        </header>
        <div class="flex-1 overflow-y-auto p-6 relative">
HTML;
}

function render_footer() {
    $csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '');
    echo <<<HTML
        </div>
    </main>
    <script>
        async function emergencyStop() {
            if(!confirm("DİKKAT: EMERGENCY STOP tetiklemek üzeresiniz. Bütün batch'ler iptal edilecek ve tüm container'lar acil durdurulacaktır. Onaylıyor musunuz?")) return;
            const confirmText = prompt("Devam etmek için 'EMERGENCY STOP' yazın:");
            if(confirmText !== "EMERGENCY STOP") { alert("İptal edildi."); return; }
            
            const formData = new FormData();
            formData.append('action', 'emergency_stop');
            formData.append('csrf_token', '{$csrf}');
            
            try {
                const res = await fetch('ajax.php', { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) {
                    alert('Acil durdurma protokolu başlatıldı!');
                    location.reload();
                } else {
                    alert('Hata: ' + data.message);
                }
            } catch(e) { alert("Bağlantı hatası"); }
        }
    </script>
</body>
</html>
HTML;
}
?>
