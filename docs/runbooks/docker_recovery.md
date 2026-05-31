# Docker Recovery Runbook

## Amaç
Docker Daemon çöktüğünde veya yanıt vermediğinde (hang) kurtarmak.

## Ne zaman kullanılır?
- `docker ps` komutu donduğunda

## Risk seviyesi
YÜKSEK (Mevcut çalışan tüm farm'lar iptal olur)

## Ön koşullar
Orchestrator'ı durdurmak.

## Adım adım komutlar
```bash
systemctl restart docker
docker system prune -f
```

## DB doğrulama sorguları
```sql
UPDATE farm_batches SET status='FAILED' WHERE status='RUNNING';
```

## Log doğrulama komutları
```bash
journalctl -u docker -f
```

## Beklenen sonuç
Docker'ın temizlenip yeniden başlaması.

## Rollback / cleanup
N/A

## Alert / audit kontrolü
N/A

## Başarılı sayılma kriteri
`docker ps` komutunun anında yanıt vermesi.

## Başarısız sayılma kriteri
Docker'ın dead lock'ta kalması.
