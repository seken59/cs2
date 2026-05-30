# Görüşme Kayıtları (Yeni Dönem)

- **[AŞAMA 1 - SIFIRLAMA]** Kullanıcı her şeyi sıfırlayıp yeni bir "Master Prompt" verdi: AlmaLinux 9, 16GB RAM, Xvfb + Wine/Proton, Batch=2, node-steam-user ve node-globaloffensive ile CS2 otonom farmı.
- **[AŞAMA 2 - MİMARİ REVİZYONU]** Sistem analiz edildi. 
  1. Steam IPC çakışmasını önlemek için Steam Native Linux Client kararı alındı (Wine iptal edildi). 
  2. Ölüm maçında 0 skorun 0 XP vereceği ve Yoldaş (Wingman) modunda 2 botun griefing banı yiyeceği tespit edilip, sistem **4-Bot Wingman Boost (Karşılıklı Eşleşme)** stratejisine geçirildi.
- **[AŞAMA 3 - DİSK KRİZİ ÇÖZÜMÜ]** Kullanıcıdan gelen "df -h" görüntüsüyle kök dizinin (/root) 70GB, /home dizininin 162GB olduğu anlaşıldı. Tüm farm altyapısı `/opt` dizininden `/home` dizinine kaydırıldı. Read-Only Volume (Ortak CS2 klasörü) onaylandı.
- **[AŞAMA 4 - DONANIM TEYİDİ]** Kullanıcı sunucu paketini attı: Xeon E5 8 Çekirdek (X5670 & E5-26xx V2-V4), 16GB DDR3/4 RAM, DGN lokasyon (Düşük ping için çok iyi) ve 240GB disk. Bu donanım 4 botluk (Batch=4) fps_max 10 stratejimizle %100 uyumludur.
