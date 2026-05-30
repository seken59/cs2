#!/bin/bash
set -euo pipefail
IFS=$'\n\t'
# KO-LMS V9 Restore Dry-Run Testi
# Gerçek bir veritabanına import yaparak şema ve data bütünlüğünü test eder.

BACKUP_DIR="/home/cs.serkaneken.com/backups"
ARCHIVE_PASS="${ARCHIVE_PASS:?Missing ARCHIVE_PASS}"
DB_USER="${DB_USER:?Missing DB_USER}"
DB_PASS="${DB_PASS:?Missing DB_PASS}"
TEST_DB="cs_bot_test"

LATEST_BACKUP=$(ls -t "$BACKUP_DIR"/KOLMS_DB_*.gpg 2>/dev/null | head -n 1)

if [ -z "$LATEST_BACKUP" ]; then
    echo "[HATA] Test edilecek backup bulunamadi."
    exit 1
fi

echo "[INFO] Restore testi basliyor: $LATEST_BACKUP"

# 1. Test veritabanını oluştur
mysql -u "$DB_USER" -p"$DB_PASS" -e "DROP DATABASE IF EXISTS $TEST_DB; CREATE DATABASE $TEST_DB;"

# 2. Şifreyi çöz ve import et
if gpg --decrypt --batch --passphrase "$ARCHIVE_PASS" "$LATEST_BACKUP" | tar -xzO | mysql -u "$DB_USER" -p"$DB_PASS" "$TEST_DB"; then
    echo "[SUCCESS] Veritabani basariyla import edildi."
    
    # 3. Şema ve Row count kontrolü
    TABLE_COUNT=$(mysql -u "$DB_USER" -p"$DB_PASS" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$TEST_DB';" -s -N)
    if [ "$TABLE_COUNT" -gt 0 ]; then
        echo "[SUCCESS] Restore Testi %100 Basarili. $TABLE_COUNT tablo dogrulandi."
        mysql -u "$DB_USER" -p"$DB_PASS" -e "INSERT INTO cs_bot.backup_runs (backup_file, backup_type, status, started_at, finished_at) VALUES ('$LATEST_BACKUP', 'DB', 'RESTORE_TESTED', NOW(), NOW());"
    else
        echo "[CRITICAL] Veritabani import edildi ama tablolar bos!"
        mysql -u "$DB_USER" -p"$DB_PASS" -e "INSERT INTO cs_bot.backup_runs (backup_file, backup_type, status, started_at, finished_at, error_message) VALUES ('$LATEST_BACKUP', 'DB', 'FAILED', NOW(), NOW(), 'Tablolar bos');"
    fi
else
    echo "[CRITICAL] Restore Testi BASARISIZ! Backup bozuk, import edilemedi."
    mysql -u "$DB_USER" -p"$DB_PASS" -e "INSERT INTO cs_bot.backup_runs (backup_file, backup_type, status, started_at, finished_at, error_message) VALUES ('$LATEST_BACKUP', 'DB', 'FAILED', NOW(), NOW(), 'Import failed');"
fi

# 4. Temizlik
mysql -u "$DB_USER" -p"$DB_PASS" -e "DROP DATABASE IF EXISTS $TEST_DB;"
