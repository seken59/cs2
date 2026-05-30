# Base Image: Debian 12 (Bookworm) - Steam ve Lavapipe için en modern/stabil depo.
FROM debian:bookworm-slim

ENV DEBIAN_FRONTEND=noninteractive
ENV DISPLAY=:99

# 32-bit kütüphane mimarisini ekle (Steam İstemcisi 32-bit gerektirir)
RUN dpkg --add-architecture i386 && \
    apt-get update && \
    apt-get install -y --no-install-recommends \
    xvfb \
    ydotool \
    wmctrl \
    imagemagick \
    curl \
    gnupg \
    ca-certificates \
    libnss3 \
    libgconf-2-4 \
    libasound2 \
    dbus-x11 \
    mesa-vulkan-drivers \
    mesa-vulkan-drivers:i386 \
    libvulkan1 \
    libvulkan1:i386 \
    steam-installer \
    locales && \
    rm -rf /var/lib/apt/lists/*

# Dil ayarları (Steam hatalarını önlemek için)
RUN sed -i -e 's/# en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen && locale-gen
ENV LANG=en_US.UTF-8

# Node.js 20 Kurulumu
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && \
    apt-get install -y nodejs && \
    rm -rf /var/lib/apt/lists/*

# Steam için kullanıcı oluştur (Root ile Steam çalıştırılamaz)
RUN useradd -m -s /bin/bash steamuser
WORKDIR /home/steamuser/app

# Kodları kopyala ve bağımlılıkları yükle
COPY package.json ./
RUN npm install

COPY bot/ ./bot/
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Dizin yetkileri
RUN chown -R steamuser:steamuser /home/steamuser

# Root olarak kalıyoruz çünkü OverlayFS mount işlemi sudo/root yetkisi gerektirir.
# entrypoint.sh içinde mount yaptıktan sonra node uygulamasını steamuser olarak başlatacağız.
ENTRYPOINT ["/entrypoint.sh"]
