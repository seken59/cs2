const mysql = require('mysql2');
const TelegramBot = require('node-telegram-bot-api');

// MySQL Bağlantısı
const DB_HOST = process.env.DB_HOST || 'localhost';
const DB_USER = process.env.DB_USER || 'cs_admin';
const DB_PASS = process.env.DB_PASS || 'zz12JkE3O@10gFr1';
const DB_NAME = process.env.DB_NAME || 'cs_bot';
const TELEGRAM_TOKEN = process.env.TELEGRAM_TOKEN || '';
const CHAT_ID = process.env.TELEGRAM_CHAT_ID || '';

const db = mysql.createPool({
    host: DB_HOST,
    user: DB_USER,
    password: DB_PASS,
    database: DB_NAME,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

const botTelegram = TELEGRAM_TOKEN ? new TelegramBot(TELEGRAM_TOKEN, { polling: false }) : null;

function sendTelegram(message) {
    if (botTelegram && CHAT_ID) {
        botTelegram.sendMessage(CHAT_ID, message).catch(console.error);
    }
}

// Veritabanı Şemasını Güncelle (V6 - Batch Lifecycle & Action Queue)
db.getConnection((err, connection) => {
    if(err) {
        console.error('Veritabanına bağlanılamadı:', err);
        return;
    }

    // Accounts tablosuna key_version ekle (hata verirse yoksay)
    connection.query(`ALTER TABLE accounts ADD COLUMN key_version VARCHAR(20) DEFAULT 'v1'`, () => {});

    // V6 Tabloları
    connection.query(`CREATE TABLE IF NOT EXISTS farm_batches (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        batch_id VARCHAR(64) NOT NULL UNIQUE,
        status ENUM('CREATED','RUNNING','STOPPING','FINISHED','FAILED','RECOVERED') NOT NULL,
        created_at DATETIME NOT NULL,
        started_at DATETIME NULL,
        finished_at DATETIME NULL,
        last_heartbeat_at DATETIME NULL,
        error_message TEXT NULL
    )`);

    connection.query(`CREATE TABLE IF NOT EXISTS farm_batch_accounts (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        batch_id VARCHAR(64) NOT NULL,
        account_id BIGINT NOT NULL,
        role VARCHAR(32) NOT NULL,
        container_name VARCHAR(128) NULL,
        status ENUM('RESERVED','RUNNING','FINISHED','FAILED','RECOVERED') NOT NULL,
        heartbeat_at DATETIME NULL,
        last_error TEXT NULL
    )`);

    connection.query(`CREATE TABLE IF NOT EXISTS admin_audit_log (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        admin_user VARCHAR(64) NOT NULL,
        ip_address VARCHAR(64) NOT NULL,
        action VARCHAR(128) NOT NULL,
        target_type VARCHAR(64) NULL,
        target_id VARCHAR(128) NULL,
        details JSON NULL,
        created_at DATETIME NOT NULL
    )`);

    connection.query(`CREATE TABLE IF NOT EXISTS action_queue (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        idempotency_key VARCHAR(128) UNIQUE NULL,
        action_type VARCHAR(64) NOT NULL,
        payload JSON NOT NULL,
        status ENUM('PENDING','PROCESSING','COMPLETED','FAILED') DEFAULT 'PENDING',
        worker_id VARCHAR(100) NULL,
        retry_count INT DEFAULT 0,
        max_retry INT DEFAULT 3,
        timeout_seconds INT DEFAULT 300,
        locked_until DATETIME NULL,
        created_at DATETIME NOT NULL,
        completed_at DATETIME NULL,
        result TEXT NULL
    )`);

    connection.query(`CREATE TABLE IF NOT EXISTS system_alerts (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        severity ENUM('INFO','WARNING','CRITICAL') NOT NULL,
        alert_type VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        related_entity_type VARCHAR(50) NULL,
        related_entity_id VARCHAR(100) NULL,
        status ENUM('OPEN','ACKED','RESOLVED') NOT NULL DEFAULT 'OPEN',
        created_at DATETIME NOT NULL,
        acknowledged_at DATETIME NULL,
        resolved_at DATETIME NULL
    )`);

    connection.query(`CREATE TABLE IF NOT EXISTS login_attempts (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NULL,
        ip_address VARCHAR(64) NOT NULL,
        success TINYINT(1) NOT NULL,
        failure_reason VARCHAR(100) NULL,
        created_at DATETIME NOT NULL
    )`);

    connection.query(`CREATE TABLE IF NOT EXISTS worker_heartbeats (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        worker_name VARCHAR(100) NOT NULL,
        worker_type ENUM('ORCHESTRATOR','HOST_WORKER','BACKUP_WORKER','ALERT_WORKER') NOT NULL,
        heartbeat_at DATETIME NOT NULL,
        status ENUM('OK','WARNING','ERROR') NOT NULL DEFAULT 'OK',
        last_error TEXT NULL
    )`);

    connection.query(`CREATE TABLE IF NOT EXISTS backup_runs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        backup_file VARCHAR(255) NOT NULL,
        backup_type ENUM('DB','KEY','FULL') NOT NULL,
        status ENUM('STARTED','COMPLETED','FAILED','RESTORE_TESTED') NOT NULL,
        size_bytes BIGINT NULL,
        checksum_sha256 VARCHAR(128) NULL,
        started_at DATETIME NOT NULL,
        finished_at DATETIME NULL,
        error_message TEXT NULL
    )`);

    connection.query(`CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value VARCHAR(255)
    )`, (err) => {
        if(!err) {
            connection.query(`INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('maintenance_mode', 'COMPLETED')`);
            connection.query(`INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('allowed_ips', '127.0.0.1,::1')`);
        }
        connection.release();
    });
});

// Master Döngü: Transaction ile Atomik Batch Başlatma
async function triggerBatch() {
    console.log('[INFO] Yeni batch kontrol ediliyor...');
    const promiseDb = db.promise();
    
    try {
        // Orchestrator Heartbeat
        await promiseDb.query(`INSERT INTO worker_heartbeats (worker_name, worker_type, heartbeat_at) VALUES ('main_index', 'ORCHESTRATOR', NOW()) ON DUPLICATE KEY UPDATE heartbeat_at=NOW(), status='OK'`);

        const [settings] = await promiseDb.query(`SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode'`);
        const maintenanceState = settings.length > 0 ? settings[0].setting_value : 'COMPLETED';
        if (maintenanceState !== 'COMPLETED' && maintenanceState !== 'FAILED' && maintenanceState !== 'CANCELLED') {
            console.log(`[WARNING] Sistem BAKIM MODUNDA (Durum: ${maintenanceState}). Yeni batch başlatılamaz.`);
            return;
        }

        await promiseDb.query('START TRANSACTION');
        
        const [rows] = await promiseDb.query(`
            SELECT id, username FROM accounts 
            WHERE status = 'IDLE' AND (locked_until IS NULL OR locked_until < NOW())
            ORDER BY last_run_at ASC LIMIT 4 FOR UPDATE
        `);
        
        if (rows.length === 4) {
            const batchId = 'BATCH-' + Date.now();
            const ids = rows.map(r => r.id);
            
            // 1. Accounts Tablosunu Güncelle
            await promiseDb.query(`
                UPDATE accounts 
                SET status = 'RESERVED', locked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE), 
                    last_run_at = NOW(), batch_id = ?, heartbeat_at = NOW()
                WHERE id IN (?)
            `, [batchId, ids]);

            // 2. Farm Batches Tablosuna Yaz
            await promiseDb.query(`
                INSERT INTO farm_batches (batch_id, status, created_at, started_at) 
                VALUES (?, 'RUNNING', NOW(), NOW())
            `, [batchId]);

            // 3. Farm Batch Accounts Tablosuna Yaz
            for(let i=0; i<4; i++) {
                await promiseDb.query(`
                    INSERT INTO farm_batch_accounts (batch_id, account_id, role, container_name, status, heartbeat_at)
                    VALUES (?, ?, ?, ?, 'RUNNING', NOW())
                `, [batchId, rows[i].id, 'FARMER', `cs2_bot_${i+1}`]);
            }
            
            await promiseDb.query('COMMIT');
            console.log(`[MASTER] 4 hesap Batch için rezerve edildi. BatchID: ${batchId}`);
            
            // TODO: Action Queue üzerinden docker start komutu eklenebilir.
        } else {
            await promiseDb.query('ROLLBACK');
            console.log('[INFO] Yeterli IDLE hesap bulunamadı.');
        }
    } catch (e) {
        await promiseDb.query('ROLLBACK');
        console.error('[HATA] Transaction başarısız:', e.message);
    }
}

// Kademeli Dead-Bot Recovery & Batch Abort (V7)
async function stagedRecovery() {
    const promiseDb = db.promise();
    try {
        // Aşama 1: WARNING (2 Dakika) - Sadece Console'a yaz, izle.
        const [warnings] = await promiseDb.query(`SELECT username, heartbeat_at FROM accounts WHERE status = 'RESERVED' AND heartbeat_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE) AND heartbeat_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)`);
        if(warnings.length > 0) {
            console.log(`[WARNING] ${warnings.length} hesapta heartbeat gecikmesi var (2-5 dk arası). İzleniyor.`);
        }

        // Aşama 2: STALE (5 Dakika) - İşareti değiştir ama henüz DB hesabını FAILED yapma. Batch Account'u STALE yap.
        await promiseDb.query(`UPDATE farm_batch_accounts SET status = 'STALE', last_error = 'STALE - 5 dakika timeout' WHERE status = 'RUNNING' AND heartbeat_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND heartbeat_at >= DATE_SUB(NOW(), INTERVAL 7 MINUTE)`);

        // Aşama 3: RECOVERY & BATCH ABORT (7 Dakika)
        const [deadAccounts] = await promiseDb.query(`SELECT username, batch_id FROM accounts WHERE status = 'RESERVED' AND heartbeat_at < DATE_SUB(NOW(), INTERVAL 7 MINUTE)`);
        
        if (deadAccounts.length > 0) {
            // Sadece ölen hesabı değil, o batch'in tamamını Abort ediyoruz.
            let abortedBatches = new Set();

            for (const dead of deadAccounts) {
                if(!dead.batch_id) continue;
                abortedBatches.add(dead.batch_id);
            }

            for (let bId of abortedBatches) {
                // Batch Durumunu ABORTING yap
                await promiseDb.query(`UPDATE farm_batches SET status = 'ABORTING', error_message = 'Batch Aborted due to Dead Bot' WHERE batch_id = ?`, [bId]);
                
                // Batch içindeki tüm konteynerlere (sağlam olanlar dahil) STOP emri gönder
                const [containers] = await promiseDb.query(`SELECT container_name FROM farm_batch_accounts WHERE batch_id = ?`, [bId]);
                for (const c of containers) {
                    if(c.container_name) {
                        await promiseDb.query(`
                            INSERT INTO action_queue (action_type, payload, status, created_at) 
                            VALUES ('STOP_CONTAINER', ?, 'PENDING', NOW())
                        `, [JSON.stringify({ container_name: c.container_name })]);
                    }
                }

                // Batch'e ait tüm hesapları IDLE konumuna iade et (Kuyruğa atıldıkları için container'lar yakında ölecek)
                await promiseDb.query(`
                    UPDATE accounts 
                    SET status = 'IDLE', last_error = 'RECOVERED - Batch Aborted', batch_id = NULL
                    WHERE batch_id = ?
                `, [bId]);
                
                console.log(`[RECOVERY] Batch ${bId} Bütünlüğü bozulduğu için ABORTED yapıldı. Tüm botlara STOP emri verildi.`);
            }
        }

        // ABORTING Alert (10 dakikadan uzun süren abort işlemi varsa)
        const [abortingBatches] = await promiseDb.query(`SELECT batch_id FROM farm_batches WHERE status = 'ABORTING' AND created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)`);
        for (const b of abortingBatches) {
            await promiseDb.query(`INSERT INTO system_alerts (severity, alert_type, message, related_entity_type, related_entity_id, created_at) VALUES ('CRITICAL', 'BATCH_ABORT_TIMEOUT', 'Batch ABORTING statüsünde 10 dakikadan uzun süredir bekliyor!', 'BATCH', ?, NOW())`, [b.batch_id]);
        }

        // Batch Status Update (Eğer içindeki tüm hesaplar bittiyse ve durumu RUNNING/ABORTING ise FINISHED/FAILED yap)
        await promiseDb.query(`
            UPDATE farm_batches fb
            SET status = IF(status = 'ABORTING', 'FAILED', 'FINISHED'), finished_at = NOW()
            WHERE status IN ('RUNNING', 'ABORTING') AND NOT EXISTS (
                SELECT 1 FROM accounts WHERE batch_id = fb.batch_id AND status = 'RESERVED'
            )
        `);

    } catch (e) {
        console.error('[HATA] Kademeli Recovery başarısız:', e.message);
    }
}

setInterval(stagedRecovery, 60000);
setInterval(triggerBatch, 60000 * 5); // 5 dakikada bir kontrol et

sendTelegram('Master Node (V6) başlatıldı. Batch işlemleri dinleniyor.');
console.log('[INFO] Master Orkestratör (V6) başlatıldı.');
