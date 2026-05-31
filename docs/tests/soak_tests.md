# Soak Tests

## ST-001: 24 saat single-batch soak
- **Test ID:** ST-001
- **Amaç:** Tek batch ile memory leak ve stabilite kontrolü.
- **Süre:** 24 saat single-batch soak
- **Kapsam:** 1 batch (4 container)
- **Ön koşullar:** Sistem temiz, alert yok.
- **Başlatma komutları:** N/A (Orchestrator otomatik başlatır)
- **İzleme komutları:**
  ```bash
  docker stats --no-stream
  docker ps
  df -h
  free -h
  uptime
  journalctl -u docker --since "1 hour ago" --no-pager
  ```
- **SQL doğrulama sorguları:**
  ```sql
  SELECT COUNT(*) FROM system_alerts WHERE severity='CRITICAL' AND status='OPEN';
  SELECT status, COUNT(*) FROM action_queue GROUP BY status;
  SELECT worker_name, heartbeat_at, status FROM worker_heartbeats ORDER BY heartbeat_at DESC;
  SELECT status, COUNT(*) FROM farm_batches GROUP BY status;
  ```
- **Başarı kriteri:** Sistem 24 saati müdahalesiz tamamlar, alert oluşmaz.
- **Başarısızlık kriteri:** Memory leak (OOM), stuck container.
- **Cleanup:** Batch tamamlanmasını bekle, logları temizle.
- **Sonuç dosyası yolu:** `docs/tests/results/2026-xx-xx_24h_soak.md`

## ST-002: 48 saat multi-batch soak
- **Test ID:** ST-002
- **Amaç:** Yüksek yük altında 48 saat multi-batch test.
- **Süre:** 48 saat
- **Kapsam:** 2+ batch
- **Ön koşullar:** ST-001 Pass edilmiş olmalı.
- **Başlatma/İzleme:** ST-001 ile aynı.
- **Başarı kriteri:** Batchler stabil başlar ve biter.
- **Başarısızlık kriteri:** action_queue yığılması.
- **Cleanup:** DB temizliği (opsiyonel).
- **Sonuç dosyası yolu:** `docs/tests/results/2026-xx-xx_48h_multibatch.md`

## ST-003: 72 saat readiness soak
- **Test ID:** ST-003
- **Amaç:** Massive Unattended Production readiness.
- **Süre:** 72 saat
- **Kapsam:** Tam kapasite
- **Ön koşullar:** ST-002 Pass.
- **Sonuç dosyası yolu:** `docs/tests/results/2026-xx-xx_72h_readiness.md`
