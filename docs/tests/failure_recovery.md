# Failure Recovery Tests

## FR-001: host_worker process kill
- **Test ID:** FR-001
- **Risk seviyesi:** Orta
- **Hedef bileşen:** host_worker
- **Ön koşullar:** En az 1 PENDING veya PROCESSING action olmalı.
- **Komutlar:**
  ```bash
  pgrep -f host_worker.js
  kill -9 <pid>
  sleep 120
  systemctl restart kolms-host-worker
  ```
- **Beklenen davranış:** worker heartbeat kesintisi alert oluşturur. worker tekrar başladığında heartbeat güncellenir.
- **DB doğrulama sorguları:**
  ```sql
  SELECT * FROM worker_heartbeats WHERE worker_name='host_worker' ORDER BY heartbeat_at DESC LIMIT 5;
  ```
- **Log doğrulama komutları:** `journalctl -u kolms-host-worker -n 50`
- **system_alerts kontrolü:** CRITICAL alert
- **Pass kriteri:** 5 dakika içinde host_worker geri döner
- **Fail kriteri:** manuel DB müdahalesi gerekir
- **Test sonucu / Yapan / Commit / Tarih:** [Sonuç Alanı]

## FR-002: orchestrator restart
- **Test ID:** FR-002
- **Komutlar:** `systemctl restart kolms-orchestrator`
- **Pass kriteri:** Yeni batchler aksamadan başlar.

## FR-003: MySQL restart
- **Test ID:** FR-003
- **Komutlar:** `systemctl restart mysqld`
- **DB doğrulama sorguları:** `SELECT 1;`
- **Pass kriteri:** Worker/orchestrator toparlanmalı. DB bağlantı hatası alert üretmeli. Sonra recovered olmalı.

## FR-004: Docker restart
- **Test ID:** FR-004
- **Komutlar:** `systemctl restart docker`
- **Pass kriteri:** Container state ve action_queue tutarlılığı bozulmamalı.

## FR-005: container kill
- **Test ID:** FR-005
- **Komutlar:** `docker kill <container_name>`
- **Pass kriteri:** Orchestrator heartbeat eksikliğini fark edip STALE/ABORTING moduna çeker.

## FR-006: action_queue PROCESSING stuck recovery
- **Test ID:** FR-006
- **Risk seviyesi:** Yüksek
- **Hedef bileşen:** action_queue / host_worker
- **Ön koşullar:** İşlemde olan bir action
- **Komutlar / DB Doğrulama:**
  ```sql
  UPDATE action_queue
  SET status='PROCESSING',
      locked_until=DATE_SUB(NOW(), INTERVAL 10 MINUTE),
      worker_id='test-worker'
  WHERE id = <ACTION_ID>;
  
  SELECT * FROM action_queue WHERE id=<ACTION_ID>;
  ```
- **Beklenen davranış:** host_worker stuck PROCESSING action’ı yeniden alır veya max_retry sonrası FAILED yapar. system_alerts ACTION_FAILED_MAX_RETRY üretir.
- **Pass kriteri:** Action FAILED veya PENDING olup işlenir.

## FR-007: backup.sh failure simulation
- **Test ID:** FR-007
- **Komutlar:** `mv /usr/bin/mysqldump /usr/bin/mysqldump_bak; ./backup.sh`
- **Pass kriteri:** backup_runs FAILED kaydı, alert CRITICAL.

## FR-008: restore_test dry-run
- **Test ID:** FR-008
- **Komutlar:** `./restore_test.sh`
- **Pass kriteri:** RESTORE_TESTED statusu DB'ye işlenir.

## FR-009: disk low space simulation
- **Test ID:** FR-009
- **Komutlar:** `fallocate -l 50G /home/cs2_write/dummy_file`
- **Pass kriteri:** Disk alarmı tetiklenir (CRITICAL alert).

## FR-010: maintenance drain
- **Test ID:** FR-010
- **Komutlar:** `UPDATE system_settings SET setting_value='1' WHERE setting_key='maintenance_mode';`
- **Pass kriteri:** Aktif batchler biter, yenisi başlamaz.
