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

BACKUP_FILE="$BACKUP_DIR/KOLMS_DB_$TIMESTAMP.tar.gz.gpg"

# DB hook for STARTED
mysql -u "$DATABASE_USR" -p"$DATABASE_PWD" -e "INSERT INTO ${DB_NAME}.backup_runs (backup_file, backup_type, status, started_at) VALUES ('$BACKUP_FILE', 'FULL', 'STARTED', NOW());"

# Hata durumunda plain sql dosyasını temizlemek için trap ekleyelim
cleanup() {
    local exit_code=$?
    rm -f "${BACKUP_DIR}/db_${TIMESTAMP}.sql"
    if [ $exit_code -ne 0 ]; then
        echo "[CRITICAL] Backup basarisiz oldu!"
        mysql -u "$DATABASE_USR" -p"$DATABASE_PWD" -e "UPDATE ${DB_NAME}.backup_runs SET status='FAILED', finished_at=NOW(), error_message='Backup script failed with exit code $exit_code' WHERE backup_file='$BACKUP_FILE';"
        mysql -u "$DATABASE_USR" -p"$DATABASE_PWD" -e "INSERT INTO ${DB_NAME}.system_alerts (severity, alert_type, message, created_at) VALUES ('CRITICAL', 'BACKUP_FAILED', 'Backup basarisiz oldu: $BACKUP_FILE', NOW());"
    fi
    exit $exit_code
}
trap cleanup EXIT INT TERM

# 1. MySQL Dump
echo "[INFO] MySQL Dump aliniyor..."
mysqldump -u "$DATABASE_USR" -p"$DATABASE_PWD" "$DB_NAME" > "$BACKUP_DIR/db_$TIMESTAMP.sql"

# 2. Arşivi Şifreleme (GPG - AES256 Authenticated Encryption)
echo "[INFO] DB Backup GPG (AES-256) ile sifreleniyor..."
tar -czf - -C "$BACKUP_DIR" "db_$TIMESTAMP.sql" | gpg --symmetric --cipher-algo AES256 --batch --passphrase "$ZIP_PWD" -o "$BACKUP_FILE"

# 3. SYSTEM_KEY (.env) Ayri Sifreleme
echo "[INFO] SYSTEM_KEY ayri sifreleniyor..."
gpg --symmetric --cipher-algo AES256 --batch --passphrase "$ZIP_PWD" -o "$BACKUP_DIR/KOLMS_KEY_$TIMESTAMP.env.gpg" "$ENV_PATH"

# 4. Temizlik (Düz metin sql silinir)
rm -f "$BACKUP_DIR/db_$TIMESTAMP.sql"

# 5. Eski Yedekleri Silme (Son 7 Gün)
find "$BACKUP_DIR" -type f -name "*.gpg" -mtime +7 -exec rm {} \;

# Basari durumu
FILE_SIZE=$(stat -c%s "$BACKUP_FILE")
CHECKSUM=$(sha256sum "$BACKUP_FILE" | awk '{print $1}')

mysql -u "$DATABASE_USR" -p"$DATABASE_PWD" -e "UPDATE ${DB_NAME}.backup_runs SET status='COMPLETED', finished_at=NOW(), size_bytes=$FILE_SIZE, checksum_sha256='$CHECKSUM' WHERE backup_file='$BACKUP_FILE';"

# Trap successful exit'i ignore etmesi için
trap - EXIT INT TERM

echo "[SUCCESS] Yedekleme Tamamlandi: $BACKUP_FILE"

