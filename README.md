# pharmacy

## Install Notes

### 1) Initial setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
```

### 2) Run application

```bash
php artisan serve
```

## Bulk Generic Import Scheduler

Bulk generic product imports (when selecting multiple products) are queued and processed by a scheduled command every 15 minutes.

### Command used by scheduler

```bash
php artisan generic-products:process-bulk-imports
```

### Manual test command

```bash
php artisan generic-products:process-bulk-imports
```

Expected output format:

```text
Processed X batch | Imported: Y | Skipped: Z
```

### Verify scheduler registration

```bash
php artisan schedule:list
```

You should see `generic-products:process-bulk-imports` with interval `*/15 * * * *`.

## Server Cron Job Setup

Laravel scheduler must be triggered every minute by system cron. Add this once on the server.

### Step 1: Open crontab

```bash
crontab -e
```

### Step 2: Add this line

Replace `/path/to/your/project` with your actual project directory.

```cron
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

This runs every minute, and Laravel executes due tasks automatically (including the 15-minute bulk import job).

### Optional log-enabled cron line

```cron
* * * * * cd /path/to/your/project && php artisan schedule:run >> storage/logs/scheduler.log 2>&1
```

## Production Checklist

- Ensure writable permissions on runtime directories:
  - `storage/`
  - `bootstrap/cache/`
- Run migrations:
  - `php artisan migrate --force`
- Clear and rebuild config/routes/views cache after deployment:
  - `php artisan optimize:clear`
  - `php artisan config:cache`
  - `php artisan route:cache`
  - `php artisan view:cache`
- Verify scheduler is registered:
  - `php artisan schedule:list`
- Ensure system cron is installed (every minute):
  - `* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1`
- If queue jobs are introduced later (for mail/heavy jobs), start a worker:
  - `php artisan queue:work --tries=3`
  - keep it alive with Supervisor/systemd in production
