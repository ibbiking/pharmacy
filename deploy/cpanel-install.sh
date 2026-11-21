#!/usr/bin/env bash
# Install / update Suite on the GoDaddy cPanel host. Usage: bash suite-install.sh live|demo  (super admin password on stdin)
set -euo pipefail
ENVN="$1"; read -r ADMIN_PW
case "$ENVN" in live) DOM=suite.alhamaad.com ;; demo) DOM=demo.suite.alhamaad.com ;; *) echo "live|demo"; exit 1 ;; esac
APP="$HOME/$DOM"; PHP=/usr/local/bin/php
mkdir -p "$APP"
tar xzf "$HOME/suite-release.tar.gz" -C "$APP"
cd "$APP"
composer install --no-dev --optimize-autoloader --no-interaction -q 2>&1 | grep -v "^$" | tail -3 || true

setenv() { if grep -q "^$1=" .env; then sed -i "s|^$1=.*|$1=$2|" .env; else printf '%s=%s\n' "$1" "$2" >> .env; fi; }
FIRST=0
if [ ! -f .env ]; then
  FIRST=1
  cp .env.example .env; chmod 600 .env
  . "$HOME/.suite-secrets/db_$ENVN"
  setenv APP_NAME Suite; setenv APP_BRAND Suite; setenv APP_ENV production; setenv APP_DEBUG false
  setenv APP_URL "https://$DOM"; setenv LOG_CHANNEL daily; setenv LOG_LEVEL warning
  setenv DB_CONNECTION mysql; setenv DB_HOST localhost; setenv DB_PORT 3306
  setenv DB_DATABASE "$DB_DATABASE"; setenv DB_USERNAME "$DB_USERNAME"; setenv DB_PASSWORD "$DB_PASSWORD"
  setenv CACHE_DRIVER file; setenv CACHE_STORE file; setenv SESSION_DRIVER file; setenv QUEUE_CONNECTION database
  setenv SESSION_SECURE_COOKIE true; setenv FILESYSTEM_DRIVER local
  setenv MAIL_MAILER sendmail; setenv MAIL_FROM_ADDRESS "noreply@$DOM"; setenv MAIL_FROM_NAME Suite
  setenv DEBUGBAR_ENABLED false
  if [ "$ENVN" = demo ]; then
    setenv DEMO_ACCOUNTS true; setenv DEMO_INSTANCE true; setenv DEMO_PASSWORD InvoraDemo2026
  else
    setenv DEMO_ACCOUNTS false; setenv DEMO_INSTANCE false
  fi
  $PHP artisan key:generate --force -q
fi
printf 'memory_limit = 256M\nupload_max_filesize = 32M\npost_max_size = 32M\nmax_execution_time = 120\n' > public/.user.ini

$PHP artisan config:clear -q
if [ "$ENVN" = demo ]; then
  if [ "$FIRST" = 1 ]; then $PHP artisan demo:rebuild --fresh --force 2>&1 | grep -E "seeded|rebuilt|ERROR|rror" | head -10; else $PHP artisan migrate --force -q; fi
else
  $PHP artisan migrate --force -q
  if [ "$FIRST" = 1 ]; then $PHP artisan db:seed --force -q 2>/dev/null || $PHP artisan db:seed --force 2>&1 | tail -3; fi
fi
# Empty password = keep the current one (the command would refuse it and stop the install half-way).
if [ -n "$ADMIN_PW" ]; then $PHP artisan app:super-admin ibrahimazhar86@gmail.com --name="Ebrahim Azhar" --password="$ADMIN_PW" 2>&1 | tail -1; fi
$PHP artisan storage:link -q 2>/dev/null || true
$PHP artisan optimize -q
$PHP artisan permission:cache-reset -q
if [ "$ENVN" = demo ]; then $PHP artisan demo:pool 2>&1 | tail -1; fi
echo "$ENVN installed in $APP"
