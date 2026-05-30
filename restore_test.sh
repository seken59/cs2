#!/bin/bash
# KO-LMS V8 Restore Dry-Run Testi
# Haftada bir cron ile çalıştırılmalıdır. (0 4 * * 0 /home/cs.serkaneken.com/public_html/restore_test.sh)

BACKUP_DIR="/home/cs.serkaneken.com/backups"
ARCHIVE_PASS="KO_LMS_BACKUP_SECURE_2026!"

LATEST_BACKUP=$(ls -t "$BACKUP_DIR"/KOLMS_DB_*.enc 2>/dev/null | head -n 1)

if [ -z "$LATEST_BACKUP" ]; then
    echo "[HATA] Test edilecek backup bulunamadi."
    exit 1
fi

echo "[INFO] Restore testi basliyor: $LATEST_BACKUP"

# Dry-run: Sadece şifreyi çözebiliyor mu ve dosya bütünlüğü sağlam mı test edilir.
# Gerçek bir veritabanına yazılmaz.
if openssl enc -aes-256-cbc -salt -pbkdf2 -iter 100000 -d -k "$ARCHIVE_PASS" -in "$LATEST_BACKUP" | tar -tzf - > /dev/null 2>&1; then
    echo "[SUCCESS] Restore Testi Basarili. Backup saglam ve decrypte edilebiliyor."
    # Gerçek sistemde burada mysql test db import testi yapılabilir.
    exit 0
else
    echo "[CRITICAL] Restore Testi BASARISIZ! Backup bozuk veya parola yanlis."
    # Burada Telegram'a HTTP POST ile uyarı gönderilir
    exit 1
fi
