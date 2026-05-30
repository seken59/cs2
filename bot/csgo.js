const SteamUser = require('steam-user');
const GlobalOffensive = require('node-globaloffensive');
const SteamTotp = require('steam-totp');
const { execSync, spawn } = require('child_process');
const axios = require('axios');
const fs = require('fs');
const mysql = require('mysql2/promise');
const TelegramBot = require('node-telegram-bot-api');
const { decrypt } = require('../utils/crypto.js');

const account = {
    username: process.env.ACCOUNT_USER,
    password: decrypt(process.env.ACCOUNT_PASS) || process.env.ACCOUNT_PASS,
    sharedSecret: decrypt(process.env.ACCOUNT_SHARED_SECRET) || process.env.ACCOUNT_SHARED_SECRET,
    proxy: process.env.SOCKS5_PROXY,
    lobbyId: process.env.LOBBY_ID,
    botId: process.env.BOT_ID,
    batchId: null // DB'den başlangıçta çekilecek
};

console.log(`[BOT-${account.botId}] Konteyner Başlatıldı. Hedef Lobi: ${account.lobbyId}`);

const { performLogin } = require('./steam_login');
const { createLobbyAndSearch } = require('./lobby_manager');
const { watchConsoleLog } = require('./macro_engine');

// 1. AŞAMA: Fiziksel Steam ve CS2 Başlatma (XP Kasma Aşaması)
async function startPhysicalGame() {
    try {
        // VACnet / Trust Factor İnsansı Gecikme (Randomized Delay)
        // Botların aynı anda tıklamasını engellemek için 1 ile 3 dakika arası rastgele bekle.
        const randomDelay = Math.floor(Math.random() * (180000 - 60000 + 1)) + 60000;
        console.log(`[BOT-${account.botId}] VACnet Evasion: Oyuna girmeden önce ${randomDelay/1000} saniye insansı gecikme uygulanıyor...`);
        await new Promise(resolve => setTimeout(resolve, randomDelay));

        startHeartbeat();

        console.log(`[BOT-${account.botId}] Steam ve CS2 başlatılıyor... (Proxy: ${account.proxy || 'Yok'})`);
        
        let steamCmd = `xvfb-run -a -n ${90 + parseInt(account.botId)} steam -login ${account.username} ${account.password} -applaunch 730 -low -nosound -textmode -w 320 -h 240 +fps_max 10 -novid -nojoy`;
        if (account.proxy) {
            // Basit SOCKS5 proxy parametresi (Örnek kullanım)
            steamCmd += ` -proxy ${account.proxy}`;
        }
        
        const steamProc = exec(steamCmd);

        // Log dosyasını her maç öncesi sıfırla
        const LOG_FILE = '/home/steamuser/cs2_merged/game/csgo/console.log';
        if (fs.existsSync(LOG_FILE)) {
            fs.writeFileSync(LOG_FILE, '');
        }

        // 2FA ve Steam Giriş İşlemi
        await performLogin();

        // CS2 Lobi Kurma ve Arama
        createLobbyAndSearch();

        // Macro Engine'i Başlat (Log okuyucu)
        watchConsoleLog();
        
        // Gerçekte bu işlemi Macro Engine veya CSGO GC eventi ile kapatmalıyız.
        // Şimdilik 20 dakika sonra zorla kapatıp XP kontrolüne geçiyoruz.
        setTimeout(() => {
            console.log(`[BOT-${account.botId}] Maç süresi doldu. Graceful Shutdown başlatılıyor...`);
            
            try {
                if (steamProc && steamProc.pid) {
                    // Steam ve alt process'leri kapatmak için SIGTERM gönder
                    process.kill(steamProc.pid, 'SIGTERM');
                    console.log(`[BOT-${account.botId}] Graceful Shutdown başlatıldı. Steam'e SIGTERM gönderiliyor...`);
                    
                    try {
                        // Sadece bu konteynerin içindeki steam process ağacına SIGTERM gönder
                        execSync(`kill -TERM -$(ps -o pgid= -p $(pgrep -f "steam" -n) | grep -o '[0-9]*') 2>/dev/null`);
                    } catch(e){}

                    // 10 Saniye sonra hala açıksa Force Kill ve Temizlik
                    setTimeout(() => {
                        console.log(`[BOT-${account.botId}] 10s doldu. Steam hala açıksa SIGKILL ile zorla kapatılacak.`);
                        try {
                            execSync(`kill -9 -$(ps -o pgid= -p $(pgrep -f "steam" -n) | grep -o '[0-9]*') 2>/dev/null`);
                            execSync('rm -rf /home/steamuser/cs2_merged/game/csgo/shadercache/*');
                        } catch(e) {}
                        checkAndClaimDrop();
                    }, 10000);
                } else {
                    execSync(`kill -9 -$(ps -o pgid= -p $(pgrep -f "steam" -n) | grep -o '[0-9]*') 2>/dev/null`);
                    checkAndClaimDrop();
                }
            } catch(e) { 
                console.error(`[BOT-${account.botId}] Kapatma Hatası:`, e.message);
                checkAndClaimDrop(); 
            }
        }, 20 * 60 * 1000);
    } catch (err) {
        console.error(`[BOT-${account.botId}] Başlatma Hatası:`, err);
    }
}

