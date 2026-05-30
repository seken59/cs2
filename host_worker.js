const mysql = require('mysql2/promise');
const { spawn } = require('child_process');
const TelegramBot = require('node-telegram-bot-api');

// MySQL Bağlantısı
const DB_HOST = process.env.DB_HOST || 'localhost';
const DB_USER = process.env.DB_USER || 'cs_admin';
const DB_PASS = process.env.DB_PASS || 'zz12JkE3O@10gFr1';
const DB_NAME = process.env.DB_NAME || 'cs_bot';
const TELEGRAM_TOKEN = process.env.TELEGRAM_TOKEN || '';
const CHAT_ID = process.env.TELEGRAM_CHAT_ID || '';

const botTelegram = TELEGRAM_TOKEN ? new TelegramBot(TELEGRAM_TOKEN, { polling: false }) : null;

function sendTelegram(message) {
    if (botTelegram && CHAT_ID) {
        botTelegram.sendMessage(CHAT_ID, message).catch(console.error);
    }
}

// Container Whitelist Kontrolü
const allowedContainers = new Set(["cs2_bot_1", "cs2_bot_2", "cs2_bot_3", "cs2_bot_4"]);

async function checkSystemHealth(db) {
    try {
        // Disk (sadece Linux için çalışır ama Windows da execSync ile alınabilir, basit simüle edelim)
        // Basit RAM kontrolü (Linux: free -m)
        let ramWarning = false;
        try {
            const freeMem = require('os').freemem();
            const totalMem = require('os').totalmem();
            const usedRatio = (totalMem - freeMem) / totalMem;
            if (usedRatio > 0.85) ramWarning = true;
        } catch(e) {}

        if (ramWarning) {
            await db.query(`INSERT INTO system_alerts (severity, alert_type, message, created_at) VALUES ('WARNING', 'HIGH_RAM', 'RAM kullanımı %85 i aştı', NOW())`);
            sendTelegram('[ALARM] RAM Kullanımı %85 üzerinde!');
        }

        // Host Worker Heartbeat
        await db.query(`INSERT INTO worker_heartbeats (worker_name, worker_type, heartbeat_at) VALUES ('host_worker', 'HOST_WORKER', NOW()) ON DUPLICATE KEY UPDATE heartbeat_at=NOW(), status='OK'`);
    } catch(e) { console.error(e); }
}

async function processQueue() {
    let db;
    try {
        db = await mysql.createConnection({
            host: DB_HOST,
            user: DB_USER,
            password: DB_PASS,
            database: DB_NAME
        });

        await checkSystemHealth(db);

        // 5 Dakikadan Eski PENDING görevleri bul ve Alarm ver
        const [staleTasks] = await db.query(`SELECT id, action_type FROM action_queue WHERE status = 'PENDING' AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)`);
        if(staleTasks.length > 0) {
            sendTelegram(`[ALARM] Action Queue Sıkıştı! ${staleTasks.length} adet işlem 5 dakikadan uzun süredir PENDING durumunda.`);
        }

        // PENDING durumundaki görevleri al (FOR UPDATE kullanarak kilitliyoruz)
        await db.query('START TRANSACTION');
        const [rows] = await db.query(`SELECT * FROM action_queue WHERE status = 'PENDING' ORDER BY created_at ASC LIMIT 1 FOR UPDATE`);
        
        if (rows.length === 0) {
            await db.query('ROLLBACK');
            await db.end();
            return;
        }

        const task = rows[0];
        
        // İşleniyor olarak işaretle
        await db.query(`UPDATE action_queue SET status = 'PROCESSING' WHERE id = ?`, [task.id]);
        await db.query('COMMIT');

        console.log(`[WORKER] İşlem yürütülüyor: ${task.action_type} (ID: ${task.id})`);
        
        let payload = {};
        try { payload = JSON.parse(task.payload); } catch(e) {}

        let finalStatus = "COMPLETED";

        if (task.action_type === 'STOP_CONTAINER') {
            const cName = payload.container_name;
            if (!cName || !allowedContainers.has(cName)) {
                await db.query(`UPDATE action_queue SET status = 'FAILED', completed_at = NOW(), result = ? WHERE id = ?`, ["Geçersiz veya yetkisiz container: " + cName, task.id]);
                return;
            }

            // Güvenli Spawn Kullanımı (Shell: false)
            const dockerStop = spawn('docker', ['stop', cName], { shell: false });
            
            dockerStop.on('close', async (code) => {
                const resultMsg = `Container ${cName} stopped with code ${code}`;
                const status = code === 0 ? 'COMPLETED' : 'FAILED';
                const connection = await mysql.createConnection({ host: DB_HOST, user: DB_USER, password: DB_PASS, database: DB_NAME });
                await connection.query(`UPDATE action_queue SET status = ?, completed_at = NOW(), result = ? WHERE id = ?`, [status, resultMsg, task.id]);
                connection.end();
            });
            return; // Spawn asenkron olduğu için buradan dönüyoruz, DB update event içinde.

        } else if (task.action_type === 'MAINTENANCE_CLEANUP') {
            const cleanup = spawn('rm', ['-rf', '/home/cs.serkaneken.com/cs2_merged/*/game/csgo/shadercache/*'], { shell: false });
            cleanup.on('close', async (code) => {
                const resultMsg = `Cleanup finished with code ${code}`;
                const status = code === 0 ? 'COMPLETED' : 'FAILED';
                const connection = await mysql.createConnection({ host: DB_HOST, user: DB_USER, password: DB_PASS, database: DB_NAME });
                await connection.query(`UPDATE action_queue SET status = ?, completed_at = NOW(), result = ? WHERE id = ?`, [status, resultMsg, task.id]);
                connection.end();
            });
            return;
        } else {
            await db.query(`UPDATE action_queue SET status = 'FAILED', completed_at = NOW(), result = ? WHERE id = ?`, ["Bilinmeyen işlem tipi", task.id]);
        }

    } catch (e) {
        if(db) await db.query('ROLLBACK').catch(()=>{});
        console.error('[WORKER] Veritabanı Hatası:', e.message);
    } finally {
        if(db) await db.end().catch(()=>{});
    }
}

// Worker'ı 5 saniyede bir çalıştır
setInterval(processQueue, 5000);
console.log('[INFO] Host Worker (V7 Hardened) başlatıldı. Action Queue dinleniyor...');
