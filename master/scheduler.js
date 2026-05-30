const mysql = require('mysql2');

const DB_HOST = process.env.DB_HOST || 'localhost';
const DB_USER = process.env.DB_USER || 'cs_admin';
const DB_PASS = process.env.DB_PASS || 'zz12JkE3O@10gFr1';
const DB_NAME = process.env.DB_NAME || 'cs_bot';

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
