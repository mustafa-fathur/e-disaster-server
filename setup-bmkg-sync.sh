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

# Scheduler-driven: no custom command required
echo "🧪 Checking Laravel scheduler configuration..."
php artisan schedule:list || { echo "❌ Failed to list scheduler. Check your setup."; exit 1; }

echo "\n📅 Setting up scheduled sync...\n"

# Create cron job entry
CRON_ENTRY="* * * * * cd $(pwd) && php artisan schedule:run >> /dev/null 2>&1"

echo "Add this line to your crontab to enable scheduled syncing:"
echo ""
echo "  $CRON_ENTRY"
echo ""
echo "To add it automatically, run:"
echo "  (crontab -l 2>/dev/null; echo \"$CRON_ENTRY\") | crontab -"
echo ""

# Show current schedule
echo "📋 Current scheduled tasks:"
php artisan schedule:list

echo ""
echo "🎯 Scheduled Sync Configuration:"
echo "  • Latest earthquakes: Every minute"
echo ""
echo "📊 To monitor sync activity, check Laravel logs:"
echo "  tail -f storage/logs/laravel.log | grep BMKG"
echo ""
echo "🛠️  Run scheduler once now (if due):"
echo "  php artisan schedule:run"
echo ""
echo "✅ Setup complete! Your BMKG earthquake sync is now configured."
