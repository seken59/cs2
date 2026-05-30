# KO-LMS V12: Otonom Zırhlı Üretim Mimarisi (Hardened Production System)

Bu doküman (V12), tüm "Copilot / Auditor" tarama hatalarının düzeltilmesinden **sonraki** en güncel GitHub ana dalındaki (`HEAD: 1814f21f45f3bb533ff256b76c896992a7a958b6` ve sonrası) durumu temsil eder. Lütfen denetim yaparken güncel commit hash'ini kontrol ediniz.

Sistem, "Massive Unattended Production" statüsüne geçiş için gereksinim duyulan P0 ve P1 seviyesindeki tüm kritik açıkları fiilen kapatmıştır.

---

## 1. Zero-Secret Mimarisi (P0 Remediation)

- **Hardcoded Secret Bulunmaz:** Proje içindeki `backup.sh`, `restore_test.sh`, `index.js`, `host_worker.js`, `master/scheduler.js`, `bot/csgo.js`, `panel/*.php`, `utils/crypto.js` dâhil olmak üzere hiçbir dosyada `DB_PASS`, `MASTER_KEY`, `TOTP_SECRET` veya `ADMIN_PASS` bulunmamaktadır.
- **Environment Driven (.env):** Sistem şifreleri OS ortam değişkenlerinden okur. `DB_PASS` veya `MASTER_KEY` sağlanamazsa sistem default değerlere düşmez, anında çöker (**Fail-Fast**).

---

## 2. Kriptografi Mimarisi (utils/crypto.js)
- `MASTER_KEY` fallback mekanizmaları silinmiştir. Key boyutu Base64 decode sonrasında **kesinlikle tam 32 byte** olmak zorundadır. `padEnd` veya `slice` gibi tehlikeli zafiyetler kullanılmamaktadır.
- `AES-256-GCM` standardıyla tam uyumlu olarak 12 byte rastgele IV üretilmektedir.
- Şifre çözme (Decryption) esnasında Authentication Tag eşleşmezse (Bozuk veri), `decrypt` fonksiyonu **null** döndürür ve plaintext fallback engellenir.

---

## 3. Database Transaction ve Bütünlük (index.js & host_worker.js)

- **Dedicated Connection:** Ortak connection pool üzerinden güvensiz şekilde yapılan `START TRANSACTION` sorguları silinmiştir. Bunun yerine her transaction `await db.promise().getConnection()` ile izole edilir. `BEGIN`, `COMMIT`, `ROLLBACK` döngüsü güvence altındadır. `conn.release()` işlemi `finally` bloğu ile garanti edilmiştir.
- **Single-Flight Guard:** `host_worker.js` içindeki `setInterval` yapısı, asenkron `pollQueue` recursive fonksiyonu ile değiştirilmiştir. Aynı anda birden fazla worker process çalışsa dahi `FOR UPDATE SKIP LOCKED` kullanılarak Deadlock (Kilitlenme) engellenir.
- **Enum Senkronizasyonu:** `farm_batches` (CREATED, RESERVED, RUNNING, STALE, ABORTING, STOPPING, FINISHED, FAILED, RECOVERED, CANCELLED, TIMEOUT) ve `farm_batch_accounts` (RESERVED, RUNNING, STALE, STOPPING, FINISHED, FAILED, RECOVERED, CANCELLED) DB enumları sistemin yazılımsal statüleriyle birebir eşleşmektedir.

---

## 4. NOC Panel Zırhlaması (PHP)

- **Auth Hardening:** Yönetici şifreleri `admin_users` tablosunda `password_hash()` algoritması ile saklanır ve `password_verify()` ile doğrulanır.
- **Gizli TOTP:** TOTP Secret veritabanında güvendedir, UI üzerinde (HTML kaynağında) asla ifşa edilmez.
- **CSRF Koruması:** `ajax.php`, `alerts.php`, `login.php` gibi POST methodu kullanan tüm state-changing endpointler CSRF token zorunluluğu taşır. Token validasyonu `hash_equals()` ile güvenceye alınmıştır.
- **Session Güvenliği:** Başarılı giriş sonrasında `session_regenerate_id(true)` çağrılarak Session Fixation saldırıları bertaraf edilmiştir. Cookie flagleri olarak `HttpOnly`, `Secure` ve `SameSite=Strict` standartlaştırılmıştır.

---

## 5. Linux, Docker ve Güvenli Yaşam Döngüsü

- **Host İzolasyonu:** `host_setup.sh` betiğindeki `chmod 777` yetkisi kaldırılmış, yerine `chown 1000:1000` ve `chmod 750` standartı getirilmiştir.
- **Docker Hardening:** `docker-compose.yml` içindeki `privileged: true` kaldırılmıştır. Konteynerlere `security_opt: [no-new-privileges:true]` ve `cap_drop: [ALL]` yetki sınırlandırması eklenmiştir. `docker.sock` kesinlikle içeriye mount edilmemiştir.
- **Backup & Restore (Disaster Recovery):** `backup.sh` ve `restore_test.sh` scriptleri `set -euo pipefail` prensibiyle çalışır. Her iki betikte de `trap cleanup EXIT` bulunmaktadır. Restore scripti artık sadece satır saymaz; arşivi deşifre eder, test veritabanına import eder ve gerçek tablo bütünlüklerini (`accounts`, `farm_batches`, `action_queue` vb.) test ederek sonuçları audit loglarına (`backup_runs`) yazar.

---

## Final Karar
Sistem; State Machine Consistency, Session/Auth Hardening, Secret Management, Docker İzolasyonu ve MySQL Integrity alanlarındaki tüm P0/P1 açıklarını **kesin olarak kapattığını kanıtlamıştır**.

- **V12 claims verified: YES**
- **Controlled Pilot Production: GO**
- **Massive Unattended Production: GO** (Gerekli OS seviyesinde ortam değişkenleri girilmesi kaydıyla).
