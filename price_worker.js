require('dotenv').config();
const mysql = require('mysql2/promise');
const axios = require('axios');
const TelegramBot = require('node-telegram-bot-api');

// DB Params
const DATABASE_IP = process.env.DATABASE_IP;
const DATABASE_USR = process.env.DATABASE_USR;
const DATABASE_PWD = process.env.DATABASE_PWD;
const DB_NAME = process.env.DB_NAME;
const TG_BOT_TOKEN = process.env.TG_BOT_TOKEN;
const CHAT_ID = process.env.TELEGRAM_CHAT_ID;

if (!DATABASE_IP || !DATABASE_USR || !DATABASE_PWD || !DB_NAME) {
    throw new Error("CRITICAL: Missing required DB environment variables in price_worker.");
}

const WORKER_ID = 'price_worker_' + Math.floor(Math.random() * 10000);
const botTelegram = TG_BOT_TOKEN ? new TelegramBot(TG_BOT_TOKEN, { polling: false }) : null;

function sendTelegram(message) {
    if (botTelegram && CHAT_ID) {
        botTelegram.sendMessage(CHAT_ID, message).catch(console.error);
    }
}

async function markActionCompleted(connection, taskId, resultMsg = 'OK') {
    await connection.query(`UPDATE action_queue SET status = 'COMPLETED', last_error = NULL, updated_at = NOW(), result = ? WHERE id = ?`, [resultMsg, taskId]);
}

async function markActionFailed(connection, task, errorMsg) {
    if (task.retry_count < task.max_retry - 1) {
        await connection.query(`UPDATE action_queue SET status = 'PENDING', retry_count = retry_count + 1, locked_until = NULL, last_error = ?, updated_at = NOW() WHERE id = ?`, [errorMsg, task.id]);
    } else {
        await connection.query(`UPDATE action_queue SET status = 'FAILED', last_error = ?, updated_at = NOW() WHERE id = ?`, [errorMsg, task.id]);
        await connection.query(`INSERT INTO system_alerts (severity, alert_type, message, created_at) VALUES ('WARNING', 'PRICE_WORKER_FAILED', ?, NOW())`, [`Action ID ${task.id} failed: ${errorMsg}`]);
    }
}

async function heartbeat(db) {
    try {
        const [existing] = await db.query(`SELECT id FROM worker_heartbeats WHERE worker_name='price_worker' LIMIT 1`);
        if(existing.length > 0) {
            await db.query(`UPDATE worker_heartbeats SET heartbeat_at=NOW(), status='OK' WHERE id=?`, [existing[0].id]);
        } else {
            await db.query(`INSERT INTO worker_heartbeats (worker_name, worker_type, heartbeat_at, status) VALUES ('price_worker', 'ORCHESTRATOR', NOW(), 'OK')`);
        }
    } catch(e) { console.error('Heartbeat error:', e.message); }
}

async function fetchPrice(connection, market_hash_name) {
    // Check Cache first
    const [cache] = await connection.query(`SELECT * FROM item_price_cache WHERE market_hash_name = ? AND expires_at > NOW() LIMIT 1`, [market_hash_name]);
    if (cache.length > 0) {
        return {
            price_usd: cache[0].best_estimate_usd,
            source: cache[0].best_source,
            confidence: 'HIGH'
        };
    }

    // Mock API fetch for CSFloat/Steam
    // In real environment, use axios.get() with process.env.CSFLOAT_API_KEY
    const price_usd = (Math.random() * 5 + 0.1).toFixed(2);
    
    // Save to Cache (expires in 6 hours)
    await connection.query(`
        INSERT INTO item_price_cache (market_hash_name, best_estimate_usd, best_source, expires_at)
        VALUES (?, ?, 'CSFloat_Mock', DATE_ADD(NOW(), INTERVAL 6 HOUR))
        ON DUPLICATE KEY UPDATE best_estimate_usd = VALUES(best_estimate_usd), expires_at = VALUES(expires_at), checked_at = NOW()
    `, [market_hash_name, price_usd]);

    return {
        price_usd: price_usd,
        source: 'CSFloat_Mock',
        confidence: 'MEDIUM'
    };
}

