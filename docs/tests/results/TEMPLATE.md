Test ID: FR-XXX
Test adı: [Test Adı]
Risk seviyesi: [Düşük/Orta/Yüksek]
Hedef bileşen: [Örn: host_worker, MySQL, action_queue]
Ön koşullar: [Örn: Sistem idle, En az 1 PENDING action]

Çalıştırılacak gerçek komutlar:
`ash
# Komutlar buraya
`

Beklenen sistem davranışı:
- Davranış 1
- Davranış 2

DB doğrulama sorguları:
`sql
-- SQL sorguları buraya
`

Log doğrulama komutları:
`ash
-- Log komutları buraya
`

system_alerts kontrolü: [Açıkla]
action_queue kontrolü: [Açıkla]
worker_heartbeats kontrolü: [Açıkla]
backup_runs kontrolü gerekiyorsa: [Açıkla]

Pass kriteri:
- 

Fail kriteri:
- 

Cleanup / rollback adımları:
`ash
# Adımlar
`

Test sonucu alanı: [PASS/FAIL/SKIP]
Testi yapan kişi: [İsim]
Commit hash: [Hash]
Tarih/saat: [Tarih]
