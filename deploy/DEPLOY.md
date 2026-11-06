# QRONEX — going live

Everything below is run **by you or your server admin**. Claude does not run commands on servers.
Target: one Ubuntu 24.04 LTS VPS (2 vCPU / 4 GB is plenty to start), domain `qronex.app` (A records for `qronex.app` and `www` → server IP). `.app` domains only work over HTTPS, so finish step 4 (certbot) before testing in a browser.

## 1. Server packages (once)
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server supervisor git unzip certbot python3-certbot-nginx \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
sudo timedatectl set-timezone Asia/Kolkata         # server clock (app stores UTC)
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable
```

## 2. Database (once)
```bash
sudo mysql_secure_installation
sudo mysql -e "CREATE DATABASE qronex CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'qronex'@'localhost' IDENTIFIED BY '<strong password>';
  GRANT ALL ON qronex.* TO 'qronex'@'localhost';"
```
Create `/root/.my.cnf` with `[client] user=qronex password=<same>` (chmod 600) for backups.

## 3. Code (once)
Repo: https://github.com/ametecsindia/QRONEX (public: never commit `.env`). Then:
```bash
sudo mkdir -p /var/www/qronex && sudo chown $USER:www-data /var/www/qronex
git clone https://github.com/ametecsindia/QRONEX.git /var/www/qronex && cd /var/www/qronex
composer install --no-dev --optimize-autoloader
cp deploy/env.production.example .env   # fill every <…>
php artisan key:generate
php artisan migrate --force
QRONEX_ADMIN_EMAIL=you@ametecsindia.com php artisan db:seed --class=ProductionSeeder --force   # prints a one-time password
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache && php artisan route:cache && php artisan view:cache
```
**Never run plain `php artisan db:seed` on the server** — that loads demo data (it refuses in production anyway).

## 4. Web server + HTTPS
```bash
sudo cp deploy/nginx-qronex.conf /etc/nginx/sites-available/qronex
sudo ln -s /etc/nginx/sites-available/qronex /etc/nginx/sites-enabled/ && sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d qronex.app -d www.qronex.app      # free certificate, auto-renews
```

## 5. Background worker + scheduler + backups
```bash
sudo cp deploy/supervisor-qronex-worker.conf /etc/supervisor/conf.d/qronex-worker.conf
sudo supervisorctl reread && sudo supervisorctl update
sudo crontab -u www-data -e     # add the schedule:run line from deploy/crontab.txt
sudo crontab -e                 # add the backup line from deploy/crontab.txt
```

## 6. Integrations
- **Razorpay:** Dashboard → Webhooks → URL `https://qronex.app/api/razorpay/webhook`, events `payment.captured`, `payment.failed`, `order.paid`, copy the secret into `RAZORPAY_WEBHOOK_SECRET`. Start with test keys, switch to live after a real ₹1 test.
- **Email:** any SMTP (Zoho, Google Workspace, Amazon SES). Add SPF + DKIM DNS records for the sending domain.
- **SMS:** DLT registration (principal entity + 4 templates in Super Admin → Email & SMS), then the gateway details.
- **Uptime monitor** (UptimeRobot / BetterStack, free): watch `https://qronex.app/health` (200 = healthy).

## 7. Check
```bash
php artisan qronex:preflight     # must show no FAIL
```
Also: Super Admin → **System** shows the same checklist and any failed background jobs.
Sign in as the admin, change the password, set up two-factor sign-in (required for admins in production).

## Updating later
```bash
cd /var/www/qronex && ./deploy/deploy.sh
```

## Restore from backup
```bash
gunzip < /var/backups/qronex/qronex-YYYYMMDD-HHMM.sql.gz | mysql qronex
```
Test a restore on a spare database once a quarter.
