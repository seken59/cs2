#!/bin/bash
# Kapsamlı Hata Ayıklama Modu
set -e

# Mount işlemi artık Host makinede (host_setup.sh) yapılıyor.
# Konteyner sadece okuma/yazma işlemlerini devralıyor.

echo "[ENTRYPOINT] ydotoold (Kernel-Level Input Daemon) başlatılıyor..."
ydotoold --socket-path=/tmp/.ydotool_socket &
sleep 1
chmod 777 /tmp/.ydotool_socket
export YDOTOOL_SOCKET=/tmp/.ydotool_socket

echo "[ENTRYPOINT] ydotoold aktif. Node.js Botu başlatılıyor..."
# Steamuser olarak geçiş yapıp botu çalıştır
exec su - steamuser -c "export YDOTOOL_SOCKET=/tmp/.ydotool_socket && cd /home/steamuser/app && node bot/csgo.js"
