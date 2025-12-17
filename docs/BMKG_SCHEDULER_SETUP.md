# BMKG Scheduler Setup Guide

## Problem

The BMKG sync job is defined in `routes/console.php` but won't run automatically unless you set up a cron job on your server to execute `php artisan schedule:run` every minute.

## Solution: Set Up Cron Job

### Step 1: SSH into Your Server

```bash
ssh user@your-server.com
# or
ssh root@e-disaster.fathur.tech
```

### Step 2: Navigate to Your Laravel Project

```bash
cd /path/to/your/laravel/project
# Example: cd /var/www/e-disaster
```

### Step 3: Check Current Cron Jobs

```bash
crontab -l
```

### Step 4: Add Laravel Scheduler Cron Job

**Option A: Add Manually (Recommended)**

```bash
# Edit crontab
crontab -e

# Add this line (replace /path/to/laravel with your actual path):
* * * * * cd /var/www/e-disaster && php artisan schedule:run >> /dev/null 2>&1

# Save and exit (Ctrl+X, then Y, then Enter for nano)
```

**Option B: Add Automatically**

```bash
# Replace /var/www/e-disaster with your actual project path
(crontab -l 2>/dev/null; echo "* * * * * cd /var/www/e-disaster && php artisan schedule:run >> /dev/null 2>&1") | crontab -
```

### Step 5: Verify Cron Job is Added

```bash
crontab -l
```

You should see:

```
* * * * * cd /var/www/e-disaster && php artisan schedule:run >> /dev/null 2>&1
```

### Step 6: Test the Scheduler

```bash
# Run manually to test
cd /var/www/e-disaster
php artisan schedule:run

# Check if it's working
php artisan schedule:list
```

### Step 7: Monitor Logs

```bash
# Watch the logs in real-time
tail -f storage/logs/laravel.log | grep BMKG

# Or check all logs
tail -f storage/logs/laravel.log
```

---

## Troubleshooting

### Issue: Cron job not running

**Check 1: Verify cron service is running**

```bash
# On Ubuntu/Debian
sudo systemctl status cron

# On CentOS/RHEL
sudo systemctl status crond
```

**Check 2: Verify cron has correct permissions**

```bash
# Make sure the user running cron has access to PHP and the project
which php
php -v
```

**Check 3: Check cron logs**

```bash
# On Ubuntu/Debian
sudo tail -f /var/log/syslog | grep CRON

# On CentOS/RHEL
sudo tail -f /var/log/cron
```

**Check 4: Test with explicit paths**

```bash
# Edit crontab to use full paths
crontab -e

# Use full paths:
* * * * * /usr/bin/php /var/www/e-disaster/artisan schedule:run >> /var/www/e-disaster/storage/logs/scheduler.log 2>&1
```

### Issue: Permission errors

```bash
# Make sure storage directory is writable
chmod -R 775 storage
chown -R www-data:www-data storage

# Make sure artisan is executable
chmod +x artisan
```

### Issue: PHP not found in cron

```bash
# Find PHP path
which php
# Output: /usr/bin/php or /usr/local/bin/php

# Use full path in crontab
* * * * * /usr/bin/php /var/www/e-disaster/artisan schedule:run >> /dev/null 2>&1
```

### Issue: Environment variables not loaded

If your cron job can't access environment variables, use this format:

```bash
crontab -e

# Add environment variables and scheduler
* * * * * cd /var/www/e-disaster && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Or create a wrapper script:

```bash
# Create wrapper script
nano /var/www/e-disaster/schedule-run.sh
```

```bash
#!/bin/bash
cd /var/www/e-disaster
source .env 2>/dev/null || true
/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

```bash
# Make executable
chmod +x /var/www/e-disaster/schedule-run.sh

# Add to crontab
* * * * * /var/www/e-disaster/schedule-run.sh
```

---

## Alternative: Using Systemd Timer (For Systemd-based Systems)

If cron doesn't work, you can use systemd:

### Step 1: Create Service File

```bash
sudo nano /etc/systemd/system/laravel-scheduler.service
```

```ini
[Unit]
Description=Laravel Scheduler
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/e-disaster
ExecStart=/usr/bin/php /var/www/e-disaster/artisan schedule:run
Restart=always

[Install]
WantedBy=multi-user.target
```

### Step 2: Create Timer File

```bash
sudo nano /etc/systemd/system/laravel-scheduler.timer
```

```ini
[Unit]
Description=Run Laravel Scheduler Every Minute
Requires=laravel-scheduler.service

[Timer]
OnCalendar=*:0/1
AccuracySec=1s

[Install]
WantedBy=timers.target
```

### Step 3: Enable and Start

```bash
sudo systemctl daemon-reload
sudo systemctl enable laravel-scheduler.timer
sudo systemctl start laravel-scheduler.timer
sudo systemctl status laravel-scheduler.timer
```

---

## Verification Commands

```bash
# Check if cron job exists
crontab -l | grep schedule

# Check Laravel scheduler status
php artisan schedule:list

# Test scheduler manually
php artisan schedule:run -v

# Check recent logs
tail -n 50 storage/logs/laravel.log | grep BMKG

# Check if scheduler is actually running (wait 1-2 minutes after setup)
grep "BMKG latest sync ran" storage/logs/laravel.log | tail -5
```

---

## Production Best Practices

1. **Log Output**: Consider logging to a file instead of `/dev/null`:

    ```bash
    * * * * * cd /var/www/e-disaster && php artisan schedule:run >> /var/www/e-disaster/storage/logs/scheduler.log 2>&1
    ```

2. **Error Notifications**: Set up email alerts for scheduler failures (if needed)

3. **Monitoring**: Use monitoring tools to ensure the scheduler is running

4. **Backup**: Document your cron setup for disaster recovery

---

## Quick Setup Script

Run this on your server (replace `/var/www/e-disaster` with your path):

```bash
#!/bin/bash
PROJECT_PATH="/var/www/e-disaster"
CRON_ENTRY="* * * * * cd $PROJECT_PATH && /usr/bin/php artisan schedule:run >> $PROJECT_PATH/storage/logs/scheduler.log 2>&1"

# Check if already exists
if crontab -l 2>/dev/null | grep -q "schedule:run"; then
    echo "✅ Cron job already exists"
    crontab -l | grep schedule
else
    # Add cron job
    (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
    echo "✅ Cron job added successfully"
    crontab -l | grep schedule
fi

# Test
echo "🧪 Testing scheduler..."
cd $PROJECT_PATH
php artisan schedule:run -v
```

Save as `setup-scheduler.sh`, make executable (`chmod +x setup-scheduler.sh`), and run it.