const DB_HOST = process.env.DB_HOST || 'localhost';
const DB_USER = process.env.DB_USER || 'cs_admin';
const DB_PASS = process.env.DB_PASS || 'zz12JkE3O@10gFr1';
const DB_NAME = process.env.DB_NAME || 'cs_bot';
const TELEGRAM_TOKEN = process.env.TELEGRAM_TOKEN || '';
const CHAT_ID = process.env.TELEGRAM_CHAT_ID || '';

const botTelegram = TELEGRAM_TOKEN ? new TelegramBot(TELEGRAM_TOKEN, { polling: false }) : null;

function sendTelegram(message) {
    if (botTelegram && CHAT_ID) {
        botTelegram.sendMessage(CHAT_ID, message).catch(() => {});
    }
}

// Heartbeat Gönderimi (Veritabanında heartbeat_at değerini günceller)
async function startHeartbeat() {
    try {
        const db = await mysql.createConnection({
            host: DB_HOST,
            user: DB_USER,
            password: DB_PASS,
            database: DB_NAME
        });

        // Başlangıçta Batch ID öğren
        const [rows] = await db.execute(`SELECT batch_id FROM accounts WHERE username = ? AND status = 'RESERVED'`, [account.username]);
        if(rows.length > 0) {
            account.batchId = rows[0].batch_id;
            console.log(`[BOT-${account.botId}] Atanmış Batch ID bulundu: ${account.batchId}`);
        } else {
            console.error(`[BOT-${account.botId}] HATA: Hesap RESERVED modunda değil veya Batch ID yok. Kapatılıyor.`);
            process.exit(1);
        }

        setInterval(async () => {
            try {
                const [result] = await db.execute(
                    `UPDATE accounts SET heartbeat_at = NOW(), locked_until = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE username = ? AND batch_id = ? AND status = 'RESERVED'`, 
                    [account.username, account.batchId]
                );
                
                // Eğer hiçbir satır güncellenmediyse (Konteyner Dead-Bot Recovery'ye takılmış veya hesabı düşmüş demektir)
                if (result.affectedRows === 0) {
                    console.error(`[BOT-${account.botId}] CRITICAL ERROR: Heartbeat reddedildi! Batch yetkisi düşmüş (Zombie Process). Kendini imha ediyor...`);
                    try {
                        execSync(`kill -9 -$(ps -o pgid= -p $(pgrep -f "steam" -n) | grep -o '[0-9]*') 2>/dev/null`);
                    } catch(e){}
                    process.exit(1);
                }

                // Farm Batch tablolarını da güncelle
                await db.execute(`UPDATE farm_batch_accounts SET heartbeat_at = NOW() WHERE batch_id = ? AND account_id = (SELECT id FROM accounts WHERE username = ?)`, [account.batchId, account.username]);
                await db.execute(`UPDATE farm_batches SET last_heartbeat_at = NOW() WHERE batch_id = ?`, [account.batchId]);

            } catch(e) {
                console.error(`[BOT-${account.botId}] Heartbeat Hatası:`, e.message);
            }
        }, 60000);
    } catch(e) {
        console.error(`[BOT-${account.botId}] Başlangıç Heartbeat Bağlantı Hatası:`, e.message);
    }
}

