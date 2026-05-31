# Action Queue Stuck Recovery Runbook

## Amaç
`action_queue` tablosunda `PROCESSING` statüsünde takılı kalmış komutları temizlemek.

## Ne zaman kullanılır?
- Bir docker stop emri uzun süredir bekliyorsa.

## Risk seviyesi
DÜŞÜK

## Ön koşullar
Host worker aktif olmalı.

## Adım adım komutlar
```bash
# N/A - DB üzerinden yapılır
```

## DB doğrulama sorguları
```sql
SELECT * FROM action_queue WHERE status='PROCESSING' AND locked_until < NOW();
SELECT * FROM system_alerts WHERE alert_type LIKE '%ACTION%' ORDER BY created_at DESC LIMIT 10;
```

## Log doğrulama komutları
```bash
tail -n 50 /var/log/kolms/host_worker.log
```

## Beklenen sonuç
Host worker'ın stuck action'ları fark edip otomatik yeniden PENDING'e alması veya max retry aşımında FAILED'a atması.

## Rollback / cleanup
Manuel müdahale gerekirse:
```sql
UPDATE action_queue SET status='PENDING', retry_count=0 WHERE status='PROCESSING' AND locked_until < NOW();
```

## Alert / audit kontrolü
system_alerts içerisinde ACTION_FAILED logu düşer.

## Başarılı sayılma kriteri
Stuck işlem sayısının 0 olması.

## Başarısız sayılma kriteri
Action tablosunda hala locked_until süresi geçmiş işlemlerin kalması.
