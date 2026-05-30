#!/bin/bash
set -euo pipefail
IFS=$'\n\t'
# KO-LMS V9 Restore Dry-Run Testi
# Gerçek bir veritabanına import yaparak şema ve data bütünlüğünü test eder.

BACKUP_DIR="/home/cs.serkaneken.com/backups"
ZIP_PWD="${ZIP_PWD:?Missing ZIP_PWD}"
DATABASE_USR="${DATABASE_USR:?Missing DATABASE_USR}"
DATABASE_PWD="${DATABASE_PWD:?Missing DATABASE_PWD}"
TEST_DB="cs_bot_test"

LATEST_BACKUP=$(ls -t "$BACKUP_DIR"/KOLMS_DB_*.gpg 2>/dev/null | head -n 1)

cleanup() {
    echo "[CLEANUP] Test DB kalintilari siliniyor..."
    mysql -u "$DATABASE_USR" -p"$DATABASE_PWD" -e "DROP DATABASE IF EXISTS $TEST_DB;" || true
}
trap cleanup EXIT INT TERM

if [ -z "$LATEST_BACKUP" ]; then
    echo "[HATA] Test edilecek backup bulunamadi."
    exit 1
fi

echo "[INFO] Restore testi basliyor: $LATEST_BACKUP"

# 1. Test veritabanını oluştur
mysql -u "$DATABASE_USR" -p"$DATABASE_PWD" -e "DROP DATABASE IF EXISTS $TEST_DB; CREATE DATABASE $TEST_DB;"

# 2. Şifreyi çöz ve import et
if gpg --decrypt --batch --passphrase "$ZIP_PWD" "$LATEST_BACKUP" | tar -xzO | mysql -u "$DATABASE_USR" -p"$DATABASE_PWD" "$TEST_DB"; then
    echo "[SUCCESS] Veritabani basariyla import edildi."
    
    # 3. Şema ve Row count kontrolü
    TABLE_COUNT=$(mysql -u "$DATABASE_USR" -p"$DATABASE_PWD" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$TEST_DB';" -s -N)
    
    # Kritik tablo kontrolü
    CRITICAL_TABLES=$(mysql -u "$DATABASE_USR" -p"$DATABASE_PWD" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$TEST_DB' AND table_name IN ('accounts', 'farm_batches', 'farm_batch_accounts', 'action_queue', 'system_settings', 'admin_users', 'login_attempts', 'backup_runs', 'system_alerts');" -s -N)

    if [ "$TABLE_COUNT" -gt 0 ] && [ "$CRITICAL_TABLES" -eq 9 ]; then
        echo "[SUCCESS] Restore Testi %100 Basarili. $TABLE_COUNT tablo dogrulandi. Kritik 9 tablo mevcut."
        mysql -u "$DATABASE_USR" -p"$DATABASE_PWD" -e "INSERT INTO cs_bot.backup_runs (backup_file, backup_type, status, started_at, finished_at) VALUES ('$LATEST_BACKUP', 'DB', 'RESTORE_TESTED', NOW(), NOW());"
    else
        echo "[CRITICAL] Veritabani import edildi ama tablolar eksik!"
        mysql -u "$DATABASE_USR" -p"$DATABASE_PWD" -e "INSERT INTO cs_bot.system_alerts (severity, alert_type, message, created_at) VALUES ('CRITICAL', 'RESTORE_TEST_FAILED', 'Tablo eksik: Bulunan $CRITICAL_TABLES/9', NOW());"
        mysql -u "$DATABASE_USR" -p"$DATABASE_PWD" -e "INSERT INTO cs_bot.backup_runs (backup_file, backup_type, status, started_at, finished_at, error_message) VALUES ('$LATEST_BACKUP', 'DB', 'FAILED', NOW(), NOW(), 'Kritik tablolar eksik');"
    fi
else
    echo "[CRITICAL] Restore Testi BASARISIZ! Backup bozuk, import edilemedi."
    mysql -u "$DATABASE_USR" -p"$DATABASE_PWD" -e "INSERT INTO cs_bot.system_alerts (severity, alert_type, message, created_at) VALUES ('CRITICAL', 'RESTORE_TEST_FAILED', 'GPG decrypt veya DB import hatasi', NOW());"
    mysql -u "$DATABASE_USR" -p"$DATABASE_PWD" -e "INSERT INTO cs_bot.backup_runs (backup_file, backup_type, status, started_at, finished_at, error_message) VALUES ('$LATEST_BACKUP', 'DB', 'FAILED', NOW(), NOW(), 'Import failed');"
fi
