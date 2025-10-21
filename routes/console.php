<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Register BMKG sync command
Artisan::command('bmkg:sync-earthquakes {--type=all} {--schedule}', function () {
    $type = $this->option('type');
    $isScheduled = $this->option('schedule');
    
    if (!$isScheduled) {
        $this->info("🔄 Starting BMKG earthquake sync...");
        $this->info("📡 Type: {$type}");
        $this->newLine();
    }

    $syncService = new \App\Services\BmkgSyncService();
    
    try {
        switch ($type) {
            case 'latest':
                $result = $syncService->syncLatestEarthquake();
                break;
            case 'recent':
                $result = $syncService->syncRecentEarthquakes();
                break;
            case 'felt':
                $result = $syncService->syncFeltEarthquakes();
                break;
            case 'all':
            default:
                $result = $syncService->syncAllEarthquakes();
                break;
        }

        if ($result['success']) {
            if (!$isScheduled) {
                $this->info("✅ " . $result['message']);
                
                if (isset($result['stats'])) {
                    $this->table(
                        ['Metric', 'Count'],
                        [
                            ['Created', $result['stats']['created']],
                            ['Skipped', $result['stats']['skipped']],
                            ['Total Processed', $result['stats']['total_processed']]
                        ]
                    );
                }
                
                if (isset($result['summary'])) {
                    $this->newLine();
                    $this->info("📊 Summary:");
                    $this->table(
                        ['Metric', 'Count'],
                        [
                            ['Total Created', $result['summary']['total_created']],
                            ['Total Skipped', $result['summary']['total_skipped']],
                            ['Sync Types', implode(', ', $result['summary']['sync_types'])]
                        ]
                    );
                }
            } else {
                // Scheduled mode - just log the result
                \Log::info("BMKG Sync Completed: " . $result['message']);
            }
            
            return 0;
        } else {
            if (!$isScheduled) {
                $this->error("❌ " . $result['message']);
            } else {
                \Log::error("BMKG Sync Failed: " . $result['message']);
            }
            
            return 1;
        }

    } catch (\Exception $e) {
        $errorMessage = "Failed to sync BMKG earthquake data: " . $e->getMessage();
        
        if (!$isScheduled) {
            $this->error("❌ " . $errorMessage);
        } else {
            \Log::error($errorMessage);
        }
        
        return 1;
    }
})->purpose('Sync earthquake data from BMKG API');

// Schedule BMKG earthquake sync tasks
Schedule::command('bmkg:sync-earthquakes --type=latest --schedule')
    ->everyMinute()
    ->name('bmkg-sync-latest')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('bmkg:sync-earthquakes --type=recent --schedule')
    ->everyFiveMinutes()
    ->name('bmkg-sync-recent')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('bmkg:sync-earthquakes --type=felt --schedule')
    ->everyTenMinutes()
    ->name('bmkg-sync-felt')
    ->withoutOverlapping()
    ->runInBackground();
