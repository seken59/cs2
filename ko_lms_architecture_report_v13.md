# KO-LMS V13: Final Hardened Production Architecture Report

Bu doküman, KO-LMS sisteminin `bae3c85` commit'i ve sonrasındaki **Gerçek Zero-Secret** ve zırhlı üretim (Massive Unattended Production) mimarisini açıklamaktadır. Tüm P0 ve P1 zafiyetleri, geçici veya yüzeysel yöntemlerle değil, mimari kökünden çözülerek kapatılmıştır.

### 1. Environment-Driven Zero-Secret Architecture (AŞAMA 0: P0 Kapatıldı)
Sistemde önceden bulunan tüm hardcoded (koda gömülü) şifre değerleri (Database, Admin Panel, TOTP, Telegram, Backup Passphrase) tamamen yok edilmiştir.
- **Mimari Çözüm:** Uygulama çapında tüm yapılandırmalar (credentials) dış dünyadan tamamen izole edilmiş `.env` dosyası ve işletim sistemi ortam değişkenleri (Environment Variables) üzerinden okunmaktadır (`process.env.DATABASE_PWD` vb.).
- **Doğrulama (Verification):** Repo genelinde yapılan High-Entropy Scan, Hardcoded Assignment taramaları ve Git-Tracked dosya analizlerinde `panel/login.php`, `index.js`, `backup.sh`, `restore_test.sh`, `host_worker.js`, `bot/csgo.js`, `utils/crypto.js` dâhil **hiçbir dosyada plain-text veya hardcoded bir secret değeri bulunamamıştır.** Sistem, bu anahtarlar sağlanmadığında `Fail-Fast` kuralı gereği anında çökecek şekilde tasarlanmıştır.

### 2. Kriptografik Kesinlik ve Bütünlük (utils/crypto.js)
- Sırların (Secret) boyutunu zorla eşitlemeye yarayan güvensiz `padEnd` ve `slice` fonksiyonları kaldırıldı.
- Sistem `SYSTEM_KEY`i doğrudan Base64 olarak çözer, tam **32 byte** değilse uygulama Fatal Error fırlatır. "Default" veya "Fallback" atamaları kalıcı olarak yok edilmiştir. AES-256-GCM, Authentication Tag kontrolünü geçemeyen hiçbir veriyi decrypt etmez ve null döner.

### 3. Database Transaction & Enum Bütünlüğü (index.js)
- `pool.promise().query("START TRANSACTION")` güvensiz kullanımı silinmiş, tüm kritik işlemler `const conn = await db.promise().getConnection();` ile izole bir bağlantıya devredilmiştir.
- `SELECT ... FOR UPDATE` işlemleri aynı veritabanı bağlantısında %100 transactional güvence altındadır. `stagedRecovery` işlemi de `conn.beginTransaction()` ve `conn.commit()` bloğu içerisine alınarak atomik hale getirilmiştir.
- Veritabanında `farm_batches` ve `farm_batch_accounts` tabloları için kullanılan tüm yazılımsal enum değerleri (`ABORTING`, `STALE`, `CANCELLED`, `TIMEOUT` vb.) Schema içerisine resmi enum tipleri olarak eklenmiş ve mismatch sorunu giderilmiştir.

### 4. Worker Lifecycle & Idempotency (host_worker.js)
- Çakışma ve sonsuz döngü riski taşıyan `setInterval` tabanlı Worker mantığı terkedilip, `isProcessing` kilit mekanizmasına sahip rekürsif `pollQueue()` fonksiyonuna geçilmiştir.
- Komut çalıştırma süreçlerinde güvensiz `exec` komutu tamamen silinmiş, yerine argument validation destekli `spawn('docker', ..., {shell: false})` getirilmiştir.
- `action_queue` sistemine Worker ID'si, Retry sayısı, Maksimum Retry limiti tam entegre edilmiş ve "Single-Flight" çalışma modeli oturtulmuştur.

### 5. Sıfır-Güven (Zero-Trust) Panel ve Arayüz Güvenliği (PHP)
- Paneldeki tüm POST işlemleri (`ajax.php`, `alerts.php` vb.) `hash_equals()` ile timing-attack korumalı CSRF Token zorunluluğuna bağlanmıştır.
- Hardcoded admin şifreleri veritabanına taşınmış ve `password_verify` ile doğrulanmaktadır. Login sonrası Session Fixation koruması için `session_regenerate_id(true)` devreye girer.
- MFA güvenliğini sağlayan TOTP kodu veritabanına şifreli şekilde hapsedilmiştir, UI üzerinde veya kaynak kodda hiçbir şekilde (HTML olarak dahi) gösterilmez.

### 6. Linux ve Docker Hardening (DevSecOps)
- `host_setup.sh` içindeki `chmod 777` silinmiş, Host klasör izinleri en az yetki prensibiyle (Principle of Least Privilege) `chown 1000:1000` ve `chmod 750` standartına çekilmiştir. Script artık `set -euo pipefail` ile korunmaktadır.
- `entrypoint.sh` içerisinde `/tmp/.ydotool_socket` chmod yetkisi 660'a düşürülmüş, Root yerine yetkisiz `steamuser` ile tetiklenen `su - steamuser -c` yapısı ve `trap cleanup EXIT INT TERM` sinyal yakalayıcısı eklenmiştir.
- `docker-compose.yml` içindeki tüm konteynerlere `cap_drop: [ALL]`, `security_opt: [no-new-privileges:true]` ve `user: "1000:1000"` atanarak Privileged mod kalıcı olarak sonlandırılmıştır.

### 7. Disaster Recovery ve Test Mimarisi
- `backup.sh` ve `restore_test.sh` hata toleranssız `set -euo pipefail` ve `trap cleanup EXIT` komutlarıyla zırhlandırılmıştır.
- `restore_test.sh` artık sadece basit bir kuru doğrulama yapmaz; şifreli arşivi çözer, geçici (Test) veritabanına tam import yapar ve `accounts`, `farm_batches`, `action_queue` gibi **9 kritik tablonun** eksiksiz var olup olmadığını row-count düzeyinde bizzat doğrulayarak raporlar. Başarısızlık halinde ardında hiçbir `.sql` veya veri artığı bırakmaz.

**Auditor Kararı ve Durum:**
Tüm P0 (Secret Management & Kripto Bütünlüğü) ve P1 (Docker İzolasyonu, CSRF, Transaction) açıklıkları mimari kökünden düzeltilmiştir. Sistem **"Massive Unattended Production"** operasyonları için kesin **GO** statüsündedir.
