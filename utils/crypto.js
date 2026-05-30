const crypto = require('crypto');

const rawKey = process.env.SYSTEM_KEY;

if (!rawKey) {
    throw new Error("CRITICAL: SYSTEM_KEY environment variable is required.");
}

const SYSTEM_KEY = Buffer.from(rawKey, 'base64');
if (SYSTEM_KEY.length !== 32) {
    throw new Error("CRITICAL: SYSTEM_KEY must be exactly 32 bytes base64 encoded.");
}

const ALGORITHM = 'aes-256-gcm';

function encrypt(text) {
    if (!text) return text;
    const iv = crypto.randomBytes(12); // GCM için 12 byte zorunlu standart
    const cipher = crypto.createCipheriv(ALGORITHM, SYSTEM_KEY, iv);
    let encrypted = cipher.update(text, 'utf8', 'hex');
    encrypted += cipher.final('hex');
    const authTag = cipher.getAuthTag().toString('hex');
    return iv.toString('hex') + ':' + encrypted + ':' + authTag;
}

function decrypt(text) {
    if (!text) return null;
    if (!text.includes(':')) return null;
    try {
        const textParts = text.split(':');
        if (textParts.length !== 3) return null; // iv:ciphertext:authTag
        const iv = Buffer.from(textParts[0], 'hex');
        const encryptedText = textParts[1];
        const authTag = Buffer.from(textParts[2], 'hex');
        
        const decipher = crypto.createDecipheriv(ALGORITHM, SYSTEM_KEY, iv);
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