// 2. AŞAMA: Node-Steam-User ile GC'ye bağlanıp Drop kontrolü ve XP Raporlama
function checkAndClaimDrop() {
    console.log(`[BOT-${account.botId}] GC üzerinden XP ve Drop kontrolü yapılıyor...`);
    
    const client = new SteamUser();
    const csgo = new GlobalOffensive(client);

    const logonOptions = {
        accountName: account.username,
        password: account.password,
        twoFactorCode: SteamTotp.generateAuthCode(account.sharedSecret)
    };

    client.logOn(logonOptions);

    client.on('loggedOn', () => {
        client.setPersona(SteamUser.EPersonaState.Online);
        client.gamesPlayed([730]);
    });

    csgo.on('connectedToGC', () => {
        console.log(`[BOT-${account.botId}] GC bağlandı, profil verisi bekleniyor...`);
        // Kendi profilimizi istek yap
        csgo.requestPlayersProfile(client.steamID);
    });

    csgo.on('playersProfile', async (profile) => {
        if (!profile || !profile.account_profiles || profile.account_profiles.length === 0) return;
        
        const data = profile.account_profiles[0];
        const currentLevel = data.player_level;
        const currentXP = data.player_cur_xp;
        
        try {
            const db = await mysql.createConnection({
                host: DB_HOST,
                user: DB_USER,
                password: DB_PASS,
                database: DB_NAME
            });

            const [rows] = await db.execute(`SELECT xp, level FROM accounts WHERE username = ?`, [account.username]);
            
            if (rows.length > 0) {
                const oldXP = rows[0].xp;
                const oldLevel = rows[0].level;
                
                let gainedXP = 0;
                if (currentLevel > oldLevel) {
                    gainedXP = (5000 - oldXP) + currentXP;
                } else {
                    gainedXP = currentXP - oldXP;
                }
                
                if (gainedXP > 0 || oldXP === 0) {
                    const msg = `🤖 *Farm Raporu* (Bot-${account.botId})\n👤 Hesap: ${account.username}\n📈 Kazanılan XP: *+${gainedXP}*\n⭐ Mevcut Seviye: ${currentLevel} (XP: ${currentXP}/5000)\n🏆 Haftalık Kasa Durumu: ${currentLevel > oldLevel ? "KASA DÜŞTÜ! 🎉" : "Kasıyor..."}`;
                    sendTelegram(msg);
                }
                
                await db.execute(`UPDATE accounts SET xp = ?, level = ? WHERE username = ?`, [currentXP, currentLevel, account.username]);
            }
            await db.end();
        } catch(e) {
            console.error(`[BOT-${account.botId}] MySQL Hatası:`, e.message);
        }
        
        console.log(`[BOT-${account.botId}] XP Güncellendi. Konteyner görevini bitirdi.`);
        process.exit(0);
    });
}

// VACnet Bypass: Rastgele Ydotool Makrosu
function performHumanLikeAction() {
    // Statik hareket ban yedirir. Rastgele WASD ve Mouse
    const actions = [
        `ydotool key 17:1 17:0`, // W
        `ydotool key 30:1 30:0`, // A
        `ydotool key 31:1 31:0`, // S
        `ydotool key 32:1 32:0`, // D
        `ydotool click 40`,      // Sol Tık
        `ydotool mousemove -x ${Math.floor(Math.random()*50)-25} -y ${Math.floor(Math.random()*50)-25}` // Rastgele crosshair sarsıntısı
    ];
    const randomAction = actions[Math.floor(Math.random() * actions.length)];
    try {
        execSync(randomAction);
    } catch(e){}
}

function processConsoleOutput(data) {
    if (data.includes("Match Started")) {
        console.log(`[BOT-${account.botId}] Maç başladı. Macro devrede.`);
        // Makroyu statik değil, rastgele aralıklarla çalıştır (10-30 saniye arası)
        const macroInterval = setInterval(() => {
            performHumanLikeAction();
        }, Math.floor(Math.random() * 20000) + 10000);
    }
}

// Sistemi Tetikle
startPhysicalGame();
