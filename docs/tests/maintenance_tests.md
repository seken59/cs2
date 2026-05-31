# Maintenance Tests

## MT-001: maintenance_mode enable
- **Komutlar:**
  ```sql
  UPDATE system_settings SET setting_value='1' WHERE setting_key='maintenance_mode';
  SELECT * FROM system_settings WHERE setting_key='maintenance_mode';
  ```
- **Pass kriteri:** maintenance_mode aktif edilir.

## MT-002: new batch prevention
- **Komutlar:**
  ```sql
  SELECT status, COUNT(*) FROM farm_batches GROUP BY status;
  ```
- **Pass kriteri:** maintenance_mode=1 iken yeni batch başlamamalı.

## MT-003: active batch drain
- **Komutlar:**
  ```sql
  SELECT status, COUNT(*) FROM action_queue GROUP BY status;
  ```
- **Pass kriteri:** aktif batchler drain/stop olmalı.

## MT-004: overlay remount verification
- **Host kontrolleri:**
  ```bash
  mount | grep cs2_merged
  docker ps
  ```
- **Pass kriteri:** overlay mountlar doğrulanmalı.

## MT-005: maintenance rollback/failure
- **Komutlar:**
  ```sql
  UPDATE system_settings SET setting_value='COMPLETED' WHERE setting_key='maintenance_mode';
  ```
- **Pass kriteri:** maintenance sonrası smoke test geçmeli.
