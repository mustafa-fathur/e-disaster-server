#!/bin/bash

# BMKG Earthquake Sync Setup Script
# This script helps you set up automatic syncing of earthquake data from BMKG

echo "🔄 BMKG Earthquake Sync Setup"
echo "=============================="
echo ""

# Check if Laravel is properly configured
if [ ! -f "artisan" ]; then
    echo "❌ Error: This script must be run from the Laravel project root directory"
    exit 1
fi

echo "✅ Laravel project detected"
echo ""

# Get PHP path
PHP_PATH=$(which php)
if [ -z "$PHP_PATH" ]; then
    PHP_PATH="php"
    echo "⚠️  Warning: Could not find PHP path. Using 'php' command."
else
    echo "✅ PHP found at: $PHP_PATH"
fi

# Get project path
PROJECT_PATH=$(pwd)
echo "✅ Project path: $PROJECT_PATH"
echo ""

# Scheduler-driven: no custom command required
echo "🧪 Checking Laravel scheduler configuration..."
php artisan schedule:list || { echo "❌ Failed to list scheduler. Check your setup."; exit 1; }

echo ""
echo "📅 Setting up scheduled sync..."
echo ""

# Create cron job entry with full paths
CRON_ENTRY="* * * * * cd $PROJECT_PATH && $PHP_PATH artisan schedule:run >> $PROJECT_PATH/storage/logs/scheduler.log 2>&1"

# Check if cron job already exists
if crontab -l 2>/dev/null | grep -q "schedule:run"; then
    echo "⚠️  Cron job already exists:"
    crontab -l | grep schedule:run
    echo ""
    read -p "Do you want to replace it? (y/N): " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        # Remove existing schedule:run entries
        crontab -l 2>/dev/null | grep -v "schedule:run" | crontab -
        # Add new entry
        (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
        echo "✅ Cron job updated successfully!"
    else
        echo "ℹ️  Keeping existing cron job."
    fi
else
    echo "📝 Adding cron job..."
    (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
    echo "✅ Cron job added successfully!"
fi

echo ""
echo "📋 Current crontab entries:"
crontab -l | grep -E "(schedule:run|^#)" || echo "  (none found)"
echo ""

# Show current schedule
echo "📋 Laravel scheduled tasks:"
php artisan schedule:list

echo ""
echo "🎯 Scheduled Sync Configuration:"
echo "  • Latest earthquakes: Every minute"
echo ""
echo "📊 To monitor sync activity:"
echo "  tail -f storage/logs/laravel.log | grep BMKG"
echo "  tail -f storage/logs/scheduler.log"
echo ""
echo "🧪 Testing scheduler now..."
php artisan schedule:run -v

echo ""
echo "✅ Setup complete! Your BMKG earthquake sync is now configured."
echo ""
echo "⚠️  IMPORTANT: If this is a production server, verify the cron job is running:"
echo "   1. Wait 1-2 minutes"
echo "   2. Check logs: tail -f storage/logs/laravel.log | grep BMKG"
echo "   3. Verify cron is active: sudo systemctl status cron (Ubuntu/Debian)"
echo ""
echo "📖 For troubleshooting, see: BMKG_SCHEDULER_SETUP.md"
