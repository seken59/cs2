#!/bin/bash
set -euo pipefail
IFS=$'\n\t'
# KO-LMS V9 Otonom Yedekleme Scripti
# GPG (AES-256) simetrik şifreleme kullanılarak yedek alınır.

BACKUP_DIR="/home/cs.serkaneken.com/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DATABASE_USR="${DATABASE_USR:?Missing DATABASE_USR}"
DATABASE_PWD="${DATABASE_PWD:?Missing DATABASE_PWD}"
DB_NAME="cs_bot"
ENV_PATH="/home/cs.serkaneken.com/farm_db/.env"
ZIP_PWD="${ZIP_PWD:?Missing ZIP_PWD}"

mkdir -p "$BACKUP_DIR"

# Hata durumunda plain sql dosyasını temizlemek için trap ekleyelim
cleanup() {
    rm -f "${BACKUP_DIR}/db_${TIMESTAMP}.sql"
}
trap cleanup EXIT

# 1. MySQL Dump
echo "[INFO] MySQL Dump aliniyor..."
mysqldump -u "$DATABASE_USR" -p"$DATABASE_PWD" "$DB_NAME" > "$BACKUP_DIR/db_$TIMESTAMP.sql"

# 2. Arşivi Şifreleme (GPG - AES256 Authenticated Encryption)
echo "[INFO] DB Backup GPG (AES-256) ile sifreleniyor..."
tar -czf - -C "$BACKUP_DIR" "db_$TIMESTAMP.sql" | gpg --symmetric --cipher-algo AES256 --batch --passphrase "$ZIP_PWD" -o "$BACKUP_DIR/KOLMS_DB_$TIMESTAMP.tar.gz.gpg"

# 3. SYSTEM_KEY (.env) Ayri Sifreleme
echo "[INFO] SYSTEM_KEY ayri sifreleniyor..."
gpg --symmetric --cipher-algo AES256 --batch --passphrase "$ZIP_PWD" -o "$BACKUP_DIR/KOLMS_KEY_$TIMESTAMP.env.gpg" "$ENV_PATH"

# 4. Temizlik (Düz metin sql silinir)
rm -f "$BACKUP_DIR/db_$TIMESTAMP.sql"

# 5. Eski Yedekleri Silme (Son 7 Gün)
find "$BACKUP_DIR" -type f -name "*.gpg" -mtime +7 -exec rm {} \;

echo "[SUCCESS] Yedekleme Tamamlandi: $BACKUP_DIR/KOLMS_DB_$TIMESTAMP.tar.gz.gpg"

