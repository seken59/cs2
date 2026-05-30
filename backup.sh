#!/bin/bash
# KO-LMS V9 Otonom Yedekleme Scripti
# GPG (AES-256) simetrik şifreleme kullanılarak yedek alınır.

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

# 2. Arşivi Şifreleme (GPG - AES256 Authenticated Encryption)
echo "[INFO] DB Backup GPG (AES-256) ile sifreleniyor..."
tar -czf - -C "$BACKUP_DIR" "db_$TIMESTAMP.sql" | gpg --symmetric --cipher-algo AES256 --batch --passphrase "$ARCHIVE_PASS" -o "$BACKUP_DIR/KOLMS_DB_$TIMESTAMP.tar.gz.gpg"

# 3. MASTER_KEY (.env) Ayri Sifreleme
echo "[INFO] MASTER_KEY ayri sifreleniyor..."
gpg --symmetric --cipher-algo AES256 --batch --passphrase "$ARCHIVE_PASS" -o "$BACKUP_DIR/KOLMS_KEY_$TIMESTAMP.env.gpg" "$ENV_PATH"

# 4. Temizlik (Düz metin sql silinir)
rm -f "$BACKUP_DIR/db_$TIMESTAMP.sql"

# 5. Eski Yedekleri Silme (Son 7 Gün)
find "$BACKUP_DIR" -type f -name "*.gpg" -mtime +7 -exec rm {} \;

echo "[SUCCESS] Yedekleme Tamamlandi: $BACKUP_DIR/KOLMS_DB_$TIMESTAMP.tar.gz.gpg"
