const SteamUser = require('steam-user');
const GlobalOffensive = require('node-globaloffensive');
const SteamTotp = require('steam-totp');
const { exec, execSync, spawn } = require('child_process');
const axios = require('axios');
const fs = require('fs');
const mysql = require('mysql2/promise');
const TelegramBot = require('node-telegram-bot-api');
const { decrypt } = require('../utils/crypto.js');

const DATABASE_IP = process.env.DATABASE_IP;
const DATABASE_USR = process.env.DATABASE_USR;
const DATABASE_PWD = process.env.DATABASE_PWD;
const DB_NAME = process.env.DB_NAME;
const TG_BOT_TOKEN = process.env.TG_BOT_TOKEN;
const CHAT_ID = process.env.TELEGRAM_CHAT_ID;

if (!DATABASE_IP || !DATABASE_USR || !DATABASE_PWD || !DB_NAME) {
    console.error(`[BOT-${process.env.BOT_ID}] CRITICAL ERROR: DB environment variables missing.`);
    process.exit(1);
}

const botTelegram = TG_BOT_TOKEN ? new TelegramBot(TG_BOT_TOKEN, { polling: false }) : null;

function sendTelegram(message) {
    if (botTelegram && CHAT_ID) {
        botTelegram.sendMessage(CHAT_ID, message).catch(() => {});
    }
}

async function handleDecryptFailure() {
    console.error(`[BOT-${process.env.BOT_ID}] CRITICAL ERROR: Decrypt failed. Plaintext fallback is NOT allowed.`);
    try {
        const db = await mysql.createConnection({ host: DATABASE_IP, user: DATABASE_USR, password: DATABASE_PWD, database: DB_NAME });
        const [rows] = await db.execute(`SELECT batch_id FROM accounts WHERE username = ? AND status = 'RESERVED'`, [process.env.ACCOUNT_USER]);
        if(rows.length > 0) {
            const batchId = rows[0].batch_id;
            await db.execute(`UPDATE accounts SET status = 'FAILED_SECRET', last_error = 'Decrypt failed', batch_id = NULL WHERE username = ?`, [process.env.ACCOUNT_USER]);
            await db.execute(`UPDATE farm_batches SET status = 'ABORTING', error_message = 'Batch Aborted due to Secret Decrypt Failure' WHERE batch_id = ?`, [batchId]);
            await db.execute(`INSERT INTO system_alerts (severity, alert_type, message, related_entity_type, related_entity_id, created_at) VALUES ('CRITICAL', 'SECRET_DECRYPT_FAILED', 'Bot şifre çözme hatası!', 'BATCH', ?, NOW())`, [batchId]);
        }
        await db.end();
    } catch(e) {}
    process.exit(1);
}

const decPass = decrypt(process.env.ACCOUNT_PASS);
const decSecret = decrypt(process.env.ACCOUNT_SHARED_SECRET);
if (!decPass || !decSecret) {
    handleDecryptFailure();
}

const account = {
    username: process.env.ACCOUNT_USER,
    password: decPass,
    sharedSecret: decSecret,
    proxy: process.env.SOCKS5_PROXY,
    lobbyId: process.env.LOBBY_ID,
    botId: process.env.BOT_ID,
    batchId: null
};

console.log(`[BOT-${account.botId}] Konteyner Başlatıldı. Hedef Lobi: ${account.lobbyId}`);

const { performLogin } = require('./steam_login');
const { createLobbyAndSearch } = require('./lobby_manager');
const { watchConsoleLog } = require('./macro_engine');

