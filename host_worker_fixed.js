require('dotenv').config();
const mysql = require('mysql2/promise');
const { spawn } = require('child_process');
const TelegramBot = require('node-telegram-bot-api');

// MySQL Bağlantısı
const DATABASE_IP = process.env.DATABASE_IP;
const DATABASE_USR = process.env.DATABASE_USR;
const DATABASE_PWD = process.env.DATABASE_PWD;
const DB_NAME = process.env.DB_NAME;
const TG_BOT_TOKEN = process.env.TG_BOT_TOKEN;
const CHAT_ID = process.env.TELEGRAM_CHAT_ID;

if (!DATABASE_IP || !DATABASE_USR || !DATABASE_PWD || !DB_NAME) {
    throw new Error("CRITICAL: Missing required DB environment variables in worker.");
}

const WORKER_ID = 'host_worker_' + Math.floor(Math.random()*10000);

const botTelegram = TG_BOT_TOKEN ? new TelegramBot(TG_BOT_TOKEN, { polling: false }) : null;

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
        db = mysql.createPool({
            host: DATABASE_IP,
            user: DATABASE_USR,
            password: DATABASE_PWD,
            database: DB_NAME,
            waitForConnections: true,
            connectionLimit: 10,
            queueLimit: 0
        });

        await checkSystemHealth(db);

        // 5 Dakikadan Eski PENDING görevleri bul ve Alarm ver
        const [staleTasks] = await db.query(`SELECT id, action_type FROM action_queue WHERE status = 'PENDING' AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)`);
        if(staleTasks.length > 0) {
            sendTelegram(`[ALARM] Action Queue Sıkıştı! ${staleTasks.length} adet işlem 5 dakikadan uzun süredir PENDING durumunda.`);
        }

        // PENDING durumundaki görevleri veya PROCESSING'de sıkışmış görevleri al (FOR UPDATE SKIP LOCKED kullanarak kilitliyoruz)
        const conn = await db.getConnection();
        try {
            await conn.beginTransaction();
            const [rows] = await conn.query(`
                SELECT * FROM action_queue 
                WHERE (status = 'PENDING' OR (status = 'PROCESSING' AND locked_until < NOW()))
                ORDER BY created_at ASC 
                LIMIT 1 
                FOR UPDATE SKIP LOCKED
            `);
            
            if (rows.length === 0) {
                await conn.rollback();
                conn.release();
                db.end();
                return;
            }

            const task = rows[0];
            
            // Timeout & Retry kontrolü
            if (task.retry_count >= task.max_retry) {
                await conn.query(`UPDATE action_queue SET status = 'FAILED', last_error = 'Max retry exceeded', updated_at = NOW() WHERE id = ?`, [task.id]);
                await db.query(`INSERT INTO system_alerts (severity, alert_type, message, created_at) VALUES ('CRITICAL', 'ACTION_FAILED_MAX_RETRY', ?, NOW())`, [`Action ID ${task.id} failed after max retries`]);
                await conn.commit();
                conn.release();
                db.end();
                return;
            }

            // İşleniyor olarak işaretle ve kilit süresi koy
            await conn.query(`UPDATE action_queue SET status = 'PROCESSING', worker_id = ?, locked_until = DATE_ADD(NOW(), INTERVAL ? SECOND), updated_at = NOW() WHERE id = ?`, [WORKER_ID, task.timeout_seconds, task.id]);
            await conn.commit();
            conn.release();

        console.log(`[WORKER] İşlem yürütülüyor: ${task.action_type} (ID: ${task.id}, Idempotency: ${task.idempotency_key})`);
        
        let payload = {};
        try { payload = JSON.parse(task.payload); } catch(e) {}

        if (task.action_type === 'STOP_CONTAINER') {
            const cName = payload.container_name;
            if (!cName || !allowedContainers.has(cName)) {
                await db.query(`UPDATE action_queue SET status = 'FAILED', completed_at = NOW(), last_error = ?, updated_at = NOW() WHERE id = ?`, ["Geçersiz veya yetkisiz container: " + cName, task.id]);
                db.end();
                return;
            }

            // Güvenli Spawn Kullanımı (Shell: false)
            const dockerStop = spawn('docker', ['stop', cName], { shell: false });
            
            dockerStop.on('close', async (code) => {
                const resultMsg = `Container ${cName} stopped with code ${code}`;
                const status = code === 0 ? 'COMPLETED' : 'FAILED';
                const connection = await mysql.createConnection({ host: DATABASE_IP, user: DATABASE_USR, password: DATABASE_PWD, database: DB_NAME });
                if (status === 'FAILED') {
                    if (task.retry_count < task.max_retry - 1) {
                        await connection.query(`UPDATE action_queue SET status = 'PENDING', retry_count = retry_count + 1, locked_until = NULL, last_error = ?, updated_at = NOW() WHERE id = ?`, [resultMsg, task.id]);
                    } else {
                        await connection.query(`UPDATE action_queue SET status = 'FAILED', completed_at = NOW(), last_error = ?, updated_at = NOW() WHERE id = ?`, [resultMsg, task.id]);
                        await connection.query(`INSERT INTO system_alerts (severity, alert_type, message, created_at) VALUES ('CRITICAL', 'ACTION_FAILED', ?, NOW())`, [`Action ID ${task.id} failed stop container`]);
                    }
                } else {
                    await connection.query(`UPDATE action_queue SET status = ?, completed_at = NOW(), result = ?, updated_at = NOW() WHERE id = ?`, [status, resultMsg, task.id]);
                }
                await connection.end();
                db.end();
            });
            return; // Spawn asenkron olduğu için buradan dönüyoruz, DB update event içinde.

        } else if (task.action_type === 'MAINTENANCE_CLEANUP') {
            const cleanup = spawn('rm', ['-rf', '/home/cs.serkaneken.com/cs2_merged/*/game/csgo/shadercache/*'], { shell: false });
            cleanup.on('close', async (code) => {
                const resultMsg = `Cleanup finished with code ${code}`;
                const status = code === 0 ? 'COMPLETED' : 'FAILED';
                const connection = await mysql.createConnection({ host: DATABASE_IP, user: DATABASE_USR, password: DATABASE_PWD, database: DB_NAME });
                await connection.query(`UPDATE action_queue SET status = ?, completed_at = NOW(), ${status === 'FAILED' ? 'last_error' : 'result'} = ?, updated_at = NOW() WHERE id = ?`, [status, resultMsg, task.id]);
                await connection.end();
                db.end();
            });
            return;
        } else {
            await db.query(`UPDATE action_queue SET status = 'FAILED', completed_at = NOW(), last_error = ?, updated_at = NOW() WHERE id = ?`, ["Bilinmeyen işlem tipi", task.id]);
            db.end();
        }

        } catch (dbErr) {
            if (conn) conn.release();
            console.error('[WORKER DB ERROR]', dbErr.message);
            db.end();
        }
    } catch (e) {
        console.error('[HATA] Action Queue islenemedi:', e.message);
    }
}

// Single-flight poll recursive loop to prevent overlap
let isProcessing = false;
async function pollQueue() {
    if (isProcessing) return;
    isProcessing = true;
    try {
        await processQueue();
    } catch (e) {
        console.error(e);
    } finally {
        isProcessing = false;
        setTimeout(pollQueue, 5000);
    }
}

pollQueue();
console.log('[INFO] Host Worker (V9 Hardened) başlatıldı. Action Queue dinleniyor...');
