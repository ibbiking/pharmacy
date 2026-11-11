# Production deployment (Roadmap 13g)

One Ubuntu 22.04/24.04 server running nginx + PHP-FPM 8.3 + MySQL 8, a queue worker under supervisor and the Laravel
scheduler from cron. Replace `app.example.com` and `/var/www/pharmacy` with your own values.

## 1. Server packages

```bash
sudo add-apt-repository -y ppa:ondrej/php && sudo apt-get update
sudo apt-get install -y nginx mysql-server supervisor git unzip curl \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt-get install -y nodejs   # only to build assets
curl -fsSL https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
```

## 2. Database

```sql
CREATE DATABASE pharmacy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pharmacy'@'localhost' IDENTIFIED BY '<a long random password>';
GRANT ALL PRIVILEGES ON pharmacy.* TO 'pharmacy'@'localhost';
```

## 3. Code and `.env`

```bash
sudo mkdir -p /var/www/pharmacy && sudo chown $USER:www-data /var/www/pharmacy
git clone https://github.com/ibbiking/pharmacy.git /var/www/pharmacy && cd /var/www/pharmacy
git checkout upgrade/php83-laravel13
cp .env.example .env && php artisan key:generate
```

Production values in `.env` (never commit it):

| Key | Value |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://app.example.com` |
| `DB_*` | the database above |
| `QUEUE_CONNECTION` | `database` |
| `SESSION_DRIVER` / `SESSION_SECURE_COOKIE` | `database` or `file` / `true` |
| `LOG_CHANNEL` / `LOG_LEVEL` | `daily` / `warning` |
| `MAIL_*` | the platform mailer (businesses can add their own SMTP in Settings → Email) |
| `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` | live keys; webhook URL `https://app.example.com/api/stripe/webhook` |
| `INERTIA_DEVTOOLS_ENABLED` | leave empty (off) |

## 4. Install, build, migrate, cache

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build            # public/build is not in git
php artisan migrate --force
php artisan db:seed --force        # first install only (roles, currencies, billing catalogue)
php artisan app:super-admin you@example.com
php artisan storage:link
php artisan optimize               # config, routes, views, events cached
php artisan permission:cache-reset
sudo chown -R $USER:www-data storage bootstrap/cache && sudo chmod -R ug+rw storage bootstrap/cache
```

## 5. nginx + PHP-FPM

`/etc/nginx/sites-available/pharmacy`:

```nginx
server {
    listen 80;
    server_name app.example.com;
    root /var/www/pharmacy/public;
    index index.php;
    client_max_body_size 20m;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
    location ~* \.(js|css|woff2?|png|jpg|svg|ico)$ { expires 30d; access_log off; try_files $uri =404; }
    location ~ /\.(?!well-known) { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/pharmacy /etc/nginx/sites-enabled/ && sudo nginx -t && sudo systemctl reload nginx
```

HTTPS: `sudo apt-get install -y certbot python3-certbot-nginx && sudo certbot --nginx -d app.example.com`. The app sends
HSTS itself once it is served over HTTPS; if a load balancer ends TLS, it must send `X-Forwarded-Proto` (trusted in
`bootstrap/app.php`).

## 6. Queue worker (supervisor)

Emails, stock alerts, imports, data exports and Stripe webhooks run on the queue. `/etc/supervisor/conf.d/pharmacy-worker.conf`:

```ini
[program:pharmacy-worker]
command=php /var/www/pharmacy/artisan queue:work database --sleep=2 --tries=3 --backoff=10 --max-time=3600
user=www-data
numprocs=2
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/pharmacy/storage/logs/worker.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status
```

## 7. Scheduler (cron)

`sudo crontab -u www-data -e`:

```
* * * * * cd /var/www/pharmacy && php artisan schedule:run >> /dev/null 2>&1
```

Runs: generic import safety sweep, stale POS reservations (15 min), `billing:enforce-entitlements` (02:30),
`exports:prune` (01:15), `backup:clean` (01:30), `backup:run` (02:00).

## 8. Backups

`backup:run` saves the database and uploads (not the code) to the disks in `config/backup.php`. Keep at least one copy
**off the server** (S3 or another machine: add a disk and list it in `backup.backup.destination.disks`). Test a
restore every month: `gunzip < db-dump.sql.gz | mysql pharmacy_restore_test`.

## 9. Logs and health

- App: `storage/logs/laravel-*.log` (daily). Worker: `storage/logs/worker.log`. nginx: `/var/log/nginx/`.
- Failed jobs: `php artisan queue:failed`, retry with `php artisan queue:retry all`.
- Super admin → Subscriptions shows Stripe / webhook health.

## 10. Updating

```bash
cd /var/www/pharmacy
php artisan down --render="errors::503"
mysqldump --no-tablespaces pharmacy | gzip > ~/backups/pharmacy_$(date +%F_%H%M).sql.gz   # always before migrating
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize && php artisan permission:cache-reset
php artisan queue:restart          # workers pick up the new code
php artisan up
```

## 11. Rollback

```bash
php artisan down
git checkout <previous commit or tag>
composer install --no-dev --optimize-autoloader && npm ci && npm run build
# only if the release added migrations and you must undo them:
php artisan migrate:rollback --step=<number of new migrations> --force
# or restore the dump taken before the update:
# gunzip < ~/backups/pharmacy_<stamp>.sql.gz | mysql pharmacy
php artisan optimize && php artisan queue:restart && php artisan up
```

## Performance notes (13f)

- Composite indexes for reports and stock (`2026_11_11_100000_add_report_and_stock_indexes`): invoices and returns by
  business + date, sold lines by business + product, lots by product + expiry + remaining (FEFO), purchases by
  business + expiry.
- `php artisan optimize` caches config, routes, views and events. Run it after every update (it is in step 10).
- OPcache is on by default in php8.3-fpm; set `opcache.validate_timestamps=0` in production and restart FPM after updates.
