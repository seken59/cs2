const crypto = require('crypto');

const rawKey = process.env.MASTER_KEY;

if (!rawKey) {
    throw new Error("CRITICAL: MASTER_KEY environment variable is required.");
}

const MASTER_KEY = Buffer.from(rawKey, 'base64');
if (MASTER_KEY.length !== 32) {
    throw new Error("CRITICAL: MASTER_KEY must be exactly 32 bytes base64 encoded.");
}

const ALGORITHM = 'aes-256-gcm';

function encrypt(text) {
    if (!text) return text;
    const iv = crypto.randomBytes(12); // GCM için 12 byte zorunlu standart
    const cipher = crypto.createCipheriv(ALGORITHM, MASTER_KEY, iv);
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
        
        const decipher = crypto.createDecipheriv(ALGORITHM, MASTER_KEY, iv);
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
