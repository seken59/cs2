# Failure Recovery Tests

## FR-001: host_worker process kill
Test ID: FR-001
Test adı: host_worker process kill
Risk seviyesi: Orta
Hedef bileşen: host_worker
Ön koşullar: En az 1 PENDING veya PROCESSING action olmalı.
Çalıştırılacak gerçek komutlar:
`ash
pgrep -f host_worker.js
kill -9 <pid>
sleep 120
systemctl restart kolms-host-worker # veya pm2 restart host_worker
`
Beklenen sistem davranışı:
- worker heartbeat kesintisi alert oluşturur
- worker tekrar başladığında heartbeat güncellenir
- PROCESSING action locked_until sonrası recover edilir
- max_retry aşılmadıkça action tekrar işlenir
DB doğrulama sorguları:
`sql
SELECT * FROM worker_heartbeats WHERE worker_name='host_worker' ORDER BY heartbeat_at DESC LIMIT 5;
SELECT * FROM action_queue ORDER BY updated_at DESC LIMIT 10;
SELECT * FROM system_alerts ORDER BY created_at DESC LIMIT 10;
`
Log doğrulama komutları:
`ash
tail -n 50 /var/log/kolms/host_worker.log
`
system_alerts kontrolü: CRITICAL Worker Heartbeat uyarısı beklenir.
action_queue kontrolü: PROCESSING olan eylem PENDING veya COMPLETED/FAILED'a dönmeli.
worker_heartbeats kontrolü: Kesinti sonrası STATUS=OK olmalı.
backup_runs kontrolü gerekiyorsa: N/A
Pass kriteri:
- 5 dakika içinde host_worker geri döner
- action_queue stuck kalmaz
- unresolved CRITICAL alert kalmaz
Fail kriteri:
- PROCESSING action sonsuza kadar kalır
- worker heartbeat güncellenmez
- manuel DB müdahalesi gerekir
Cleanup / rollback adımları:
- Test actionlarını COMPLETED/FAILED final state’e getir
- system_alerts ACK/RESOLVE yap

## Diğer Testler...
Gelecek testler TEMPLATE.md formatında results/ klasörüne eklenecek.
