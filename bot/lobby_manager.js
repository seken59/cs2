const { execSync } = require('child_process');

/**
 * Xdotool kullanarak 640x480 çözünürlükte CS2 lobisini kurar.
 * 4-Bot stratejisinde, botlar UI'a tıklayarak Yoldaş eşleştirmesini başlatır.
 */

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// 640x480 Koordinatları (Varsayımsal, gerçek sunucuda kalibre edilmelidir)
const COORDS = {
    PLAY_BUTTON: 'xdotool mousemove 100 50 click 1',
    MATCHMAKING_TAB: 'xdotool mousemove 200 80 click 1',
    WINGMAN_TOGGLE: 'xdotool mousemove 300 120 click 1',
    GO_BUTTON: 'xdotool mousemove 500 400 click 1',
    ACCEPT_MATCH: 'xdotool mousemove 320 240 click 1'
};

async function createLobbyAndSearch() {
    console.log(`[LOBBY] CS2 Ana menü bekleniyor...`);
    await delay(30000); // Oyunun açılmasını bekle

    console.log(`[LOBBY] Oyna (Play) butonuna tıklanıyor...`);
    execSync(COORDS.PLAY_BUTTON);
    await delay(2000);

    console.log(`[LOBBY] Eşleştirme (Matchmaking) sekmesi seçiliyor...`);
    execSync(COORDS.MATCHMAKING_TAB);
    await delay(2000);

    console.log(`[LOBBY] Yoldaş (Wingman) moduna geçiliyor...`);
    execSync(COORDS.WINGMAN_TOGGLE);
    await delay(2000);

    console.log(`[LOBBY] Eşleşme Araması (GO) başlatılıyor...`);
    execSync(COORDS.GO_BUTTON);
    
    // Maç bulma ve kabul etme döngüsü
    console.log(`[LOBBY] Maç aranıyor. Kabul (Accept) butonuna basmak için döngüye girildi.`);
    for (let i = 0; i < 30; i++) {
        // Her 5 saniyede bir ekranın ortasındaki Accept butonuna tıkla
        try {
            execSync(COORDS.ACCEPT_MATCH);
        } catch(e) {}
        await delay(5000);
    }
    
    console.log(`[LOBBY] Maça girildiği varsayılıyor. AFK engelleme (Anti-AFK) başlatılıyor.`);
    antiAFK();
}

async function antiAFK() {
    console.log(`[LOBBY] Gelişmiş İnsan Simülasyonu (Entropy) başlatıldı. Rastgele WASD basılacak.`);
    const keys = ['w', 'a', 's', 'd', 'space', 'ctrl'];
    
    // Maç süresince (yaklaşık 15 dakika) döngüde kal
    const endTime = Date.now() + (15 * 60 * 1000); 

    while (Date.now() < endTime) {
        try {
            // Rastgele bir tuş seç
            const randomKey = keys[Math.floor(Math.random() * keys.length)];
            // Rastgele basılı tutma süresi (200ms ile 1500ms arası)
            const pressDuration = Math.floor(Math.random() * 1300) + 200;
            
            // Tuşa basılı tutma simülasyonu (xdotool keydown/keyup)
            execSync(`xdotool keydown ${randomKey}`);
            await delay(pressDuration);
            execSync(`xdotool keyup ${randomKey}`);

            // İnsan gibi bir sonraki hamle için rastgele bekleme (500ms ile 4000ms arası)
            const idleTime = Math.floor(Math.random() * 3500) + 500;
            await delay(idleTime);

        } catch(e) {
            // xdotool bazen hata verebilir, sistemi çökertmemesi için yakalıyoruz
        }
    }
}

module.exports = { createLobbyAndSearch };

