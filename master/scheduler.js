const mysql = require('mysql2');

const DATABASE_IP = process.env.DATABASE_IP;
const DATABASE_USR = process.env.DATABASE_USR;
const DATABASE_PWD = process.env.DATABASE_PWD;
const DB_NAME = process.env.DB_NAME;

if (!DATABASE_IP || !DATABASE_USR || !DATABASE_PWD || !DB_NAME) {
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
        host: DATABASE_IP,
        user: DATABASE_USR,
        password: DATABASE_PWD,
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

