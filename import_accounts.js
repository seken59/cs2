const fs = require('fs');
const path = require('path');
const mysql = require('mysql2/promise');

const DB_HOST = process.env.DB_HOST || 'localhost';
const DB_USER = process.env.DB_USER || 'cs_admin';
const DB_PASS = process.env.DB_PASS || 'zz12JkE3O@10gFr1';
const DB_NAME = process.env.DB_NAME || 'cs_bot';

const MAFILES_DIR = path.join(__dirname, 'maFiles');

if (!fs.existsSync(MAFILES_DIR)) {
    fs.mkdirSync(MAFILES_DIR);
    console.log(`[INFO] '${MAFILES_DIR}' dizini oluşturuldu. Lütfen SDA (.maFile) dosyalarınızı buraya atın ve scripti tekrar çalıştırın.`);
async function importAccounts() {
    if (!fs.existsSync(MAFILES_DIR)) {
        console.error(`[HATA] ${MAFILES_DIR} klasörü bulunamadı!`);
        return;
    }

    const files = fs.readdirSync(MAFILES_DIR);
    let imported = 0;
    
    try {
        const db = await mysql.createConnection({
            host: DB_HOST,
            user: DB_USER,
            password: DB_PASS,
            database: DB_NAME
        });

        const { encrypt } = require('./utils/crypto.js');

        for (const file of files) {
            if (file.endsWith('.maFile')) {
                const filePath = path.join(MAFILES_DIR, file);
                const content = fs.readFileSync(filePath, 'utf8');
                
                try {
                    const data = JSON.parse(content);
                    const username = data.account_name;
                    const sharedSecret = data.shared_secret;
                    const password = ""; // maFile içinde şifre genelde olmaz, elle güncellenmesi gerek

                    if (username && sharedSecret) {
                        try {
                            const encryptedPassword = encrypt(password);
                            const encryptedSecret = encrypt(sharedSecret);
                            await db.execute(
                                `INSERT INTO accounts (username, password, shared_secret, status, level, xp) VALUES (?, ?, ?, 'IDLE', 1, 0)`,
                                [username, encryptedPassword, encryptedSecret]
                            );
                            console.log(`[BAŞARILI] Hesap Eklendi (Şifreli): ${username}`);
                            imported++;
                        } catch (e) {
                            if (e.code === 'ER_DUP_ENTRY') {
                                console.log(`[ATLANDI] Hesap zaten var: ${username}`);
                            } else {
                                console.error(`[HATA] ${username} eklenirken hata:`, e.message);
                            }
                        }
                    }
                } catch (err) {
                    console.error(`[HATA] Dosya okunamadı (${file}):`, err.message);
                }
            }
        }
        
        console.log(`\n[SONUÇ] Toplam ${imported} yeni hesap veritabanına aktarıldı.`);
        await db.end();
    } catch(e) {
        console.error("[FATAL] MySQL Bağlantı Hatası:", e.message);
    }
}

importAccounts();
