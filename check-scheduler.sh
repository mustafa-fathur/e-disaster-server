#!/bin/bash

# BMKG Scheduler Diagnostic Script
# Run this on your server to check if the scheduler is properly configured

echo "🔍 BMKG Scheduler Diagnostic"
echo "============================"
echo ""

# Check if running from Laravel root
if [ ! -f "artisan" ]; then
    echo "❌ Error: This script must be run from the Laravel project root directory"
    exit 1
fi

PROJECT_PATH=$(pwd)
echo "📁 Project path: $PROJECT_PATH"
echo ""

# Check PHP
echo "🐘 PHP Check:"
PHP_PATH=$(which php)
if [ -z "$PHP_PATH" ]; then
    echo "  ❌ PHP not found in PATH"
    echo "  💡 Try: which php or locate php"
else
    echo "  ✅ PHP found: $PHP_PATH"
    php -v | head -1
fi
echo ""

# Check Laravel scheduler
echo "📅 Laravel Scheduler Check:"
if php artisan schedule:list > /dev/null 2>&1; then
    echo "  ✅ Scheduler is accessible"
    echo ""
    echo "  Scheduled tasks:"
    php artisan schedule:list
else
    echo "  ❌ Cannot access scheduler"
    echo "  Error: $(php artisan schedule:list 2>&1)"
fi
echo ""

# Check cron job
echo "⏰ Cron Job Check:"
if crontab -l 2>/dev/null | grep -q "schedule:run"; then
    echo "  ✅ Cron job found:"
    crontab -l | grep schedule:run | sed 's/^/    /'
    
    # Check if cron service is running
    echo ""
    echo "  Cron service status:"
    if systemctl is-active --quiet cron 2>/dev/null || systemctl is-active --quiet crond 2>/dev/null; then
        echo "    ✅ Cron service is running"
    else
        echo "    ⚠️  Cron service status unknown (may not have systemctl)"
        echo "    💡 Check manually: sudo systemctl status cron (Ubuntu) or sudo systemctl status crond (CentOS)"
    fi
else
    echo "  ❌ No cron job found for Laravel scheduler"
    echo ""
    echo "  💡 To add cron job, run:"
    echo "     ./setup-bmkg-sync.sh"
    echo "  Or manually:"
    echo "     crontab -e"
    echo "     Add: * * * * * cd $PROJECT_PATH && $(which php) artisan schedule:run >> $PROJECT_PATH/storage/logs/scheduler.log 2>&1"
fi
echo ""

# Check logs
echo "📊 Log Check:"
if [ -f "storage/logs/laravel.log" ]; then
    echo "  ✅ Laravel log exists"
    BMKG_LOGS=$(grep -c "BMKG" storage/logs/laravel.log 2>/dev/null || echo "0")
    echo "  📝 BMKG-related log entries: $BMKG_LOGS"
    
    if [ "$BMKG_LOGS" -gt 0 ]; then
        echo ""
        echo "  Recent BMKG logs (last 5):"
        grep "BMKG" storage/logs/laravel.log | tail -5 | sed 's/^/    /'
    fi
else
    echo "  ⚠️  Laravel log not found"
fi

if [ -f "storage/logs/scheduler.log" ]; then
    echo "  ✅ Scheduler log exists"
    SCHEDULER_LOGS=$(wc -l < storage/logs/scheduler.log 2>/dev/null || echo "0")
    echo "  📝 Scheduler log entries: $SCHEDULER_LOGS"
    
    if [ "$SCHEDULER_LOGS" -gt 0 ]; then
        echo ""
        echo "  Recent scheduler logs (last 5):"
        tail -5 storage/logs/scheduler.log | sed 's/^/    /'
    fi
else
    echo "  ℹ️  Scheduler log not found (will be created when cron runs)"
fi
echo ""

# Test scheduler manually
echo "🧪 Manual Scheduler Test:"
echo "  Running: php artisan schedule:run -v"
php artisan schedule:run -v 2>&1 | sed 's/^/    /'
echo ""

# Check permissions
echo "🔐 Permissions Check:"
if [ -w "storage/logs" ]; then
    echo "  ✅ storage/logs is writable"
else
    echo "  ❌ storage/logs is not writable"
    echo "  💡 Fix: chmod -R 775 storage && chown -R www-data:www-data storage"
fi

if [ -x "artisan" ]; then
    echo "  ✅ artisan is executable"
else
    echo "  ⚠️  artisan is not executable (may still work)"
    echo "  💡 Fix: chmod +x artisan"
fi
echo ""

# Summary
echo "📋 Summary:"
CRON_EXISTS=$(crontab -l 2>/dev/null | grep -c "schedule:run" || echo "0")
if [ "$CRON_EXISTS" -gt 0 ]; then
    echo "  ✅ Cron job is configured"
    echo "  ⏳ Wait 1-2 minutes and check logs to verify it's running automatically"
    echo "  📖 Monitor: tail -f storage/logs/laravel.log | grep BMKG"
else
    echo "  ❌ Cron job is NOT configured"
    echo "  💡 Run: ./setup-bmkg-sync.sh to set it up"
fi
echo ""
echo "✅ Diagnostic complete!"

