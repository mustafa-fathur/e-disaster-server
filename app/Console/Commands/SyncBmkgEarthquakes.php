<?php

namespace App\Console\Commands;

use App\Services\BmkgSyncService;
use Illuminate\Console\Command;

class SyncBmkgEarthquakes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bmkg:sync-earthquakes 
                            {--type=all : Type of earthquake data to sync (latest|recent|felt|all)}
                            {--schedule : Run in scheduled mode (less verbose output)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync earthquake data from BMKG API and store as disasters in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        $isScheduled = $this->option('schedule');
        
        if (!$isScheduled) {
            $this->info("🔄 Starting BMKG earthquake sync...");
            $this->info("📡 Type: {$type}");
            $this->newLine();
        }

        $syncService = new BmkgSyncService();
        
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
                
                return Command::SUCCESS;
            } else {
                if (!$isScheduled) {
                    $this->error("❌ " . $result['message']);
                } else {
                    \Log::error("BMKG Sync Failed: " . $result['message']);
                }
                
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $errorMessage = "Failed to sync BMKG earthquake data: " . $e->getMessage();
            
            if (!$isScheduled) {
                $this->error("❌ " . $errorMessage);
            } else {
                \Log::error($errorMessage);
            }
            
            return Command::FAILURE;
        }
    }
}
