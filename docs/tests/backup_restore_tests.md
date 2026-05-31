# Backup & Restore Tests

## BR-001: backup.sh success test
- **Komutlar:**
  ```bash
  ./backup.sh
  ls -lh backups/
  sha256sum backups/*
  ```
- **SQL:**
  ```sql
  SELECT * FROM backup_runs ORDER BY started_at DESC LIMIT 10;
  ```
- **Pass kriteri:** backup_runs COMPLETED olmalı, size_bytes > 0, checksum_sha256 dolu olmalı, plain .sql dosyası kalmamalı.
- **Fail kriteri:** plain dump kalması, backup_runs FAILED.

## BR-002: backup.sh failure simulation
- **Komutlar:**
  ```bash
  # Veritabanı şifresini geçici değiştirip scripti çalıştır
  ./backup.sh
  ```
- **SQL:**
  ```sql
  SELECT * FROM system_alerts WHERE alert_type LIKE '%BACKUP%' ORDER BY created_at DESC LIMIT 10;
  ```
- **Pass kriteri:** Script fail eder ve system_alerts CRITICAL kayıt üretir.
- **Fail kriteri:** Hata yutulur.

## BR-003: restore_test.sh dry-run
- **Komutlar:**
  ```bash
  ./restore_test.sh
  ```
- **Pass kriteri:** restore_test test DB’ye import yapmalı, kritik tabloları doğrulamalı. backup_runs RESTORE_TESTED kaydı güncellenmeli.
- **Fail kriteri:** restore import failure.

## BR-004: backup artifact integrity check
- **Komutlar:**
  ```bash
  sha256sum -c <(echo "HASH  backups/file.tar.gz")
  ```
