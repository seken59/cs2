const { execSync } = require('child_process');
const SteamTotp = require('steam-totp');

/**
 * Xdotool kullanarak Steam arayüzüne şifre ve 2FA kodunu yazar.
 * Bu script Xvfb ortamında (DISPLAY=:99) çalıştırılmalıdır.
 */

const username = process.env.ACCOUNT_USER;
const password = process.env.ACCOUNT_PASS;
const sharedSecret = process.env.ACCOUNT_SHARED_SECRET;

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function performLogin() {
    console.log(`[STEAM-LOGIN] X11 arayüzünde login simülasyonu başlatılıyor... (${username})`);
    
    // Steam login penceresinin gelmesini bekle (Tahmini 10 saniye)
    console.log(`[STEAM-LOGIN] Steam arayüzünün yüklenmesi bekleniyor...`);
    await delay(10000);

    // Xdotool ile aktif pencereye şifreyi yaz ve Enter'a bas
    // Not: Kullanıcı adını parametre ile (-login) verdiğimiz için şifre veya 2FA ekranında başlar.
    try {
        console.log(`[STEAM-LOGIN] Şifre enjekte ediliyor...`);
        // Klavyeden şifreyi yaz (xdotool)
        execSync(`xdotool type "${password}"`);
        execSync(`xdotool key Return`);
        
        await delay(5000); // 2FA ekranının gelmesini bekle

        if (sharedSecret) {
            console.log(`[STEAM-LOGIN] Steam Guard 2FA Kodu üretiliyor...`);
            const authCode = SteamTotp.generateAuthCode(sharedSecret);
            console.log(`[STEAM-LOGIN] 2FA Kodu: ${authCode}`);
            
            execSync(`xdotool type "${authCode}"`);
            execSync(`xdotool key Return`);
            console.log(`[STEAM-LOGIN] 2FA Kodu onaylandı.`);
        }
        
        console.log(`[STEAM-LOGIN] Login işlemi tamamlandı. CS2 başlatılmaya hazır.`);

    } catch (error) {
        console.error(`[STEAM-LOGIN] Xdotool hatası:`, error.message);
    }
}

module.exports = { performLogin };

