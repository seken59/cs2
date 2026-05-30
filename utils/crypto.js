const crypto = require('crypto');

// Master Key for AES-256-GCM. 32-bytes required.
// Çevresel değişkenden alınmalı, yoksa varsayılan (TEHLİKELİ) kullanılır.
const MASTER_KEY = process.env.ENCRYPTION_KEY || 'KOLmsSuperSecretEncryptionKey123';
const ALGORITHM = 'aes-256-gcm';

function encrypt(text) {
    if (!text) return text;
    const iv = crypto.randomBytes(12); // GCM için 12 byte önerilir
    const cipher = crypto.createCipheriv(ALGORITHM, Buffer.from(MASTER_KEY.padEnd(32, '0').slice(0, 32)), iv);
    let encrypted = cipher.update(text, 'utf8', 'hex');
    encrypted += cipher.final('hex');
    const authTag = cipher.getAuthTag().toString('hex');
    return iv.toString('hex') + ':' + encrypted + ':' + authTag;
}

function decrypt(text) {
    if (!text || !text.includes(':')) return text;
    try {
        const textParts = text.split(':');
        if (textParts.length !== 3) return null; // iv:ciphertext:authTag
        const iv = Buffer.from(textParts[0], 'hex');
        const encryptedText = textParts[1];
        const authTag = Buffer.from(textParts[2], 'hex');
        
        const decipher = crypto.createDecipheriv(ALGORITHM, Buffer.from(MASTER_KEY.padEnd(32, '0').slice(0, 32)), iv);
        decipher.setAuthTag(authTag);
        let decrypted = decipher.update(encryptedText, 'hex', 'utf8');
        decrypted += decipher.final('utf8');
        return decrypted;
    } catch (e) {
        console.error('[CRYPTO ERROR] Decryption failed (Manipulation detected?):', e.message);
        return null;
    }
}

module.exports = { encrypt, decrypt };