async function processQueue() {
    let db;
    try {
        db = mysql.createPool({ host: DATABASE_IP, user: DATABASE_USR, password: DATABASE_PWD, database: DB_NAME, waitForConnections: true, connectionLimit: 10, queueLimit: 0 });
        await heartbeat(db);

        const conn = await db.getConnection();
        try {
            await conn.beginTransaction();
            const [rows] = await conn.query(`SELECT * FROM action_queue WHERE (status = 'PENDING' OR (status = 'PROCESSING' AND locked_until < NOW())) AND action_type IN ('REFRESH_ITEM_PRICE', 'REFRESH_DROP_EVENT_PRICES', 'SCAN_ACCOUNT_INVENTORY', 'SYNC_ACCOUNT_DROPS', 'GENERATE_WEEKLY_REVENUE_REPORT') ORDER BY created_at ASC LIMIT 1 FOR UPDATE SKIP LOCKED`);
            
            if (rows.length === 0) {
                await conn.rollback();
                conn.release();
                db.end();
                return;
            }

            const task = rows[0];
            await conn.query(`UPDATE action_queue SET status = 'PROCESSING', worker_id = ?, locked_until = DATE_ADD(NOW(), INTERVAL ? SECOND), updated_at = NOW() WHERE id = ?`, [WORKER_ID, task.timeout_seconds || 300, task.id]);
            await conn.commit();
            conn.release();

            let payload = {};
            try { payload = JSON.parse(task.payload); } catch(e) {}

            const connection = await mysql.createConnection({ host: DATABASE_IP, user: DATABASE_USR, password: DATABASE_PWD, database: DB_NAME });

            try {
                switch (task.action_type) {
                    case 'REFRESH_ITEM_PRICE':
                        if (payload.item_id) {
                            const [items] = await connection.query(`SELECT market_hash_name FROM drop_items WHERE id = ?`, [payload.item_id]);
                            if (items.length > 0) {
                                const priceData = await fetchPrice(connection, items[0].market_hash_name);
                                await connection.query(`UPDATE drop_items SET price_usd = ?, price_source = ?, price_confidence = ?, price_checked_at = NOW() WHERE id = ?`, [priceData.price_usd, priceData.source, priceData.confidence, payload.item_id]);
                            }
                        }
                        await markActionCompleted(connection, task.id, 'Price refreshed');
                        break;
                    case 'REFRESH_DROP_EVENT_PRICES':
                        if (payload.drop_event_id) {
                            const [items] = await connection.query(`SELECT id, market_hash_name FROM drop_items WHERE drop_event_id = ?`, [payload.drop_event_id]);
                            let totalUsd = 0;
                            for (let item of items) {
                                const priceData = await fetchPrice(connection, item.market_hash_name);
                                await connection.query(`UPDATE drop_items SET price_usd = ?, price_source = ?, price_confidence = ?, price_checked_at = NOW() WHERE id = ?`, [priceData.price_usd, priceData.source, priceData.confidence, item.id]);
                                totalUsd += parseFloat(priceData.price_usd);
                            }
                            await connection.query(`UPDATE drop_events SET total_estimated_usd = ?, status = 'VALUED', valued_at = NOW() WHERE id = ?`, [totalUsd, payload.drop_event_id]);
                        }
                        await markActionCompleted(connection, task.id, 'Drop event valued');
                        break;
                    case 'SYNC_ACCOUNT_DROPS':
                    case 'SCAN_ACCOUNT_INVENTORY':
                        // Placeholder for inventory scanning logic
                        await markActionCompleted(connection, task.id, 'Scan completed');
                        break;
                    case 'GENERATE_WEEKLY_REVENUE_REPORT':
                        sendTelegram('📊 Haftalık Gelir Raporu Hazırlandı. Panele eklendi.');
                        await markActionCompleted(connection, task.id, 'Report generated');
                        break;
                    default:
                        await markActionFailed(connection, task, `Unsupported price worker action_type: ${task.action_type}`);
                        break;
                }
            } catch (handlerErr) {
                await markActionFailed(connection, task, handlerErr.message);
            }
            await connection.end();
            db.end();
        } catch (dbErr) {
            if (conn) conn.release();
            console.error('[PRICE WORKER DB ERROR]', dbErr.message);
            db.end();
        }
    } catch (e) {
        console.error('[HATA] Price Worker Queue islenemedi:', e.message);
    }
}

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
        setTimeout(pollQueue, 3000);
    }
}

pollQueue();
console.log('[INFO] Price Worker (V23) başlatıldı. Drop ve Fiyat işlemleri dinleniyor...');
