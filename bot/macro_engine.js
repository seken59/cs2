const fs = require('fs');
const { execSync } = require('child_process');

/**
 * MACRO ENGINE V2 (MULTI-PROBABILITY & FUZZING)
 * VACnet'in "Isı Haritası" (Heatmap) analizini kör etmek için tasarlandı.
 * Botlar her raund aynı noktada buluşmaz. Raund numarasına göre 4 farklı rotadan
 * birini seçerler. Hareket süreleri (milisaniye) "%10 Fuzzing" (sapma) ile rastgeleleştirilir.
 * Vuruşlar nokta atışı değil, "Spray & Sweep" (geniş açılı tarama) ile yapılır.
 */

const LOG_FILE = '/home/steamuser/cs2_merged/game/csgo/console.log';
const botId = parseInt(process.env.BOT_ID);

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// İki farklı konteynerin AYNI rotayı seçmesi için raund numarasını seed alan rastgele sayı üretici
function seededRandom(seed) {
    let x = Math.sin(seed++) * 10000;
    return x - Math.floor(x);
}

// Haritalara Özel Olası Rotalar ve Fuzzing Süreleri (Kalibre Edilecektir)
const MAP_ROUTES = {
    "de_boyard": [
        { name: "Orta Hızlı Tünel", ct_walk: 3000, t_walk: 3500, turn: 45 },
        { name: "Sağ Açık Alan", ct_walk: 4000, t_walk: 2500, turn: -45 },
        { name: "Sol Kapı Arkası", ct_walk: 2000, t_walk: 4000, turn: 90 },
        { name: "Spawn İçi", ct_walk: 1000, t_walk: 5000, turn: 0 }
    ],
    "de_chalice": [
        { name: "Ana Avlu", ct_walk: 4500, t_walk: 4500, turn: 90 },
        { name: "Yan Koridor", ct_walk: 5000, t_walk: 3000, turn: -90 },
        { name: "Uzun Köşe", ct_walk: 3000, t_walk: 6000, turn: 45 },
        { name: "Hızlı Pus", ct_walk: 2000, t_walk: 7000, turn: 0 }
    ],
    "de_vertigo": [
        { name: "B Merdiven", ct_walk: 5000, t_walk: 6000, turn: 180 },
        { name: "Kısa Orta", ct_walk: 4000, t_walk: 4000, turn: 90 },
        { name: "Asansör Boşluğu", ct_walk: 3000, t_walk: 7000, turn: -90 },
        { name: "Köşe Pusu", ct_walk: 1500, t_walk: 8000, turn: 45 }
    ],
    "de_overpass": [
        { name: "Monster", ct_walk: 6000, t_walk: 5000, turn: 90 },
        { name: "Su Kanalı", ct_walk: 5000, t_walk: 6000, turn: -90 },
        { name: "Kısa Boru", ct_walk: 4000, t_walk: 4000, turn: 180 },
        { name: "B Alanı", ct_walk: 2000, t_walk: 7000, turn: 0 }
    ],
    "de_nuke": [
        { name: "Rampa", ct_walk: 4000, t_walk: 4500, turn: 90 },
        { name: "Kontrol Odası", ct_walk: 5000, t_walk: 3000, turn: -90 },
        { name: "Hızlı Dışarı", ct_walk: 3000, t_walk: 6000, turn: 180 },
        { name: "Radyasyon", ct_walk: 2000, t_walk: 7000, turn: 45 }
    ]
};

let currentMap = "de_boyard"; // Varsayılan Harita

function look(degrees) {
    const pixels = degrees * 5; 
    try { execSync(`xdotool mousemove_relative -- ${pixels} 0`); } catch(e){}
}

