<?php
// panel/action_queue.php
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/layout.php';

try {
    // Basic init or setup if needed
} catch (Throwable $e) {
    error_log("ActionQueue View Error: " . $e->getMessage());
    $fatalError = "Modül yüklenemedi. Lütfen logları kontrol edin.";
}

$statusFilter = $_GET['status'] ?? '';

render_header('Action Queue');
?>

<script>
// Global variables for AJAX
const csrfToken = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
const currentStatusFilter = <?= json_encode($statusFilter) ?>;
</script>

<div class="glass-panel rounded-2xl overflow-hidden">
    <div class="p-6 border-b border-white/5 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <h2 class="text-xl font-semibold">Görev Kuyruğu (Action Queue)</h2>
        
        <div class="flex space-x-2 overflow-x-auto pb-2 w-full md:w-auto">
            <a href="action_queue.php" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $statusFilter==='' ? 'bg-primary text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">Tümü</a>
            <a href="action_queue.php?status=PENDING" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $statusFilter==='PENDING' ? 'bg-blue-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">PENDING</a>
            <a href="action_queue.php?status=PROCESSING" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $statusFilter==='PROCESSING' ? 'bg-purple-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">PROCESSING</a>
            <a href="action_queue.php?status=COMPLETED" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $statusFilter==='COMPLETED' ? 'bg-green-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">COMPLETED</a>
            <a href="action_queue.php?status=FAILED" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $statusFilter==='FAILED' ? 'bg-red-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">FAILED</a>
            <a href="action_queue.php?status=DEAD" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors <?= $statusFilter==='DEAD' ? 'bg-slate-900 text-white border border-slate-700' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">DEAD</a>
        </div>
    </div>
    <div class="overflow-x-auto">
        <div id="actionToast" class="hidden fixed bottom-4 right-4 bg-slate-800 text-white px-4 py-2 rounded shadow-lg z-50 transition-opacity duration-300">İşlem başarılı.</div>
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-white/5 text-slate-400 text-sm uppercase tracking-wider">
                    <th class="p-4 font-medium">ID / Action</th>
                    <th class="p-4 font-medium">Status</th>
                    <th class="p-4 font-medium">Retry / Timeout</th>
                    <th class="p-4 font-medium">Zamanlama</th>
                    <th class="p-4 font-medium text-right">İşlem</th>
                </tr>
            </thead>
            <tbody id="queueTableBody" class="divide-y divide-white/5">
                <tr><td colspan="5" class="p-8 text-center text-slate-500"><i class="fas fa-spinner fa-spin mr-2"></i> Yükleniyor...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
function showToast(message, isError = false) {
    const toast = document.getElementById('actionToast');
    toast.textContent = message;
    toast.className = `fixed bottom-4 right-4 px-4 py-2 rounded shadow-lg z-50 transition-opacity duration-300 ${isError ? 'bg-red-600 text-white' : 'bg-green-600 text-white'}`;
    toast.classList.remove('hidden');
    setTimeout(() => {
        toast.classList.add('hidden');
    }, 3000);
}

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return (unsafe + '').replace(/[&<"'>]/g, function (m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}

function getStatusColor(status) {
    if (status === 'PENDING') return 'text-blue-400';
    if (status === 'PROCESSING') return 'text-purple-400';
    if (status === 'COMPLETED') return 'text-green-400';
    if (status === 'FAILED') return 'text-red-400';
    if (status === 'DEAD') return 'text-slate-500';
    if (status === 'CANCELLED') return 'text-slate-400';
    return 'text-slate-400';
}

function renderTable(data) {
    const tbody = document.getElementById('queueTableBody');
    if (!data || data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="p-8 text-center text-slate-500">Kuyrukta kayıt bulunmuyor.</td></tr>';
        return;
    }
    
    let html = '';
    data.forEach(a => {
        const sColor = getStatusColor(a.status);
        let actionButtons = '';
        
        if (['PENDING', 'PROCESSING'].includes(a.status)) {
            actionButtons += `<button onclick="queueAction('cancel', ${a.id})" class="text-xs bg-slate-800 hover:bg-red-500/20 text-red-400 px-3 py-1 rounded border border-white/5 transition-colors mr-2">Cancel</button>`;
        }
        if (['FAILED', 'DEAD', 'PROCESSING'].includes(a.status)) {
            actionButtons += `<button onclick="queueAction('retry', ${a.id})" class="text-xs bg-slate-800 hover:bg-blue-500/20 text-blue-400 px-3 py-1 rounded border border-white/5 transition-colors mr-2">Retry</button>`;
        }
        if (a.status === 'FAILED') {
            actionButtons += `<button onclick="queueAction('mark_dead', ${a.id})" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-1 rounded border border-white/5 transition-colors">Mark DEAD</button>`;
        }
        
        const payloadSafe = escapeHtml(a.payload);
        const errorSafe = escapeHtml(a.last_error);
        
        html += `<tr class="hover:bg-white/[0.02] transition-colors group">
            <td class="p-4">
                <div class="font-mono text-xs text-slate-500 mb-1">#${a.id}</div>
                <div class="font-bold text-slate-200">${escapeHtml(a.action_type)}</div>
                <div class="text-[10px] text-slate-500 truncate max-w-[200px]" title="${payloadSafe}">${payloadSafe}</div>
            </td>
            <td class="p-4 font-semibold ${sColor}">${a.status}</td>
            <td class="p-4 text-sm text-slate-300">
                <div>Retry: ${a.retry_count}/${a.max_retry}</div>
                <div class="text-xs text-slate-500">Timeout: ${a.timeout_seconds}s</div>
                ${a.last_error ? `<div class="text-[10px] text-red-400 truncate max-w-[150px]" title="${errorSafe}">${errorSafe}</div>` : ''}
            </td>
            <td class="p-4 text-xs text-slate-400">
                <div>Oluşturulma: ${a.created_at}</div>
                <div>Güncelleme: ${a.updated_at}</div>
            </td>
            <td class="p-4 text-right space-x-2">
                ${actionButtons}
            </td>
        </tr>`;
    });
    
    tbody.innerHTML = html;
}

function fetchQueue() {
    let url = 'api/action_queue_list.php';
    if (currentStatusFilter) {
        url += '?status=' + encodeURIComponent(currentStatusFilter);
    }
    fetch(url)
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                renderTable(res.data);
            }
        })
        .catch(err => console.error("Fetch error:", err));
}

function queueAction(action, id) {
    if(action === 'cancel' && !confirm('Bu işlemi iptal etmek istediğinize emin misiniz?')) return;
    
    const formData = new FormData();
    formData.append('action', action);
    formData.append('id', id);
    formData.append('csrf_token', csrfToken);
    
    fetch('api/action_queue_action.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            showToast(res.message);
            fetchQueue();
        } else {
            showToast(res.message, true);
        }
    })
    .catch(err => {
        showToast("Bir hata oluştu", true);
        console.error(err);
    });
}

// Initial fetch & auto-refresh
fetchQueue();
setInterval(fetchQueue, 5000);
</script>

<?php render_footer(); ?>

