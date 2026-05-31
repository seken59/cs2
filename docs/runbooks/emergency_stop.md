# Emergency Stop Runbook

## Amaç
Sistemi acil durumlarda (hack şüphesi, db corruption, OOM) güvenli bir şekilde kapatmak.

## Ne zaman kullanılır?
- CPU/RAM %100'e vurup kilitlendiğinde
- Yetkisiz erişim şüphesinde

## Risk seviyesi
YÜKSEK (Tüm aktif maçlar ve droplar yanabilir)

## Ön koşullar
Sisteme root / admin erişimi.

## Adım adım komutlar
```bash
# 1. Orchestrator ve Worker'ı durdur
systemctl stop kolms-orchestrator
systemctl stop kolms-host-worker

# 2. Tüm çalışan docker konteynerleri anında kill et
docker kill $(docker ps -q)

# 3. Tüm action queue sıfırla
mysql -u root -p -e "UPDATE action_queue SET status='FAILED' WHERE status IN ('PENDING', 'PROCESSING');"
```

## DB doğrulama sorguları
```sql
SELECT status, COUNT(*) FROM action_queue GROUP BY status;
```

## Log doğrulama komutları
```bash
docker ps
```

## Beklenen sonuç
Hiçbir çalışan docker container kalmamalı, action_queue boş/FAILED olmalı.

## Rollback / cleanup
Sistemi açmak için `systemctl start kolms-orchestrator`

## Alert / audit kontrolü
system_alerts tablosuna MANUAL_EMERGENCY_STOP kaydı düşülür.

## Başarılı sayılma kriteri
Tüm operasyonun durması.

## Başarısız sayılma kriteri
Container'ların kapanmaması.