async function sprayAndSweep() {
    console.log(`[MACRO] Düşman aranıyor (Spray & Sweep - Geniş Açılı Tarama)...`);
    try {
        execSync(`xdotool mousedown 1`); // Ateş etmeye başla
        
        // Geniş açılı tarama: Fuzzing sapmalarını telafi etmek ve headshot yüzdesini rastgeleleştirmek için
        for(let i=0; i<10; i++) {
            execSync(`xdotool mousemove_relative -- 50 0`); // Sağa tara
            await delay(100);
        }
        for(let i=0; i<20; i++) {
            execSync(`xdotool mousemove_relative -- -50 0`); // Sola tara
            await delay(100);
        }
        
        execSync(`xdotool mouseup 1`); // Ateşi kes
    } catch(e){}
}

async function executeMacro(routeIndex, isCT, roundNum) {
    // Seçilen haritanın rotalarını al
    const routes = MAP_ROUTES[currentMap] || MAP_ROUTES["de_boyard"];
    const route = routes[routeIndex % routes.length];
    
    console.log(`[MACRO] Harita: ${currentMap} | Raund: ${roundNum} | Rota: ${route.name}`);
    
    // FUZZING MANTIĞI: Süreleri sabit bırakma, +- %10 sapma ekle
    const fuzz = (time) => time + (Math.floor(Math.random() * (time * 0.2)) - (time * 0.1));
    
    const walkTime = isCT ? route.ct_walk : route.t_walk;
    const fuzzedWalk = fuzz(walkTime);
    
    try {
        execSync(`xdotool keydown w`);
        await delay(fuzzedWalk);
        execSync(`xdotool keyup w`);
    } catch(e){}
    
    // Dönüş yönü
    const turnDegrees = isCT ? route.turn : -route.turn;
    look(turnDegrees);
    
    // Rastgele insansı bekleme süresi
    await delay(fuzz(1000));
    
    // Skor Takası: Sadece o raundun "kazananı" ateş eder. (Sırayla kill alırlar)
    const shouldShoot = (roundNum % 2 === 0) ? isCT : !isCT;
    
    if (shouldShoot) {
        await sprayAndSweep();
    } else {
        // Kurban olan bot rastgele hareket eder veya eğilir
        try {
            execSync(`xdotool keydown ctrl`);
            await delay(fuzz(3000));
            execSync(`xdotool keyup ctrl`);
        } catch(e){}
    }
}

function watchConsoleLog() {
    console.log(`[MACRO] Çok İhtimali VACnet Bypass Aktif. console.log dinleniyor...`);
    let fileSize = 0;
    let currentRound = 0;
    
    if (fs.existsSync(LOG_FILE)) {
        fileSize = fs.statSync(LOG_FILE).size;
    }

    setInterval(() => {
        if (!fs.existsSync(LOG_FILE)) return;
        
        const newSize = fs.statSync(LOG_FILE).size;
        if (newSize > fileSize) {
            const stream = fs.createReadStream(LOG_FILE, { start: fileSize, end: newSize });
            stream.on('data', async (data) => {
                const lines = data.toString().split('\n');
                for (let line of lines) {
                    // Harita yükleme logu: "Map: de_overpass" veya benzeri
                    if (line.includes('Map: ')) {
                        const match = line.match(/Map:\s(de_[a-zA-Z0-9_]+)/);
                        if (match && MAP_ROUTES[match[1]]) {
                            currentMap = match[1];
                            console.log(`[MACRO] Harita Algılandı: ${currentMap}`);
                        }
                    }
                    // Match_Start logu gelirse raundu sıfırla
                    if (line.includes('Match_Start')) {
                        currentRound = 0;
                    }
                    if (line.includes('Round_Start')) {
                        currentRound++;
                        console.log(`[MACRO] Raund ${currentRound} başladı!`);
                        
                        // Senkronizasyon: İki konteyner da aynı rotayı seçmesi için Raund No'sunu Seed kullanır
                        const routes = MAP_ROUTES[currentMap] || MAP_ROUTES["de_boyard"];
                        const routeIndex = Math.floor(seededRandom(currentRound) * routes.length);
                        const isCT = (botId === 1 || botId === 2);
                        
                        await executeMacro(routeIndex, isCT, currentRound);
                    }
                }
            });
            fileSize = newSize;
        }
    }, 1000);
}

module.exports = { watchConsoleLog };

