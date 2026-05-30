#!/bin/bash
# Kapsamlı Hata Ayıklama Modu
set -euo pipefail

echo "[ENTRYPOINT] Cleanup trap ayarlanıyor..."
cleanup() {
    echo "[ENTRYPOINT] Sinyal alındı. Servisler kapatılıyor..."
    kill $(jobs -p) 2>/dev/null || true
}
trap cleanup EXIT INT TERM

echo "[ENTRYPOINT] ydotoold (Kernel-Level Input Daemon) başlatılıyor..."
ydotoold --socket-path=/tmp/.ydotool_socket &
sleep 1
chown 1000:1000 /tmp/.ydotool_socket
chmod 660 /tmp/.ydotool_socket
export YDOTOOL_SOCKET=/tmp/.ydotool_socket

echo "[ENTRYPOINT] ydotoold aktif. Node.js Botu başlatılıyor..."
exec su - steamuser -c "export YDOTOOL_SOCKET=/tmp/.ydotool_socket && cd /home/steamuser/app && node bot/csgo.js"

