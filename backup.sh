#!/bin/bash
# KO-LMS V8 Otonom Yedekleme Scripti
# Kullanım: crontab -e -> 0 3 * * * /home/cs.serkaneken.com/public_html/backup.sh

BACKUP_DIR="/home/cs.serkaneken.com/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DB_USER="cs_admin"
DB_PASS="zz12JkE3O@10gFr1"
DB_NAME="cs_bot"
ENV_PATH="/home/cs.serkaneken.com/farm_db/.env"
ARCHIVE_PASS="KO_LMS_BACKUP_SECURE_2026!" # Parola ortam değişkeninden alınmalıdır.

mkdir -p "$BACKUP_DIR"

# 1. MySQL Dump
echo "[INFO] MySQL Dump aliniyor..."
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_DIR/db_$TIMESTAMP.sql"

# 2. Arşivi Şifreleme (AES-256-GCM yerine AES-256-CBC + pbkdf2)
# AI'ın uyarısı: GCM veya HMAC kullanılması.
echo "[INFO] DB Backup AES-256-CBC ve PBKDF2 (HMAC simülasyonu) ile sifreleniyor..."
tar -czf - -C "$BACKUP_DIR" "db_$TIMESTAMP.sql" | openssl enc -aes-256-cbc -salt -pbkdf2 -iter 100000 -e -k "$ARCHIVE_PASS" -out "$BACKUP_DIR/KOLMS_DB_$TIMESTAMP.tar.gz.enc"

# 3. MASTER_KEY (.env) Ayri Sifreleme
echo "[INFO] MASTER_KEY ayri sifreleniyor..."
openssl enc -aes-256-cbc -salt -pbkdf2 -iter 100000 -e -k "$ARCHIVE_PASS" -in "$ENV_PATH" -out "$BACKUP_DIR/KOLMS_KEY_$TIMESTAMP.env.enc"

# 4. Temizlik (Düz metin sql silinir)
rm -f "$BACKUP_DIR/db_$TIMESTAMP.sql"

# 5. Eski Yedekleri Silme (Son 7 Gün)
find "$BACKUP_DIR" -type f -name "*.enc" -mtime +7 -exec rm {} \;

echo "[SUCCESS] Yedekleme Tamamlandi: $BACKUP_DIR/KOLMS_DB_$TIMESTAMP.tar.gz.enc"
