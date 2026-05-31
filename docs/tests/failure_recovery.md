# KO-LMS Operational Validation & Test Plan

Bu doküman, KO-LMS sisteminin `Controlled Pilot Production` aşamasından `Massive Unattended Production` aşamasına geçebilmesi için zorunlu olan operasyonel testlerin ve doğrulamaların listesini içerir.

## 1. Pilot Test Planı

### Pilot-1: Temel Çalışabilirlik (Manuel Gözetim)
- **Kapsam:** 1 batch, 4 container
- **Süre:** 6 saat kesintisiz çalışma
- **Beklentiler:**
  - Tüm logların ve `system_alerts` bildirimlerinin hatasız aktığının doğrulanması.
  - Manuel gözetim altında unexpected (beklenmeyen) hata olmaması.

### Pilot-2: Soak & Resilience
- **Kapsam:** 1 batch
- **Süre:** 24 saat soak (ıslatma/stres) testi
- **Beklentiler:**
  - Cron üzerinden `backup.sh` komutunun problemsiz çalışması.
  - `restore_test.sh` komutunun çalışması ve yedekten dönebiliyor olması.
  - `worker_heartbeats` sisteminin izlenmesi ve kopukluk yaşanmaması.

### Pilot-3: Yük Altında İzleme (Multi-Batch)
- **Kapsam:** 2 veya daha fazla batch eşzamanlı çalışma
- **Süre:** 48 saat
- **Beklentiler:**
  - CPU, RAM ve Disk (I/O) kullanımlarının stabil seyretmesi.
  - Memory leak (RAM şişmesi) olmaması.

---

## 2. Crash / Recovery (Hata Kurtarma) Testleri

Aşağıdaki şablonu kullanarak her bir test durumunu kaydedin ve başarılı olmadan Massive GO vermeyin.

### Test Şablonu

```text
Test adı: [Örn: host_worker process kill]
Tarih: 
Commit: 
Amaç: 
Komut: [Örn: kill -9 <pid>]
Beklenen sonuç: 
Gerçek sonuç: 
system_alerts kaydı: 
backup_runs kaydı: 
Başarılı/Başarısız: 
Not: 
```

### Zorunlu Test Senaryoları
1. `host_worker` process kill (Zorla Kapatma)
2. `orchestrator` restart (Süreci Yeniden Başlatma)
3. MySQL restart (Veritabanı Bağlantı Kopması)
4. Docker restart (Daemon Çökmesi)
5. Container kill (Manuel Oyun Kapatılması)
6. `action_queue` PROCESSING stuck (Zaman Aşımına Uğramış İşlem)
7. `backup.sh` failure simulation (Yedekleme Çökmesi)
8. `restore_test` dry-run (Veritabanı Geri Yükleme Pratiği)
9. Disk low space simulation (Diskte Yer Kalmaması)
10. Maintenance drain (Bakım Modu Geçişi)

---

## 3. NOC Panel / Pilot Checklist

Yeni bir sunucu başlatıldığında veya her büyük güncelleme sonrası şu liste doğrulanmalıdır:

- [ ] DB bağlantısı OK
- [ ] Docker daemon OK
- [ ] Overlay mounts OK
- [ ] `worker_heartbeats` güncel
- [ ] `action_queue` stuck yok
- [ ] `system_alerts` OPEN kritik yok
- [ ] son `backup.sh` başarılı
- [ ] son `restore_test` başarılı
- [ ] disk boş alan yeterli
- [ ] RAM/swap normal
- [ ] current commit doğru

---

## 4. Massive Unattended İçin Minimum Kanıt Gereksinimleri

Massive (başıboş/otonom) üretime geçmek için yukarıdaki testlerin yanı sıra asgari olarak şu performans kanıtlanmalıdır:

- 72 saat kesintisiz soak test (başarılı)
- En az 3 başarılı `restore_test` (veri bütünlüğü doğrulanmış)
- En az 1 MySQL restart recovery testi (başarılı)
- En az 1 Docker restart recovery testi (başarılı)
- En az 1 `host_worker` crash recovery testi (başarılı)
- En az 1 maintenance workflow success (bakım modu senaryosu)
- `0` critical unresolved alert (çözülmemiş kritik alarm sıfır olmalı)
- Backup success rate > %95
- `action_queue` stuck recovery success (askıda kalan işlemler kurtarılmalı)

**Dikkat:** Bu çıktılar olmadan "Massive Unattended GO" kararı alınmamalıdır.
