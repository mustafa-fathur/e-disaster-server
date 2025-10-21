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

# Test the sync command
echo "🧪 Testing BMKG sync command..."
php artisan bmkg:sync-earthquakes --type=latest --schedule

if [ $? -eq 0 ]; then
    echo "✅ BMKG sync command working correctly"
else
    echo "❌ BMKG sync command failed. Please check your configuration."
    exit 1
fi

echo ""
echo "📅 Setting up scheduled sync..."
echo ""

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
echo "  • Recent earthquakes (M 5.0+): Every 5 minutes"
echo "  • Felt earthquakes: Every 10 minutes"
echo ""
echo "📊 To monitor sync activity, check Laravel logs:"
echo "  tail -f storage/logs/laravel.log | grep BMKG"
echo ""
echo "🛠️  Manual sync commands:"
echo "  php artisan bmkg:sync-earthquakes --type=latest"
echo "  php artisan bmkg:sync-earthquakes --type=recent"
echo "  php artisan bmkg:sync-earthquakes --type=felt"
echo "  php artisan bmkg:sync-earthquakes --type=all"
echo ""
echo "✅ Setup complete! Your BMKG earthquake sync is now configured."
