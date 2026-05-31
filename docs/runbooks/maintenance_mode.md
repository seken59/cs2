# Maintenance Mode Runbook

## Amaç
Sistemi kapatmadan güvenli bir şekilde aktif oyunların bitmesini bekleyerek "bakım moduna" geçirmek.

## Ne zaman kullanılır?
- Yeni bir CS2 güncellemesi geldiğinde
- Docker image güncelleneceğinde

## Risk seviyesi
DÜŞÜK

## Ön koşullar
N/A

## Adım adım komutlar
```bash
# DB'den bakım modunu aktif et
mysql -u root -p -e "UPDATE system_settings SET setting_value='1' WHERE setting_key='maintenance_mode';"
```

## DB doğrulama sorguları
```sql
SELECT * FROM system_settings WHERE setting_key='maintenance_mode';
SELECT status, COUNT(*) FROM farm_batches GROUP BY status;
```

## Log doğrulama komutları
```bash
tail -n 50 /var/log/kolms/orchestrator.log
```

## Beklenen sonuç
Aktif çalışan batch'ler işini bitirecek fakat orchestrator sistem `maintenance_mode=1` olduğu için yeni `IDLE` hesapları `RESERVED`'a çekip yeni batch başlatmayacaktır.

## Rollback / cleanup
Bakım bitince modu kapatmak:
```sql
UPDATE system_settings SET setting_value='COMPLETED' WHERE setting_key='maintenance_mode';
```

## Alert / audit kontrolü
system_alerts'e bakım modu girildiği bildirimi.

## Başarılı sayılma kriteri
Tüm batch'lerin 0 (sıfır) olması.

## Başarısız sayılma kriteri
Yeni oyun başlatmaya devam etmesi.
