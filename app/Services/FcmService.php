<?php

namespace App\Services;

use App\Models\UserDevice;
use App\Models\DisasterVolunteer;
use Illuminate\Support\Facades\Log;

class FcmService
{
    protected $messaging = null;
    protected $enabled = false;

    public function __construct()
    {
        // Check if Firebase package is installed and configured
        if (class_exists('\Kreait\Firebase\Factory')) {
            try {
                // Get credentials path from config
                $configPath = config('services.firebase.credentials_path', 'app/firebase-credentials.json');
                
                // Resolve path - handle both relative and absolute paths
                if (str_starts_with($configPath, '/')) {
                    // Absolute path
                    $credentialsPath = $configPath;
                } elseif (str_starts_with($configPath, 'storage/')) {
                    // If config has 'storage/' prefix, remove it and resolve
                    $configPath = str_replace('storage/', '', $configPath);
                    $credentialsPath = storage_path($configPath);
                } else {
                    // Relative path - resolve from storage directory
                    $credentialsPath = storage_path($configPath);
                }
                
                if (file_exists($credentialsPath)) {
                    $factory = (new \Kreait\Firebase\Factory)
                        ->withServiceAccount($credentialsPath);
                    
                    $this->messaging = $factory->createMessaging();
                    $this->enabled = true;
                    
                    Log::info('FCM service initialized successfully', [
                        'credentials_path' => $credentialsPath
                    ]);
                } else {
                    Log::warning('Firebase credentials file not found', [
                        'path' => $credentialsPath,
                        'config_path' => $configPath,
                        'storage_path' => storage_path('app'),
                        'file_exists' => file_exists($credentialsPath)
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to initialize Firebase', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } else {
            Log::warning('Firebase package (kreait/firebase-php) not installed. Run: composer require kreait/firebase-php');
        }
    }

    /**
     * Send notification to disaster volunteers
     * 
     * @param string $disasterId
     * @param string $title
     * @param string $body
     * @param array $data Additional data payload
     * @param string|null $excludeUserId User ID to exclude from notifications
     * @return array
     */
    public function sendToDisasterVolunteers(
        string $disasterId,
        string $title,
        string $body,
        array $data = [],
        ?string $excludeUserId = null
    ): array {
        if (!$this->enabled) {
            Log::warning('FCM service is not enabled. Skipping notification.');
            return [
                'success' => false,
                'message' => 'FCM service is not enabled'
            ];
        }

        // Get all active devices for assigned volunteers
        $volunteers = DisasterVolunteer::where('disaster_id', $disasterId)
            ->when($excludeUserId, fn($q) => $q->where('user_id', '!=', $excludeUserId))
            ->with('user.activeDevices')
            ->get();

        $tokens = [];
        foreach ($volunteers as $volunteer) {
            foreach ($volunteer->user->activeDevices as $device) {
                $tokens[] = $device->fcm_token;
            }
        }

        if (empty($tokens)) {
            Log::info('No active devices found for disaster volunteers', [
                'disaster_id' => $disasterId,
                'exclude_user_id' => $excludeUserId
            ]);
            return [
                'success' => false,
                'message' => 'No active devices found'
            ];
        }

        return $this->sendNotification($tokens, $title, $body, $data);
    }

    /**
     * Send notification to multiple tokens
     * 
     * @param array $tokens Array of FCM tokens
     * @param string $title
     * @param string $body
     * @param array $data Additional data payload
     * @return array
     */
    public function sendNotification(array $tokens, string $title, string $body, array $data = []): array
    {
        if (!$this->enabled) {
            Log::warning('FCM service is not enabled. Skipping notification.');
            return [
                'success' => false,
                'message' => 'FCM service is not enabled'
            ];
        }

        if (empty($tokens)) {
            return [
                'success' => false,
                'message' => 'No tokens provided'
            ];
        }

        try {
            $message = \Kreait\Firebase\Messaging\CloudMessage::new()
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                ->withData($data);

            // FCM supports up to 500 tokens per batch
            $chunks = array_chunk($tokens, 500);
            $totalSent = 0;
            $totalFailed = 0;
            $invalidTokens = [];

            foreach ($chunks as $chunk) {
                try {
                    $result = $this->messaging->sendMulticast($message, $chunk);
                    $totalSent += $result->successes()->count();
                    $totalFailed += $result->failures()->count();
                    
                    // Collect invalid tokens
                    foreach ($result->invalidTokens() as $invalidToken) {
                        $invalidTokens[] = $invalidToken;
                    }
                } catch (\Exception $e) {
                    Log::error('FCM batch send failed: ' . $e->getMessage(), [
                        'tokens_count' => count($chunk)
                    ]);
                    $totalFailed += count($chunk);
                }
            }

            // Clean up invalid tokens
            if (!empty($invalidTokens)) {
                $this->cleanupInvalidTokens($invalidTokens);
            }

            Log::info('FCM notifications sent', [
                'total_tokens' => count($tokens),
                'sent' => $totalSent,
                'failed' => $totalFailed,
                'invalid_tokens' => count($invalidTokens)
            ]);

            return [
                'success' => $totalSent > 0,
                'sent' => $totalSent,
                'failed' => $totalFailed,
                'invalid_tokens' => count($invalidTokens),
                'message' => $totalSent > 0 
                    ? "Sent {$totalSent} notifications successfully" 
                    : "Failed to send notifications"
            ];
        } catch (\Exception $e) {
            Log::error('FCM send failed: ' . $e->getMessage(), [
                'tokens_count' => count($tokens),
                'title' => $title
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to send notifications: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send notification to a single user
     * 
     * @param string $userId
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendToUser(string $userId, string $title, string $body, array $data = []): array
    {
        $user = \App\Models\User::with('activeDevices')->find($userId);
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found'
            ];
        }

        $tokens = $user->activeDevices->pluck('fcm_token')->toArray();

        if (empty($tokens)) {
            return [
                'success' => false,
                'message' => 'User has no active devices'
            ];
        }

        return $this->sendNotification($tokens, $title, $body, $data);
    }

    /**
     * Send notification to multiple users
     * 
     * @param array $userIds
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): array
    {
        $users = \App\Models\User::with('activeDevices')
            ->whereIn('id', $userIds)
            ->get();

        $tokens = [];
        foreach ($users as $user) {
            foreach ($user->activeDevices as $device) {
                $tokens[] = $device->fcm_token;
            }
        }

        if (empty($tokens)) {
            return [
                'success' => false,
                'message' => 'No active devices found for users'
            ];
        }

        return $this->sendNotification($tokens, $title, $body, $data);
    }

    /**
     * Clean up invalid tokens from database
     * 
     * @param array $invalidTokens
     * @return void
     */
    protected function cleanupInvalidTokens(array $invalidTokens): void
    {
        if (empty($invalidTokens)) {
            return;
        }

        $deleted = UserDevice::whereIn('fcm_token', $invalidTokens)->delete();
        
        Log::info('Cleaned up invalid FCM tokens', [
            'count' => $deleted
        ]);
    }

    /**
     * Check if FCM service is enabled
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}

