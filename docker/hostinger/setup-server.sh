#!/usr/bin/env bash
# ==============================================================
# Solusi Kopi - Hostinger VPS Server Setup Script
# Jalankan sebagai root: sudo bash setup-server.sh
# Tested on Ubuntu 22.04 / 24.04
# ==============================================================

set -euo pipefail

echo "========================================="
echo "  Solusi Kopi - Server Setup"
echo "========================================="

# --- 1. Update system ---
echo "[1/7] Updating system packages..."
apt update && apt upgrade -y

# --- 2. Install Nginx ---
echo "[2/7] Installing Nginx..."
apt install -y nginx
systemctl enable nginx
systemctl start nginx

# --- 3. Install PHP 8.3 & extensions ---
echo "[3/7] Installing PHP 8.3..."
apt install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt update

apt install -y \
    php8.3-fpm \
    php8.3-cli \
    php8.3-mysql \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-bcmath \
    php8.3-intl \
    php8.3-gd \
    php8.3-zip \
    php8.3-curl \
    php8.3-opcache \
    php8.3-readline

systemctl enable php8.3-fpm
systemctl start php8.3-fpm

# --- 4. Install MySQL 8 ---
echo "[4/7] Installing MySQL..."
apt install -y mysql-server
systemctl enable mysql
systemctl start mysql

echo ""
echo ">> MySQL installed. Jalankan 'sudo mysql_secure_installation' setelah setup selesai."
echo ""

# --- 5. Install Composer ---
echo "[5/7] Installing Composer..."
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# --- 6. Install Node.js 20 (untuk build frontend) ---
echo "[6/7] Installing Node.js 20..."
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

# --- 7. Install utilities ---
echo "[7/7] Installing utilities..."
apt install -y git unzip certbot python3-certbot-nginx

echo ""
echo "========================================="
echo "  Server setup selesai!"
echo "========================================="
echo ""
echo "Langkah selanjutnya:"
echo "  1. sudo mysql_secure_installation"
echo "  2. Buat database: sudo mysql -e \"CREATE DATABASE solusi_kopi; CREATE USER 'solusi_kopi'@'localhost' IDENTIFIED BY 'PASSWORD_KAMU'; GRANT ALL ON solusi_kopi.* TO 'solusi_kopi'@'localhost'; FLUSH PRIVILEGES;\""
echo "  3. Jalankan: bash deploy.sh"
echo ""