// 1. AŞAMA: Fiziksel Steam ve CS2 Başlatma (XP Kasma Aşaması)
async function startPhysicalGame() {
    try {
        // İnsansı Gecikme (Randomized Delay)
        // Botların aynı anda tıklamasını engellemek için 1 ile 3 dakika arası rastgele bekle.
        const randomDelay = Math.floor(Math.random() * (180000 - 60000 + 1)) + 60000;
        console.log(`[BOT-${account.botId}] Oyuna girmeden önce ${randomDelay/1000} saniye gecikme uygulanıyor...`);
        await new Promise(resolve => setTimeout(resolve, randomDelay));

        startHeartbeat();

        console.log(`[BOT-${account.botId}] Steam ve CS2 başlatılıyor... (Proxy: ${account.proxy || 'Yok'})`);
        
        let steamCmd = `xvfb-run -a -n ${90 + parseInt(account.botId)} steam -login ${account.username} ${account.password} -applaunch 730 -low -nosound -textmode -w 320 -h 240 +fps_max 10 -novid -nojoy`;
        // Basit SOCKS5 proxy parametresi (Örnek kullanım)
        const steamArgs = ['-a', '-n', `${90 + parseInt(account.botId)}`, 'steam', '-login', account.username, account.password, '-applaunch', '730', '-low', '-nosound', '-textmode', '-w', '320', '-h', '240', '+fps_max', '10', '-novid', '-nojoy'];
        if (account.proxy) {
            steamArgs.push('-proxy', account.proxy);
        }
        
        const steamProc = spawn('xvfb-run', steamArgs, { shell: false });

        // Log dosyasını her maç öncesi sıfırla
        const LOG_FILE = '/home/steamuser/cs2_merged/game/csgo/console.log';
        if (fs.existsSync(LOG_FILE)) {
            fs.writeFileSync(LOG_FILE, '');
        }

        // 2FA ve Steam Giriş İşlemi
        await performLogin(account);

        // CS2 Lobi Kurma ve Arama
        createLobbyAndSearch();

        // Macro Engine'i Başlat (Log okuyucu)
        watchConsoleLog();
        
        setTimeout(() => {
            console.log(`[BOT-${account.botId}] Maç süresi doldu. Graceful Shutdown başlatılıyor...`);
            
            try {
                if (steamProc && steamProc.pid) {
                    process.kill(steamProc.pid, 'SIGTERM');
                    console.log(`[BOT-${account.botId}] Graceful Shutdown başlatıldı. Steam'e SIGTERM gönderiliyor...`);
                    
                    try {
                        execSync(`kill -TERM -$(ps -o pgid= -p $(pgrep -f "steam" -n) | grep -o '[0-9]*') 2>/dev/null`);
                    } catch(e){}

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

// Heartbeat Gönderimi (Veritabanında heartbeat_at değerini günceller)
async function startHeartbeat() {
    try {
        const db = await mysql.createConnection({
            host: DATABASE_IP,
            user: DATABASE_USR,
            password: DATABASE_PWD,
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
                
                if (result.affectedRows === 0) {
                    console.error(`[BOT-${account.botId}] CRITICAL ERROR: Heartbeat reddedildi! Batch yetkisi düşmüş. Kendini imha ediyor...`);
                    try {
                        execSync(`kill -9 -$(ps -o pgid= -p $(pgrep -f "steam" -n) | grep -o '[0-9]*') 2>/dev/null`);
                    } catch(e){}
                    process.exit(1);
                }

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
        csgo.requestPlayersProfile(client.steamID);
    });

    csgo.on('playersProfile', async (profile) => {
        if (!profile || !profile.account_profiles || profile.account_profiles.length === 0) return;
        
        const data = profile.account_profiles[0];
        const currentLevel = data.player_level;
        const currentXP = data.player_cur_xp;
        
        try {
            const db = await mysql.createConnection({
                host: DATABASE_IP,
                user: DATABASE_USR,
                password: DATABASE_PWD,
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

// Input automation is performed through configured input tooling. This provides no guarantee against platform-side detection.
function performHumanLikeAction() {
    const actions = [
        `ydotool key 17:1 17:0`, // W
        `ydotool key 30:1 30:0`, // A
        `ydotool key 31:1 31:0`, // S
        `ydotool key 32:1 32:0`, // D
        `ydotool click 40`,      // Sol Tık
        `ydotool mousemove -x ${Math.floor(Math.random()*50)-25} -y ${Math.floor(Math.random()*50)-25}`
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

