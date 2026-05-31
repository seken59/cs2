Test ID: PREFLIGHT-001
Test adı: Pilot-0 Preflight Check
Tarih: 2026-05-31
Başlangıç saati: 
Bitiş saati: 
Commit: e7f53a669d67f6965fc8a2df03d1781738cf3abd
Branch: main
Ortam: Production / Staging
Testi yapan kişi: 
Amaç: Sistemin Pilot testlerine başlamadan önce genel sağlığının doğrulanması.
Ön koşullar: Sunucu açık, tüm servisler (Docker, MySQL, host_worker, orchestrator) kurulu olmalı.

Komutlar / Checklist:
- [ ] `mysql -u root -p -e "SELECT 1;"` (DB bağlantısı OK)
- [ ] `docker ps` (Docker daemon OK)
- [ ] `mount | grep cs2_merged` (Overlay mounts OK)
- [ ] `docker info | grep 'Storage Driver: overlay2'` (Storage Overlay2 check)
- [ ] `df -h` (Disk boş alan >20GB olmalı)
- [ ] `free -h` (RAM/Swap normal seviyelerde)

Beklenen sonuç: Tüm bileşenlerin hatasız yanıt vermesi.
Gerçek sonuç: 

DB çıktıları:
- worker_heartbeats:
- system_alerts: (CRITICAL OPEN alert olmamalı)
- action_queue: (stuck action olmamalı)
- backup_runs: 

Log çıktıları:
- `journalctl -u docker --no-pager -n 20`:
- `tail -n 20 /var/log/kolms/orchestrator.log`:

system_alerts: 
action_queue: 
worker_heartbeats: 
backup_runs: 

Metrikler:
- CPU: 
- RAM: 
- Swap: 
- Disk: 
- Docker container count: 

Pass/Fail: 
Cleanup: Yok (Okuma odaklı kontroller)

Notlar:
Ek dosyalar / kanıtlar:
