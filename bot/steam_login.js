const { execSync } = require('child_process');
const SteamTotp = require('steam-totp');

/**
 * Xdotool kullanarak Steam arayüzüne şifre ve 2FA kodunu yazar.
 * Bu script Xvfb ortamında (DISPLAY=:99) çalıştırılmalıdır.
 */

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function performLogin(account) {
    console.log(`[STEAM-LOGIN] X11 arayüzünde login simülasyonu başlatılıyor... (${account.username})`);
    
    // Steam login penceresinin gelmesini bekle (Tahmini 10 saniye)
    console.log(`[STEAM-LOGIN] Steam arayüzünün yüklenmesi bekleniyor...`);
    await delay(10000);

    // Xdotool ile aktif pencereye şifreyi yaz ve Enter'a bas
    try {
        console.log(`[STEAM-LOGIN] Şifre enjekte ediliyor...`);
        execSync(`xdotool type "${account.password}"`);
        execSync(`xdotool key Return`);
        
        await delay(5000); // 2FA ekranının gelmesini bekle

        if (account.sharedSecret) {
            console.log(`[STEAM-LOGIN] Steam Guard 2FA Kodu üretiliyor...`);
            const authCode = SteamTotp.generateAuthCode(account.sharedSecret);
            // 2FA code is NEVER logged
            
            execSync(`xdotool type "${authCode}"`);
            execSync(`xdotool key Return`);
            console.log(`[STEAM-LOGIN] 2FA Kodu enjekte edildi.`);
        }
        
        console.log(`[STEAM-LOGIN] Login işlemi tamamlandı. CS2 başlatılmaya hazır.`);

    } catch (error) {
        console.error(`[STEAM-LOGIN] Xdotool hatası:`, error.message);
    }
}

module.exports = { performLogin };

