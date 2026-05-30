const mysql = require('mysql2');

const DB_HOST = process.env.DB_HOST;
const DB_USER = process.env.DB_USER;
const DB_PASS = process.env.DB_PASS;
const DB_NAME = process.env.DB_NAME;

if (!DB_HOST || !DB_USER || !DB_PASS || !DB_NAME) {
    console.error("CRITICAL ERROR: DB environment variables missing in scheduler.");
    process.exit(1);
}

/**
 * CS2 haftalık drop limitleri her Çarşamba TSİ sabaha karşı sıfırlanır.
 * Bu script, veritabanındaki tüm hesapların status'ünü tekrar IDLE yaparak
 * XP kasma havuzuna geri ekler.
 */

function resetWeeklyDrops() {
    const db = mysql.createConnection({
        host: DB_HOST,
        user: DB_USER,
        password: DB_PASS,
        database: DB_NAME
    });
    
    console.log(`[SCHEDULER] Haftalık Drop Sıfırlama işlemi başlatılıyor...`);

    db.query(`UPDATE accounts SET status = 'IDLE' WHERE status = 'DROPPED'`, function(err, result) {
        if (err) {
            console.error(`[SCHEDULER] Hata:`, err.message);
        } else {
            console.log(`[SCHEDULER] Başarılı. ${result.affectedRows} hesap IDLE durumuna çekildi.`);
        }
        db.end();
    });
}

// Çarşamba günleri çalıştırılacak mantık
function startCron() {
    setInterval(() => {
        const now = new Date();
        // Çarşamba (Day 3) saat 04:00 (TSİ) kontrolü
        if (now.getDay() === 3 && now.getHours() === 4 && now.getMinutes() === 0) {
            resetWeeklyDrops();
        }
    }, 60000); // Her dakika kontrol et
}

module.exports = { startCron, resetWeeklyDrops };
