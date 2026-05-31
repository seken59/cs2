# Backup Restore Runbook

## Amaç
Oluşan bir yedekleme (backup) dosyasından sistemi eski sağlıklı haline getirmek.

## Ne zaman kullanılır?
- Veritabanı bozulduğunda
- Disk sıfırlandığında

## Risk seviyesi
YÜKSEK (Mevcut tüm veriler silinecek)

## Ön koşullar
`backups/` klasöründe geçerli bir SQL dump dosyası olması.

## Adım adım komutlar
```bash
# 1. Servisleri durdur
systemctl stop kolms-orchestrator
systemctl stop kolms-host-worker

# 2. Mevcut DB'yi uçur ve yenisini aç
mysql -e "DROP DATABASE lisa_db; CREATE DATABASE lisa_db;"

# 3. Yedeği yükle
mysql lisa_db < backups/latest_backup.sql

# 4. Servisleri başlat
systemctl start kolms-orchestrator
```

## DB doğrulama sorguları
```sql
SELECT COUNT(*) FROM accounts;
SELECT COUNT(*) FROM system_settings;
```

## Log doğrulama komutları
N/A

## Beklenen sonuç
Yedekteki tüm hesapların geri gelmesi.

## Rollback / cleanup
Yanlış yükleme yapılırsa eski DB dump'ına tekrar dönülür.

## Alert / audit kontrolü
system_alerts tablosunda RESTORE_COMPLETED görülmeli.

## Başarılı sayılma kriteri
Sistemin datasız sıfırdan problemsiz açılması.

## Başarısız sayılma kriteri
SQL syntax hataları vermesi.
