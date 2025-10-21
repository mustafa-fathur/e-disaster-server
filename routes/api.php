<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DisasterController;
use App\Http\Controllers\Api\V1\DisasterReportController;
use App\Http\Controllers\Api\V1\DisasterVictimController;
use App\Http\Controllers\Api\V1\DisasterAidController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PictureController;
use App\Http\Controllers\Api\V1\SystemController;
use App\Http\Controllers\Api\V1\BmkgController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Mobile (Android) REST API routes. These are versioned and return JSON.
| Swap closures with real controllers as features are implemented.
*/

// Version 1
Route::prefix('v1')->group(function () {

    // Healthcheck
    Route::get('/health', [SystemController::class, 'health']);

    // Public Auth Endpoints
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
    });

    // Protected Endpoints (Officer/Volunteer) - General Access
    Route::middleware(['api_access'])->group(function () {
        
        // Auth endpoints
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        
        // Profile Management
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/profile/password', [AuthController::class, 'updatePassword']);
        Route::post('/profile/picture', [AuthController::class, 'updateProfilePicture']);
        Route::delete('/profile/picture', [AuthController::class, 'deleteProfilePicture']);

        // Dashboard
        Route::get('/dashboard', [DisasterController::class, 'dashboard']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'getNotifications']);
        Route::get('/notifications/stats', [NotificationController::class, 'getNotificationStats']);
        Route::put('/notifications/read-all', [NotificationController::class, 'markAllNotificationsRead']);
        Route::delete('/notifications/read-all', [NotificationController::class, 'deleteAllReadNotifications']);
        Route::get('/notifications/{id}', [NotificationController::class, 'getNotification']);
        Route::put('/notifications/{id}/read', [NotificationController::class, 'markNotificationRead']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'deleteNotification']);

        // Picture Management (General Access)
        Route::post('/pictures/{modelType}/{modelId}', [PictureController::class, 'uploadImage']);
        Route::get('/pictures/{modelType}/{modelId}', [PictureController::class, 'getImages']);
        Route::get('/pictures/{modelType}/{modelId}/{imageId}', [PictureController::class, 'getImage']);
        Route::put('/pictures/{modelType}/{modelId}/{imageId}', [PictureController::class, 'updateImage']);
        Route::delete('/pictures/{modelType}/{modelId}/{imageId}', [PictureController::class, 'deleteImage']);

        // Disaster Management (General Access - Read Only)
        Route::get('/disasters', [DisasterController::class, 'index']);
        Route::get('/disasters/{id}', [DisasterController::class, 'show']);
        Route::post('/disasters', [DisasterController::class, 'createDisaster']);

        // Disaster Volunteer Management (General Access)
        Route::get('/disasters/{id}/volunteers', [DisasterController::class, 'getDisasterVolunteers']);
        Route::post('/disasters/{id}/volunteers', [DisasterController::class, 'assignVolunteerToDisaster']);
        Route::delete('/disasters/{id}/volunteers/{volunteerId}', [DisasterController::class, 'removeVolunteerFromDisaster']);

        // BMKG Earthquake Data (General Access)
        Route::prefix('bmkg')->group(function () {
            Route::get('/earthquakes', [BmkgController::class, 'getEarthquakeData']);
            Route::get('/earthquakes/latest', [BmkgController::class, 'getLatestEarthquake']);
            Route::get('/earthquakes/recent', [BmkgController::class, 'getRecentEarthquakes']);
            Route::get('/earthquakes/felt', [BmkgController::class, 'getFeltEarthquakes']);
            
            // BMKG Sync Endpoints (Store earthquake data as disasters)
            Route::post('/sync/latest', [BmkgController::class, 'syncLatestEarthquake']);
            Route::post('/sync/recent', [BmkgController::class, 'syncRecentEarthquakes']);
            Route::post('/sync/felt', [BmkgController::class, 'syncFeltEarthquakes']);
            Route::post('/sync/all', [BmkgController::class, 'syncAllEarthquakes']);
        });

    });

    // Disaster-Assigned Endpoints (Officer/Volunteer assigned to specific disaster)
    Route::middleware(['api_access', 'disaster_assigned'])->group(function () {
        
        // Disaster Management (Only for assigned volunteers/officers)
        Route::put('/disasters/{id}', [DisasterController::class, 'updateDisaster']);
        Route::put('/disasters/{id}/cancel', [DisasterController::class, 'cancelDisaster']);

        // Disaster Reports (Only for assigned volunteers/officers)
        Route::get('/disasters/{id}/reports', [DisasterReportController::class, 'getDisasterReports']);
        Route::post('/disasters/{id}/reports', [DisasterReportController::class, 'createDisasterReport']);
        Route::get('/disasters/{id}/reports/{reportId}', [DisasterReportController::class, 'getDisasterReport']);
        Route::put('/disasters/{id}/reports/{reportId}', [DisasterReportController::class, 'updateDisasterReport']);
        Route::delete('/disasters/{id}/reports/{reportId}', [DisasterReportController::class, 'deleteDisasterReport']);

        // Disaster Victims (Only for assigned volunteers/officers)
        Route::get('/disasters/{id}/victims', [DisasterVictimController::class, 'getDisasterVictims']);
        Route::post('/disasters/{id}/victims', [DisasterVictimController::class, 'createDisasterVictim']);
        Route::get('/disasters/{id}/victims/{victimId}', [DisasterVictimController::class, 'getDisasterVictim']);
        Route::put('/disasters/{id}/victims/{victimId}', [DisasterVictimController::class, 'updateDisasterVictim']);
        Route::delete('/disasters/{id}/victims/{victimId}', [DisasterVictimController::class, 'deleteDisasterVictim']);

        // Disaster Aids (Only for assigned volunteers/officers)
        Route::get('/disasters/{id}/aids', [DisasterAidController::class, 'getDisasterAids']);
        Route::post('/disasters/{id}/aids', [DisasterAidController::class, 'createDisasterAid']);
        Route::get('/disasters/{id}/aids/{aidId}', [DisasterAidController::class, 'getDisasterAid']);
        Route::put('/disasters/{id}/aids/{aidId}', [DisasterAidController::class, 'updateDisasterAid']);
        Route::delete('/disasters/{id}/aids/{aidId}', [DisasterAidController::class, 'deleteDisasterAid']);

    });

});

// Other versions if needed.