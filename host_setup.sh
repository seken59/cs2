#!/bin/bash
# KO-LMS Host Setup Script
# Bu script Docker konteynerleri başlamadan ÖNCE host makinede (root yetkisiyle) çalıştırılmalıdır.
# Amacı: OverlayFS işlemlerini Host seviyesine taşıyarak konteynerleri Privileged modundan kurtarmak.

set -euo pipefail

MASTER_DIR="/home/cs.serkaneken.com/cs2_master"
BASE_DIR="/home/cs.serkaneken.com"

# Master dizini kontrol et
if [ ! -d "$MASTER_DIR" ]; then
    echo "[HATA] $MASTER_DIR bulunamadı. Lütfen temiz CS2 klasörünü bu dizine koyun."
    exit 1
fi

echo "[INFO] Botlar için Host OverlayFS dizinleri hazırlanıyor..."

# 4 Bot için döngü (Daha fazla bot için buradaki rakamı artırabilirsiniz)
for i in {1..4}; do
    WRITE_DIR="$BASE_DIR/cs2_write/bot$i"
    WORK_DIR="$BASE_DIR/cs2_work/bot$i"
    MERGED_DIR="$BASE_DIR/cs2_merged/bot$i"

    mkdir -p "$WRITE_DIR"
    mkdir -p "$WORK_DIR"
    mkdir -p "$MERGED_DIR"

    # Eğer daha önce mount edildiyse, temizle
    if mountpoint -q "$MERGED_DIR"; then
        umount "$MERGED_DIR"
    fi

    # OverlayFS Mount (Host seviyesinde)
    mount -t overlay overlay -o lowerdir="$MASTER_DIR",upperdir="$WRITE_DIR",workdir="$WORK_DIR" "$MERGED_DIR"
    
    # İzinleri Docker'ın steamuser'ı (Örn: UID 1000) erişebilecek şekilde ayarla
    chown -R 1000:1000 "$WRITE_DIR"
    chown -R 1000:1000 "$WORK_DIR"
    chown -R 1000:1000 "$MERGED_DIR"
    
    chmod -R 750 "$WRITE_DIR"
    chmod -R 750 "$WORK_DIR"
    chmod -R 750 "$MERGED_DIR"

    echo "[OK] Bot-$i OverlayFS Mount Başarılı: $MERGED_DIR"
done

echo "[SONUÇ] Tüm OverlayFS diskleri hazır. Artık 'docker-compose up -d' ile güvenli başlatma yapabilirsiniz."

