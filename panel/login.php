<?php
// Secure Session Settings (V8)
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();
require_once 'GoogleAuthenticator.php';

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    die("Sistem konfigürasyon dosyası bulunamadı. Lütfen config.php'yi oluşturun.");
}
require_once $configPath;

// IP Whitelist (DB'den çekilecek)
$ENABLE_IP_CHECK = true;

try {
    // $db config.php icinden geliyor olmali
    $stmt = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'allowed_ips'");
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    $ALLOWED_IPS = $res ? explode(',', $res['setting_value']) : ['127.0.0.1', '::1'];
} catch(Exception $e) {
    $ALLOWED_IPS = ['127.0.0.1', '::1']; // DB hatasında Fallback
}

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    return $_SERVER['REMOTE_ADDR'];
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ip = getUserIP();
    
    // IP Whitelist Check
    if ($ENABLE_IP_CHECK && !in_array($ip, $ALLOWED_IPS)) {
        header('HTTP/1.0 403 Forbidden');
        die("<h1>403 Forbidden</h1><p>Yetkisiz IP Adresi: " . htmlspecialchars($ip) . ". Sistem kilitlendi.</p>");
    }

    // Rate Limit (Lockout) Check
    $stmtLock = $db->prepare("SELECT COUNT(*) as fails FROM login_attempts WHERE ip_address = ? AND success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmtLock->execute([$ip]);
    $lockRes = $stmtLock->fetch(PDO::FETCH_ASSOC);
    if ($lockRes && $lockRes['fails'] >= 5) {
        die("Güvenlik sebebiyle IP adresiniz 15 dakika boyunca kilitlenmiştir.");
    }

    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    $pin = $_POST['pin'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    // CSRF Check
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $db->prepare("INSERT INTO login_attempts (ip_address, success, failure_reason, created_at) VALUES (?, 0, 'CSRF Failed', NOW())")->execute([$ip]);
        $error = "Geçersiz istek (CSRF hatası).";
    } else {
        $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$user]);
        $adminInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($adminInfo && password_verify($pass, $adminInfo['password_hash'])) {
            $ga = new GoogleAuthenticator();
            $checkResult = $ga->verifyCode($adminInfo['MFA_SECRET'], $pin, 2);

            if ($checkResult) {
                session_regenerate_id(true);
                $_SESSION['kolms_admin_logged_in'] = true;
                $_SESSION['login_time'] = time();
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                // Audit Log Kaydı
                try {
                    $logStmt = $db->prepare("INSERT INTO admin_audit_log (admin_user, ip_address, action, created_at) VALUES (?, ?, 'login_success', NOW())");
                    $logStmt->execute([$user, $ip]);
                    $db->prepare("INSERT INTO login_attempts (ip_address, success, created_at) VALUES (?, 1, NOW())")->execute([$ip]);
                } catch(Exception $e){}

                header("Location: index.php");
                exit;
            } else {
                $db->prepare("INSERT INTO login_attempts (ip_address, success, failure_reason, created_at) VALUES (?, 0, 'Invalid TOTP', NOW())")->execute([$ip]);
                $error = "Hatalı Giriş veya Geçersiz Google Authenticator Kodu!";
            }
        } else {
            $db->prepare("INSERT INTO login_attempts (ip_address, success, failure_reason, created_at) VALUES (?, 0, 'Invalid Credentials', NOW())")->execute([$ip]);
            $error = "Hatalı Giriş veya Geçersiz Google Authenticator Kodu!";
        }
    }
} else {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="tr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KO-LMS | Secure Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #0f172a; color: #f8fafc; }
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center">
    <div class="glass-panel p-8 rounded-2xl shadow-2xl w-full max-w-md">
        <h1 class="text-3xl font-bold mb-6 text-center text-blue-400">KO-LMS <span class="text-white text-lg font-light">Secure</span></h1>
        
        <?php if($error): ?>
            <div class="bg-red-500/20 border border-red-500 text-red-300 p-3 rounded mb-4 text-sm text-center">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1 text-gray-300">Kullanıcı Adı</label>
                <input type="text" name="username" class="w-full bg-slate-800 border border-slate-700 rounded px-4 py-2 focus:outline-none focus:border-blue-500 text-white" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1 text-gray-300">Şifre</label>
                <input type="password" name="password" class="w-full bg-slate-800 border border-slate-700 rounded px-4 py-2 focus:outline-none focus:border-blue-500 text-white" required>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium mb-1 text-gray-300">Google Authenticator (TOTP) Kodu</label>
                <input type="text" name="pin" class="w-full bg-slate-800 border border-slate-700 rounded px-4 py-2 focus:outline-none focus:border-blue-500 text-white text-center tracking-widest text-lg" required autocomplete="off" maxlength="6" placeholder="000000">
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2 px-4 rounded transition-colors shadow-lg shadow-blue-500/30">
                Sisteme Giriş Yap
            </button>
        </form>
    </div>
</body>
</html>

