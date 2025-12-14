<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule BMKG latest-only sync task (no custom Artisan command needed)
Schedule::call(function () {
    try {
        $result = (new \App\Services\BmkgSyncService())->syncLatestEarthquake();
        if (is_array($result) && ($result['success'] ?? false)) {
            \Log::info('BMKG latest sync ran', [
                'created' => $result['created'] ?? null,
                'message' => $result['message'] ?? null,
            ]);
        } else {
            \Log::warning('BMKG latest sync failed', [
                'message' => $result['message'] ?? 'Unknown error',
            ]);
        }
    } catch (\Throwable $e) {
        \Log::error('BMKG latest sync exception', [
            'error' => $e->getMessage(),
        ]);
    }
})
    ->everyMinute()
    ->name('bmkg-sync-latest')
    ->withoutOverlapping();
