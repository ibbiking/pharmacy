#!/usr/bin/env bash
#
# One-shot local setup for the Pharmacy app (Laravel 13 / PHP 8.3) on Ubuntu or Debian.
#
#   bash setup.sh
#
# Safe to run again at any time (e.g. after every `git pull`): anything already installed is
# skipped, an existing database is backed up before migrating, and seed data is only loaded
# into an empty database. It never changes the system's default `php` binary.

set -euo pipefail

PHP_VERSION="8.3"
PHP="php${PHP_VERSION}"
DEFAULT_DB_NAME="pharmacy_laravel13"
TEST_DB="pharmacy_testing"   # used only by `php artisan test` (wiped on every run)
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="$(dirname "$APP_DIR")/db_backups"

cd "$APP_DIR"

say()  { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m[!] %s\033[0m\n' "$*"; }
die()  { printf '\033[1;31m[x] %s\033[0m\n' "$*" >&2; exit 1; }

if [ "$(id -u)" -eq 0 ]; then SUDO=""; else SUDO="sudo"; fi

# ---------------------------------------------------------------------------
# 0. Supported OS check
# ---------------------------------------------------------------------------
[ -f artisan ] || die "Run this script from inside the project folder."
command -v apt-get >/dev/null 2>&1 || die "This script supports Ubuntu/Debian (apt) only."
. /etc/os-release
OS_ID="${ID:-}"; OS_LIKE="${ID_LIKE:-}"

export DEBIAN_FRONTEND=noninteractive

# ---------------------------------------------------------------------------
# 1. System packages: PHP 8.3 + extensions, MySQL, tools
# ---------------------------------------------------------------------------
say "Installing base tools"
$SUDO apt-get update -y
$SUDO apt-get install -y ca-certificates curl git unzip lsb-release gnupg software-properties-common openssl

if ! apt-cache policy "${PHP}-cli" 2>/dev/null | grep -q 'Candidate: [0-9]'; then
    say "Adding a package source for PHP ${PHP_VERSION}"
    if [ "$OS_ID" = "ubuntu" ] || [[ "$OS_LIKE" == *ubuntu* ]]; then
        $SUDO add-apt-repository -y ppa:ondrej/php
    else
        $SUDO curl -fsSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
        echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
            | $SUDO tee /etc/apt/sources.list.d/php-sury.list >/dev/null
    fi
    $SUDO apt-get update -y
fi

say "Installing PHP ${PHP_VERSION} and required extensions"
$SUDO apt-get install -y \
    "${PHP}-cli" "${PHP}-mysql" "${PHP}-mbstring" "${PHP}-xml" "${PHP}-curl" \
    "${PHP}-zip" "${PHP}-gd" "${PHP}-intl" "${PHP}-bcmath"

if ! command -v mysqld >/dev/null 2>&1 && ! command -v mariadbd >/dev/null 2>&1; then
    say "Installing MySQL server"
    if apt-cache policy mysql-server 2>/dev/null | grep -q 'Candidate: [0-9]'; then
        $SUDO apt-get install -y mysql-server
    else
        $SUDO apt-get install -y mariadb-server
    fi
fi
command -v mysql >/dev/null 2>&1 || $SUDO apt-get install -y default-mysql-client || $SUDO apt-get install -y mysql-client

say "Starting the database service"
for svc in mysql mariadb; do
    if command -v systemctl >/dev/null 2>&1 && [ -d /run/systemd/system ]; then
        $SUDO systemctl enable --now "$svc" >/dev/null 2>&1 && break || true
    else
        $SUDO service "$svc" start >/dev/null 2>&1 && break || true
    fi
done

# ---------------------------------------------------------------------------
# 1b. Node.js (frontend build: Vite needs Node ^20.19 or >= 22.12)
# ---------------------------------------------------------------------------
node_ok() {
    command -v node >/dev/null 2>&1 || return 1
    node -e 'const [a,b]=process.versions.node.split(".").map(Number); process.exit((a===20&&b>=19)||(a===22&&b>=12)||a>22?0:1)'
}
if ! node_ok; then
    say "Installing Node.js 22"
    if [ -n "$SUDO" ]; then
        curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
    else
        curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    fi
    $SUDO apt-get install -y nodejs
fi

# ---------------------------------------------------------------------------
# 2. Composer
# ---------------------------------------------------------------------------
if ! command -v composer >/dev/null 2>&1; then
    say "Installing Composer"
    EXPECTED="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    ACTUAL="$($PHP -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    [ "$EXPECTED" = "$ACTUAL" ] || die "Composer installer checksum mismatch."
    $SUDO "$PHP" /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi
COMPOSER="$(command -v composer)"

# ---------------------------------------------------------------------------
# 3. .env
# ---------------------------------------------------------------------------
env_get() { grep -E "^$1=" .env 2>/dev/null | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
env_set() {
    if grep -qE "^$1=" .env; then
        sed -i "s|^$1=.*|$1=$2|" .env
    else
        printf '%s=%s\n' "$1" "$2" >> .env
    fi
}

if [ ! -f .env ]; then
    say "Creating .env from .env.example"
    cp .env.example .env
fi

# ---------------------------------------------------------------------------
# 4. Database: reuse working credentials from .env, otherwise create a local user
# ---------------------------------------------------------------------------
DB_HOST="$(env_get DB_HOST)";         DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="$(env_get DB_PORT)";         DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="$(env_get DB_DATABASE)"; DB_DATABASE="${DB_DATABASE:-$DEFAULT_DB_NAME}"
[ "$DB_DATABASE" = "laravel" ] && DB_DATABASE="$DEFAULT_DB_NAME"
DB_USERNAME="$(env_get DB_USERNAME)"
DB_PASSWORD="$(env_get DB_PASSWORD)"

db_ok() { MYSQL_PWD="$DB_PASSWORD" mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -e "SELECT 1" >/dev/null 2>&1; }

if [ -z "$DB_USERNAME" ] || ! db_ok; then
    say "Creating a local MySQL user for the app"
    DB_USERNAME="pharmacy"
    DB_PASSWORD="$(openssl rand -hex 16)"
    SQL="CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
         CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
         ALTER USER '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
         ALTER USER '${DB_USERNAME}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
         GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'localhost';
         GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'127.0.0.1';
         GRANT ALL PRIVILEGES ON \`${TEST_DB}\`.* TO '${DB_USERNAME}'@'localhost';
         GRANT ALL PRIVILEGES ON \`${TEST_DB}\`.* TO '${DB_USERNAME}'@'127.0.0.1';
         FLUSH PRIVILEGES;"
    if ! $SUDO mysql -e "$SQL" 2>/dev/null; then
        warn "Could not log in to MySQL as the system root user."
        read -r -s -p "Enter your MySQL root password: " ROOT_PW; echo
        MYSQL_PWD="$ROOT_PW" mysql -h"$DB_HOST" -P"$DB_PORT" -uroot -e "$SQL" \
            || die "Could not create the MySQL user. Check the root password and try again."
    fi
    env_set DB_CONNECTION mysql
    env_set DB_HOST "$DB_HOST"
    env_set DB_PORT "$DB_PORT"
    env_set DB_USERNAME "$DB_USERNAME"
    env_set DB_PASSWORD "$DB_PASSWORD"
    db_ok || die "Still cannot connect to MySQL with the new user."
fi
env_set DB_DATABASE "$DB_DATABASE"

MYSQL_PWD="$DB_PASSWORD" mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" \
    -e "CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
MYSQL_PWD="$DB_PASSWORD" mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" \
    -e "CREATE DATABASE IF NOT EXISTS \`${TEST_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" \
    || warn "Could not create the test database ${TEST_DB} (only needed to run automated tests)."


TABLES="$(MYSQL_PWD="$DB_PASSWORD" mysql -N -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_DATABASE}'")"

if [ "$TABLES" -gt 0 ]; then
    say "Backing up the existing database before updating it"
    mkdir -p "$BACKUP_DIR"
    OUT="$BACKUP_DIR/${DB_DATABASE}_$(date +%Y%m%d_%H%M%S).sql.gz"
    MYSQL_PWD="$DB_PASSWORD" mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" \
        --single-transaction --no-tablespaces "$DB_DATABASE" | gzip > "$OUT"
    echo "    Backup saved to: $OUT"
fi

# ---------------------------------------------------------------------------
# 5. PHP dependencies, app key, migrations, seed, caches
# ---------------------------------------------------------------------------
say "Installing PHP dependencies (this can take a few minutes)"
"$PHP" "$COMPOSER" install --no-interaction --prefer-dist

say "Building the frontend (this can take a minute)"
npm ci --no-fund --no-audit
npm run build

if [ -z "$(env_get APP_KEY)" ]; then
    "$PHP" artisan key:generate --force
fi

say "Updating the database structure"
"$PHP" artisan migrate --force

USERS="$(MYSQL_PWD="$DB_PASSWORD" mysql -N -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" "$DB_DATABASE" \
    -e "SELECT COUNT(*) FROM users" 2>/dev/null || echo 0)"
if [ "$USERS" -eq 0 ]; then
    say "Empty database - loading starter data"
    "$PHP" artisan db:seed --force
fi

say "Finishing up"
"$PHP" artisan storage:link >/dev/null 2>&1 || true
"$PHP" artisan optimize:clear
"$PHP" artisan permission:cache-reset

say "Done. Start the app with:"
echo "    bash start.sh"
echo "Then open: http://127.0.0.1:8080/admin/login"
