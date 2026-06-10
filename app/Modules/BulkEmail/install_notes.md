# Bulk Campaign Manager - Installation & Maintenance Notes

## 1. Background Processing (CRITICAL)
For automated scheduling and background sending to work, you must enable the Laravel Scheduler on your system.

### Option A: Local Development (Manual)
Run this command in a separate terminal window and keep it open:
```bash
php artisan schedule:work
```

### Option B: Production (Automated)
Run this command once to add it to your system's crontab:
```bash
(crontab -l ; echo "* * * * * cd /home/seebi/projects/testpharmacyclone/pharmacy && php artisan schedule:run >> /dev/null 2>&1") | crontab -
```

---

## 2. Dynamic Sender Name
You can now specify a "Sender Name" (From Name) for each campaign. 
- If provided, clients will see that name (e.g., "Your Brand Name").
- If left empty, it will default to the name set in your **SMTP Settings**.

---

## 3. Storage & Images
If signature logos are not appearing, ensure the public storage link is active:
```bash
php artisan storage:link
```

---

## 4. Timezone
The system is currently configured to **Asia/Karachi** timezone. 
To change this in the future, edit `config/app.php` at line 70.
