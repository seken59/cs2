# MySQL Recovery Runbook

## Amaç
MySQL veritabanı servisi çöktüğünde güvenli bir şekilde ayağa kaldırmak.

## Ne zaman kullanılır?
- MySQL "Connection Refused" veya "Too many connections" verdiğinde

## Risk seviyesi
ORTA

## Ön koşullar
N/A

## Adım adım komutlar
```bash
systemctl restart mysqld
```

## DB doğrulama sorguları
```sql
SELECT 1;
```

## Log doğrulama komutları
```bash
tail -n 100 /var/log/mysqld.log
```

## Beklenen sonuç
MySQL'in temiz şekilde yeniden başlaması. Worker ve Orchestrator'ın kaldığı yerden bağlanması.

## Rollback / cleanup
N/A

## Alert / audit kontrolü
system_alerts tablosunda DB_DISCONNECT alertleri çözülmüş (RESOLVED) olmalı.

## Başarılı sayılma kriteri
Sistemin kesintisiz devam etmesi.

## Başarısız sayılma kriteri
MySQL'in corrupt table hatası vermesi.
