# Production deployment (Roadmap 13g)

> **Live today (2026-11-20): GoDaddy cPanel shared hosting** (same account as new.alhamaad.com, IP 92.205.172.200):
> https://invora.alhamaad.com (live) and https://invora-demo.alhamaad.com (demo installation). Release with
> `bash deploy/cpanel-release.sh` (builds, uploads git HEAD, runs `deploy/cpanel-install.sh` on the server: composer,
> migrate, caches; first run also creates `.env`, the database link, base data, the demo). Database passwords live
> only on the server (`~/.invora-secrets`, mode 600). Cron (cPanel): `schedule:run` + `queue:work --stop-when-empty`
> every minute per site. No supervisor on shared hosting. The VPS steps below stay valid for a move to a VPS.


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
# optional: demo logins with data for client demos (§12): set DEMO_ACCOUNTS=true and DEMO_PASSWORD in .env first
php artisan db:seed --class='Database\Seeders\DemoDataSeeder' --force
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

### Release workflow

1. **Build on a branch** (e.g. `feature/...`). Before merging: `php artisan test` (green) and `npm run build` (no errors).
2. **Check it in a browser** on the local QA copy with the demo logins (`docs/DEMO_ACCOUNTS.md`).
3. **Merge** into the release branch (today `upgrade/php83-laravel13`), push it to GitHub, and tag the release:
   `git tag v2026.11.20 && git push origin v2026.11.20` (a tag is what you roll back to).
4. **Deploy on the server** with one command (as the code owner, not root):

```bash
cd /var/www/pharmacy && bash deploy.sh                # update the checked-out branch
bash deploy.sh upgrade/php83-laravel13 --seed-demo    # or name the branch; --seed-demo adds missing demo logins
```

`deploy.sh` stops if the server has local code changes, backs up the database to `~/backups/`, switches on maintenance
mode, fast-forwards to the pushed code, installs, builds, migrates, rebuilds the caches, restarts the queue workers
and goes live again. If any step fails the site stays in maintenance mode and the script prints the rollback steps.

5. **After deploying:** sign in, open the dashboard and the POS, and check `storage/logs/laravel-*.log` for new errors.

### The same steps by hand

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

## 12. Demo accounts and a demo server

`database/seeders/DemoDataSeeder.php` creates one owner with a business of **every type** (Demo Pharmacy, Demo
Inventory Store, Demo B2B Distributors), an accountant and a cashier, and fills each business through the real
screens' services: purchases (paid / on credit), warehouses, transfers, POS sales (cash and credit), a return, expenses
with approvals, books; the wholesale business also gets quotations → orders → invoices → shipment → payments → credit
notes and purchase orders → receipts → bills → payments → vendor credits. Dates are relative to the day it runs. The
logins are listed in `docs/DEMO_ACCOUNTS.md`; on a live server the password is **your** `DEMO_PASSWORD`, never the
documented test one.

`.env` keys (config/demo.php):

| Key | Meaning |
|---|---|
| `DEMO_ACCOUNTS` | `true` allows the demo seeder on a production server (it is on by default elsewhere). |
| `DEMO_PASSWORD` | Password of the three demo logins. Required on a production server. |
| `DEMO_INSTANCE` | `true` only on a separate demo installation with its **own database**: enables `demo:rebuild`. |
| `DEMO_REBUILD_AT` | Time of the nightly rebuild on a demo installation (default `03:00`). |

The demo logins can't change their email or password (Profile screen), so one client can't lock the next one out.
Their emails are not real mailboxes: nothing is emailed while seeding and the weekly summary email is off on live.

**Option A: demo logins on the live server** (simplest). Set `DEMO_ACCOUNTS=true` and `DEMO_PASSWORD`, then run the
seeder once (or `bash deploy.sh --seed-demo`). The data stays as clients leave it and ages with time.

**Option B (recommended for client demos): a separate demo server**, e.g. `demo.example.com`: the same code and steps
1–11 with its own database, plus in its `.env`:

```
DEMO_ACCOUNTS=true
DEMO_PASSWORD=<a password you give to clients>
DEMO_INSTANCE=true
```

Then `php artisan demo:rebuild --fresh --force` once (empties the database and seeds everything).

**Private copy per visitor (demo installation).** Everyone signs in with the same public demo login, which the sign-in
page fills in (owner, with one-click accountant / cashier). Each browser (anonymous cookie id) is handed its **own copy**
of the three demo businesses, so visitors never see each other's changes and the seeded original is never touched:

- `demo:pool` (every minute) keeps `DEMO_POOL_SIZE` copies (default 3) built ahead, so sign-in is instant; if the pool
  is empty a copy is built during sign-in (a few seconds).
- `demo:cleanup` (every 2 hours) deletes every copy idle for `DEMO_IDLE_MINUTES` (default 120), with everything in it
  (every table with a `business_id`, its people, sessions). `demo:rebuild` resets all demo data every night at
  `DEMO_REBUILD_AT` (default 00:00) in `DEMO_TIMEZONE` (default Asia/Karachi); the sign-in page says so.
- Every demo visitor is logged for good (`demo_sessions`: browser id, browser / OS / device, IP, pages, actions;
  `demo_activities`: each save / change / delete, refused ones marked), also after their copy is deleted.
- The same browser signing in again within that time gets its own copy back.
- Demo logins can add, edit and delete business data but never change people, roles, modules, billing, business /
  email / numbering settings, exports or their login (`App\Http\Middleware\DemoGuard`).
- Super admin → **Visits** shows the demo sessions running now, ready copies and demo sign-ins per day.

Every night (`DEMO_REBUILD_AT`, via the scheduler) `demo:rebuild` deletes every private copy and the
seeded demo and seeds the demo again with today's dates (super admins and the visit / sign-in logs stay), so every client sees fresh, recent data and nothing they
tried yesterday. `demo:rebuild` refuses to run anywhere without `DEMO_INSTANCE=true`. Real customers never sign up on
the demo server (it is wiped nightly): link "Get started" visitors to the live server.

## Performance notes (13f)

- Composite indexes for reports and stock (`2026_11_11_100000_add_report_and_stock_indexes`): invoices and returns by
  business + date, sold lines by business + product, lots by product + expiry + remaining (FEFO), purchases by
  business + expiry.
- `php artisan optimize` caches config, routes, views and events. Run it after every update (it is in step 10).
- OPcache is on by default in php8.3-fpm; set `opcache.validate_timestamps=0` in production and restart FPM after updates.
