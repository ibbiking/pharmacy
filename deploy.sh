#!/usr/bin/env bash
#
# Release an update to a live server (docs/DEPLOYMENT.md §10). Run on the server, inside the app folder, as the
# user that owns the code (not root):
#
#   bash deploy.sh                 # update to the latest commit of the branch that is checked out
#   bash deploy.sh main            # switch to / update branch "main"
#   bash deploy.sh main --seed-demo  # also add the demo accounts if they are missing (needs DEMO_ACCOUNTS=true)
#
# Steps: checks → database backup → maintenance mode → pull → install → build → migrate → caches → workers → live.
# If a step fails the site STAYS in maintenance mode (so nobody works on a half-updated app) and the script prints how
# to roll back. Nothing secret is printed.

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups}"
BRANCH="${1:-}"
SEED_DEMO=0
for arg in "$@"; do [ "$arg" = "--seed-demo" ] && SEED_DEMO=1; done
[ "$BRANCH" = "--seed-demo" ] && BRANCH=""

say()  { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }
die()  { printf '\033[1;31m[x] %s\033[0m\n' "$*" >&2; exit 1; }

cd "$APP_DIR"
[ -f artisan ] && [ -f .env ] || die "Run this inside the app folder (artisan and .env must exist)."
[ "$(id -u)" -ne 0 ] || die "Don't run as root: run as the user that owns the code."
env_value() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
[ "$(env_value APP_ENV)" = "production" ] || echo "Note: APP_ENV is '$(env_value APP_ENV)', not production."
[ -z "$(git status --porcelain --untracked-files=no)" ] || die "The server has local code changes. Commit or remove them first (git status)."

PREVIOUS="$(git rev-parse --short HEAD)"
STAMP="$(date +%F_%H%M)"
WENT_DOWN=0
on_error() {
    printf '\n\033[1;31m[x] The update stopped at line %s. The site is still in maintenance mode.\033[0m\n' "$1" >&2
    printf 'Roll back:\n  git checkout %s && composer install --no-dev --optimize-autoloader && npm ci && npm run build\n' "$PREVIOUS" >&2
    printf '  if migrations ran: gunzip < %s/%s_%s.sql.gz | mysql <database>   (or php artisan migrate:rollback --step=N --force)\n' "$BACKUP_DIR" "$(env_value DB_DATABASE)" "$STAMP" >&2
    printf '  php artisan optimize && php artisan queue:restart && php artisan up\n' >&2
    [ "$WENT_DOWN" = 1 ] || printf '(Maintenance mode was not switched on yet, so the site is still live on the old code.)\n' >&2
}
trap 'on_error $LINENO' ERR

say "Backing up the database"
mkdir -p "$BACKUP_DIR"
DB_NAME="$(env_value DB_DATABASE)"
MYSQL_PWD="$(env_value DB_PASSWORD)" mysqldump --no-tablespaces --single-transaction --routines \
    -h "$(env_value DB_HOST)" -P "$(env_value DB_PORT)" -u "$(env_value DB_USERNAME)" "$DB_NAME" \
    | gzip > "$BACKUP_DIR/${DB_NAME}_${STAMP}.sql.gz"
echo "Saved $BACKUP_DIR/${DB_NAME}_${STAMP}.sql.gz"

say "Fetching the code"
git fetch --prune origin
if [ -n "$BRANCH" ]; then git checkout "$BRANCH"; fi
BRANCH="$(git rev-parse --abbrev-ref HEAD)"
git merge-base --is-ancestor HEAD "origin/$BRANCH" || die "origin/$BRANCH is not ahead of the server (diverged). Fix it by hand."

say "Maintenance mode on"
php artisan down --render="errors::503" --retry=60
WENT_DOWN=1

say "Updating to origin/$BRANCH"
git merge --ff-only "origin/$BRANCH"
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund && npm run build

say "Database"
php artisan migrate --force
if [ "$SEED_DEMO" = 1 ]; then
    php artisan db:seed --class='Database\Seeders\DemoDataSeeder' --force
fi

say "Caches and workers"
php artisan optimize
php artisan permission:cache-reset
php artisan queue:restart

php artisan up
trap - ERR
say "Live on $(git rev-parse --short HEAD) (was $PREVIOUS)"
