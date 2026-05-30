# Proje Başlangıç Analizi ve Strateji Önerileri (Aktif)

- **Sunucu & Donanım:** AlmaLinux 9, Xeon E5 8 Core, 16GB RAM, 240GB Disk, DGN Lokasyon (Düşük Ping/Verimli Eşleşme).
- **Teknoloji Yığını:** Docker (Konteyner İzolasyonu), Xvfb (Sanal Ekran), Mesa-Lavapipe (Yazılımsal Vulkan / GPU'suz CS2 Rendeleme), CS2 Native Linux Client.
- **Matchmaking (XP) Stratejisi:** Batch Size = 4. 2 lobi (2v2) aynı anda Wingman (Yoldaş) arayıp birbiriyle eşleşecek. CT takımı sürenin bitmesini bekleyerek raundu kazanıp (Time-Out XP) maçları tamamlayacak. Raporlama ve griefing ban riski %0.
- **Disk Stratejisi:** 40GB'lık ana CS2 klasörü `/home/cs2_master` dizinine kurulacak ve 4 bota `Read-Only Volume` olarak bağlanacak. Disk maliyeti 4 bot için sadece 40GB olacak.
- **Güvenlik (Anti-Ban):** 
  - Fiziksel Steam istemcisi ile Node.js Game Coordinator paketleri KESİNLİKLE aynı anda çalışmayacak. 
  - Login işlemleri ve lobi hareketleri `xdotool` kullanılarak %100 klavye/mouse simülasyonu ile yapılacak.
  - Maç içi AFK engelleme için konsol üzerinden rastgele tuş enjeksiyonları yapılacak.
  - **Macro Engine V2 (VACnet Bypass):** Botlar sadece ortada buluşup ölmez. Haritanın 4 farklı rotasından rastgele birini seçip yürür. Adım süreleri +- %10 Fuzzing (rastgelelik) içerir. Ateş ederken Spray (geniş açı) atarlar.
  - **Çoklu Harita Desteği:** Sistem tek bir haritaya bağımlı bırakılmadı. `de_boyard`, `de_chalice`, `de_vertigo`, `de_overpass`, ve `de_nuke` haritalarına ait rotalar ve pusma süreleri (ct_walk, t_walk, turn degrees) sisteme kodlandı. Botlar `console.log` üzerinden "Map:" yazısını gördüklerinde taktikleri dinamik değiştirir.
