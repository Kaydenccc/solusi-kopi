#!/usr/bin/env bash
# ==============================================================
# Solusi Kopi - Deploy Script untuk Hostinger VPS
# Jalankan sebagai root: sudo bash deploy.sh
# ==============================================================

set -euo pipefail

APP_DIR="/var/www/solusi-kopi"
REPO_URL="https://github.com/Ice192/Solusi_kopi.git"
DOMAIN="solusikopi.online"  # <-- GANTI dengan domain kamu

echo "========================================="
echo "  Solusi Kopi - Deploying..."
echo "========================================="

# --- 1. Clone / Pull repository ---
if [ -d "$APP_DIR" ]; then
    echo "[1/8] Pulling latest code..."
    cd "$APP_DIR"
    git pull origin main
else
    echo "[1/8] Cloning repository..."
    git clone "$REPO_URL" "$APP_DIR"
    cd "$APP_DIR"
fi

# --- 2. Install PHP dependencies ---
echo "[2/8] Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# --- 3. Setup .env (hanya pertama kali) ---
if [ ! -f "$APP_DIR/.env" ]; then
    echo "[3/8] Creating .env file..."
    cp .env.example .env
    php artisan key:generate
    echo ""
    echo ">> PENTING: Edit file .env sebelum lanjut!"
    echo ">> nano $APP_DIR/.env"
    echo ""
    echo ">> Yang WAJIB diisi:"
    echo "   - APP_URL=https://$DOMAIN"
    echo "   - APP_ENV=production"
    echo "   - APP_DEBUG=false"
    echo "   - DB_DATABASE, DB_USERNAME, DB_PASSWORD"
    echo "   - MIDTRANS keys (minta ke developer/client)"
    echo ""
    read -p "Tekan Enter setelah selesai edit .env..." _
else
    echo "[3/8] .env sudah ada, skip..."
fi

# --- 4. Install & build frontend ---
echo "[4/8] Building frontend assets..."
npm ci
npm run build

# --- 5. Laravel setup ---
echo "[5/8] Running Laravel setup..."
php artisan migrate --force
php artisan storage:link || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# --- 6. Set permissions ---
echo "[6/8] Setting permissions..."
chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# --- 7. Setup Nginx ---
echo "[7/8] Configuring Nginx..."
NGINX_CONF="/etc/nginx/sites-available/solusi-kopi"

if [ ! -f "$NGINX_CONF" ]; then
    cp "$APP_DIR/docker/hostinger/nginx-solusi-kopi.conf" "$NGINX_CONF"
    sed -i "s/solusikopi.online/$DOMAIN/g" "$NGINX_CONF"
    ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default
fi

nginx -t && systemctl reload nginx

# --- 8. Setup SSL (Let's Encrypt) ---
echo "[8/8] Setting up SSL..."
if [ ! -d "/etc/letsencrypt/live/$DOMAIN" ]; then
    certbot --nginx -d "$DOMAIN" -d "www.$DOMAIN" --non-interactive --agree-tos --email "admin@$DOMAIN"
else
    echo "SSL sudah terpasang, skip..."
fi

echo ""
echo "========================================="
echo "  Deploy selesai!"
echo "========================================="
echo "  URL: https://$DOMAIN"
echo "========================================="
