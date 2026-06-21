# Panduan Deploy Solusi Kopi ke Hostinger VPS

## Prasyarat
- Hostinger VPS (KVM 1 minimum - 1 vCPU, 4GB RAM, 50GB SSD)
- Domain yang sudah diarahkan (DNS A record) ke IP VPS
- OS: Ubuntu 22.04 atau 24.04

---

## Step 1: Akses VPS via SSH

```bash
ssh root@IP_VPS_KAMU
```

## Step 2: Upload & Jalankan Setup Script

```bash
# Download setup script
curl -O https://raw.githubusercontent.com/Ice192/Solusi_kopi/main/docker/hostinger/setup-server.sh

# Jalankan
chmod +x setup-server.sh
sudo bash setup-server.sh
```

Ini akan install: Nginx, PHP 8.3, MySQL, Composer, Node.js 20, Certbot.

## Step 3: Setup Database MySQL

```bash
# Amankan MySQL
sudo mysql_secure_installation

# Buat database & user
sudo mysql -e "
CREATE DATABASE solusi_kopi;
CREATE USER 'solusi_kopi'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON solusi_kopi.* TO 'solusi_kopi'@'localhost';
FLUSH PRIVILEGES;
"
```

> Catat password database yang kamu buat!

## Step 4: Deploy Aplikasi

```bash
# Download deploy script
curl -O https://raw.githubusercontent.com/Ice192/Solusi_kopi/main/docker/hostinger/deploy.sh

# Edit domain di script
nano deploy.sh
# Ganti DOMAIN="DOMAIN_KAMU.com" dengan domain asli

# Jalankan
chmod +x deploy.sh
sudo bash deploy.sh
```

## Step 5: Edit .env (saat diminta oleh script)

Saat deploy script meminta, edit file `.env`:

```bash
nano /var/www/solusi-kopi/.env
```

**Yang WAJIB diubah:**

```env
APP_NAME="Solusi Kopi"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domainmu.com

DB_DATABASE=solusi_kopi
DB_USERNAME=solusi_kopi
DB_PASSWORD=PASSWORD_YANG_KAMU_BUAT_DI_STEP3

MIDTRANS_SERVER_KEY=xxx      # <-- Minta ke developer/client
MIDTRANS_CLIENT_KEY=xxx      # <-- Minta ke developer/client
MIDTRANS_IS_PRODUCTION=true  # <-- Minta ke developer/client
MIDTRANS_MERCHANT_ID=xxx     # <-- Minta ke developer/client
```

> **Note:** Credentials Midtrans, Google OAuth, dan Facebook OAuth bukan tanggung jawab hosting. Minta ke developer/pemilik project.

## Step 6: Verifikasi

Buka browser dan akses `https://domainmu.com`. Pastikan:
- [x] Halaman landing tampil
- [x] HTTPS aktif (gembok hijau)
- [x] Login page bisa diakses

---

## Perintah Berguna

```bash
# Re-deploy setelah ada update dari developer
cd /var/www/solusi-kopi
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R www-data:www-data /var/www/solusi-kopi
sudo systemctl reload nginx

# Cek log error
tail -f /var/www/solusi-kopi/storage/logs/laravel.log
tail -f /var/log/nginx/solusi-kopi-error.log

# Restart services
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx
sudo systemctl restart mysql

# Perpanjang SSL otomatis (sudah auto via certbot, cek dengan):
sudo certbot renew --dry-run
```

---

## Catatan untuk Hosting Person

Yang **bukan** tanggung jawab kamu (serahkan ke developer):
- Kredensial Midtrans (server key, client key, merchant ID)
- Kredensial Google/Facebook OAuth
- Fix bug atau issue di kode aplikasi
- Konfigurasi seeder untuk production

Yang **tanggung jawab** kamu:
- Server tetap online & ter-update
- SSL tetap aktif
- Database di-backup rutin
- Deploy ulang jika developer push update
