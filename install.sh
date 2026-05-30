#!/bin/bash
# AlmaLinux 9 - CS2 Farm Host Kurulum Scripti
# Bu script, 8 çekirdekli makineyi CS2 konteynerleri için hazırlar.

echo "[INFO] Sistem güncelleniyor ve gerekli paketler yükleniyor..."
dnf update -y
dnf install -y epel-release git curl wget jq sqlite

echo "[INFO] Docker ve Docker Compose kuruluyor..."
dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
dnf install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
systemctl start docker
systemctl enable docker

echo "[INFO] CS2 Master (Read-Only) dizini oluşturuluyor..."
mkdir -p /home/cs2_master
echo "[WARNING] Lütfen stripped (temizlenmiş) CS2 Linux Native dosyalarını /home/cs2_master dizinine yükleyin."

echo "[INFO] Veritabanı ve Log dizinleri oluşturuluyor..."
mkdir -p /home/cs2_farm/db
mkdir -p /home/cs2_farm/logs
touch /home/cs2_farm/db/farm.sqlite

echo "[INFO] Kurulum başarıyla tamamlandı. Node.js (Master) başlatılmaya hazır."
