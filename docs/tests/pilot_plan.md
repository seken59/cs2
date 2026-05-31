# Pilot Test Plan

Sistemin otonom operasyonlara açılmadan önce kontrollü izleme altında doğrulanması.

## Pilot-1: Temel Çalışabilirlik
- **Kapsam:** 1 batch / 4 container
- **Süre:** 6 saat çalışma (manuel gözetim altında)
- **İzleme Komutları:**
  ```bash
  docker stats --no-stream
  df -h
  free -h
  du -sh /home/cs.serkaneken.com/cs2_write/*
  ```
- **SQL Doğrulama:**
  ```sql
  SELECT * FROM system_alerts WHERE status='OPEN' ORDER BY created_at DESC;
  SELECT * FROM action_queue WHERE status IN ('PENDING','PROCESSING') ORDER BY updated_at DESC;
  SELECT * FROM worker_heartbeats ORDER BY heartbeat_at DESC;
  SELECT * FROM backup_runs ORDER BY started_at DESC LIMIT 5;
  ```
- **Pass Kriteri:**
  - unresolved CRITICAL alert yok
  - action_queue PROCESSING stuck yok
  - worker heartbeat aktif
  - backup/restore hatası yok
- **Fail Kriteri:**
  - manuel DB müdahalesi gerekmesi
  - container restart loop
  - unresolved CRITICAL alert

## Pilot-2: Soak & Resilience
- **Kapsam:** 1 batch döngüsü
- **Süre:** 24 saat soak
- **Metrikler:**
  - CPU, RAM, Swap, Disk
  - upperdir boyutu
  - backup_runs, system_alerts, worker_heartbeats
- **GO/NO-GO Kriteri:** Sistem müdahalesiz 24 saati hatasız tamamlamalıdır.

## Pilot-3: Yük Altında İzleme (Multi-Batch)
- **Kapsam:** 2+ batch
- **Süre:** 48 saat multi-batch
- **Metrikler:** CPU load, RAM tüketimi, Disk I/O, DB query gecikmeleri.
- **Pass/Fail Kriteri:** 48 saat boyunca stuck action oluşmamalı, memory leak gözlenmemelidir.
- **Sonuç Şablonu:** `docs/tests/results/TEMPLATE.md` kullanılarak belgelenmelidir.
