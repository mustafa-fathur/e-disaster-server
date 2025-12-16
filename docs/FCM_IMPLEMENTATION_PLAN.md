# Firebase Cloud Messaging (FCM) Implementation Plan

**Feature:** Push Notifications for Disaster Victim Reports  
**Date:** December 2024

---

## 📋 Overview

Implement FCM push notifications to send real-time alerts when a new disaster victim report is created. Notifications will be sent to all volunteers assigned to the disaster, except the creator.

---

## 🏗️ Architecture Design

### 1. Database Schema

#### Option A: Separate `user_devices` Table (Recommended) ✅

**Why:** Supports multiple devices per user (phone, tablet, web browser), better scalability, and proper device management.

**Migration:** `2025_12_XX_XXXXXX_create_user_devices_table.php`

```php
Schema::create('user_devices', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
    $table->string('fcm_token', 255)->unique();
    $table->enum('platform', ['android', 'ios', 'web'])->default('android');
    $table->string('device_id', 100)->nullable(); // Optional: device identifier
    $table->string('device_name', 100)->nullable(); // Optional: "Samsung Galaxy S21"
    $table->string('app_version', 20)->nullable(); // Optional: app version
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_used_at')->nullable();
    $table->timestamps();

    // Indexes for performance
    $table->index('user_id');
    $table->index('fcm_token');
    $table->index(['user_id', 'is_active']);
});
```

**Benefits:**

-   ✅ Multiple devices per user
-   ✅ Platform tracking (Android/iOS/Web)
-   ✅ Device management (activate/deactivate)
-   ✅ Token history and cleanup
-   ✅ Future-proof for web push notifications

#### Option B: Add Column to `users` Table (Not Recommended)

**Why Not:** Only supports one device per user, no device management, harder to scale.

---

### 2. Model Structure

**File:** `app/Models/UserDevice.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'fcm_token',
        'platform',
        'device_id',
        'device_name',
        'app_version',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

**Update User Model:** Add relationship

```php
// In app/Models/User.php
public function devices()
{
    return $this->hasMany(UserDevice::class);
}

public function activeDevices()
{
    return $this->hasMany(UserDevice::class)->where('is_active', true);
}
```

---

### 3. FCM Service Class

**File:** `app/Services/FcmService.php`

**Purpose:** Centralized service for all FCM operations (send, validate, handle errors)

**Responsibilities:**

-   Send push notifications to single/multiple tokens
-   Handle FCM API errors (invalid tokens, rate limits)
-   Token validation and cleanup
-   Retry logic for failed sends
-   Logging for debugging

**Key Methods:**

```php
- sendNotification($tokens, $title, $body, $data = [])
- sendToUser($userId, $title, $body, $data = [])
- sendToUsers($userIds, $title, $body, $data = [])
- sendToDisasterVolunteers($disasterId, $excludeUserId, $title, $body, $data = [])
- validateToken($token)
- cleanupInvalidTokens($tokens)
```

**Dependencies:**

-   `google/apiclient` or `kreait/firebase-php` package
-   FCM Server Key in `.env`

---

### 4. Token Management

#### A. Store Token on Login

**File:** `app/Http/Controllers/Api/V1/AuthController.php`

**Endpoint:** `POST /api/v1/auth/login`

**Changes:**

1. Add optional `fcm_token` and `platform` to request body
2. After successful login, store/update device token
3. Handle multiple devices (don't delete old tokens, just update)

**Logic:**

```php
// After successful login
if ($request->has('fcm_token')) {
    $platform = $request->input('platform', 'android');

    // Find existing device by token or create new
    $device = UserDevice::updateOrCreate(
        ['fcm_token' => $request->fcm_token],
        [
            'user_id' => $user->id,
            'platform' => $platform,
            'device_id' => $request->input('device_id'),
            'device_name' => $request->input('device_name'),
            'app_version' => $request->input('app_version'),
            'is_active' => true,
            'last_used_at' => now(),
        ]
    );
}
```

#### B. Delete Token on Logout

**File:** `app/Http/Controllers/Api/V1/AuthController.php`

**Endpoint:** `POST /api/v1/auth/logout`

**Options:**

**Option 1: Delete Token (Recommended for security)**

```php
// Delete device token on logout
if ($request->has('fcm_token')) {
    UserDevice::where('fcm_token', $request->fcm_token)
        ->where('user_id', $user->id)
        ->delete();
}
```

**Option 2: Deactivate Token (Keep for history)**

```php
// Deactivate instead of delete
UserDevice::where('fcm_token', $request->fcm_token)
    ->where('user_id', $user->id)
    ->update(['is_active' => false]);
```

**Recommendation:** Use **Option 1** (delete) for security and to prevent sending notifications to logged-out devices.

#### C. Optional: Token Refresh Endpoint

**Endpoint:** `PUT /api/v1/profile/device-token`

**Purpose:** Update token when it refreshes (FCM tokens can change)

```php
public function updateDeviceToken(Request $request)
{
    $validator = Validator::make($request->all(), [
        'fcm_token' => 'required|string',
        'old_fcm_token' => 'nullable|string', // Optional: for token refresh
        'platform' => 'nullable|in:android,ios,web',
    ]);

    $user = auth('sanctum')->user();

    // If old token provided, delete it
    if ($request->has('old_fcm_token')) {
        UserDevice::where('fcm_token', $request->old_fcm_token)
            ->where('user_id', $user->id)
            ->delete();
    }

    // Create or update new token
    UserDevice::updateOrCreate(
        ['fcm_token' => $request->fcm_token],
        [
            'user_id' => $user->id,
            'platform' => $request->input('platform', 'android'),
            'is_active' => true,
            'last_used_at' => now(),
        ]
    );

    return response()->json(['message' => 'Device token updated successfully'], 200);
}
```

---

### 5. Notification Integration

#### A. Update DisasterVictimController

**File:** `app/Http/Controllers/Api/V1/DisasterVictimController.php`

**Method:** `createDisasterVictim()`

**Changes:**

1. After successful victim creation
2. Create database notification records for all assigned volunteers
3. Send FCM push notifications to all assigned volunteers (except creator)
4. Handle errors gracefully (don't fail victim creation if FCM fails)

**Implementation:**

```php
// After $victim->save() in createDisasterVictim()

// 1. Get all volunteers assigned to this disaster (except creator)
$assignedVolunteers = DisasterVolunteer::where('disaster_id', $id)
    ->where('user_id', '!=', $user->id) // Exclude creator
    ->with('user.activeDevices')
    ->get();

// 2. Create database notifications
foreach ($assignedVolunteers as $volunteer) {
    Notification::create([
        'user_id' => $volunteer->user_id,
        'title' => 'New Disaster Victim Report',
        'message' => "A new victim report has been added to {$disaster->title}",
        'category' => NotificationTypeEnum::NEW_DISASTER_VICTIM_REPORT,
        'is_read' => false,
        'sent_at' => now(),
    ]);
}

// 3. Send FCM push notifications (in background/queue)
try {
    $fcmService = app(FcmService::class);
    $fcmService->sendToDisasterVolunteers(
        disasterId: $id,
        excludeUserId: $user->id,
        title: 'New Disaster Victim Report',
        body: "A new victim report has been added to {$disaster->title}",
        data: [
            'type' => 'new_disaster_victim_report',
            'disaster_id' => $disaster->id,
            'victim_id' => $victim->id,
            'disaster_title' => $disaster->title,
        ]
    );
} catch (\Exception $e) {
    // Log error but don't fail the request
    \Log::error('FCM notification failed: ' . $e->getMessage());
}
```

---

### 6. FCM Service Implementation

**File:** `app/Services/FcmService.php`

**Package Options:**

#### Option A: `kreait/firebase-php` (Recommended)

```bash
composer require kreait/firebase-php
```

**Pros:**

-   Official Firebase SDK
-   Well-maintained
-   Supports all Firebase features
-   Good error handling

#### Option B: Direct HTTP API

```php
// Use Guzzle to call FCM REST API
use Illuminate\Support\Facades\Http;
```

**Pros:**

-   No extra dependencies
-   Lightweight
-   Full control

**Cons:**

-   Manual error handling
-   More code to maintain

**Recommendation:** Use **Option A** (`kreait/firebase-php`)

**Implementation Structure:**

```php
<?php

namespace App\Services;

use App\Models\UserDevice;
use App\Models\DisasterVolunteer;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmService
{
    protected $messaging;

    public function __construct()
    {
        $factory = (new Factory)
            ->withServiceAccount(storage_path('app/firebase-credentials.json'));

        $this->messaging = $factory->createMessaging();
    }

    /**
     * Send notification to disaster volunteers
     */
    public function sendToDisasterVolunteers(
        string $disasterId,
        ?string $excludeUserId = null,
        string $title,
        string $body,
        array $data = []
    ): array {
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
            return ['success' => false, 'message' => 'No active devices found'];
        }

        return $this->sendNotification($tokens, $title, $body, $data);
    }

    /**
     * Send notification to multiple tokens
     */
    public function sendNotification(array $tokens, string $title, string $body, array $data = []): array
    {
        try {
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            // FCM supports up to 500 tokens per batch
            $chunks = array_chunk($tokens, 500);
            $results = [];

            foreach ($chunks as $chunk) {
                $result = $this->messaging->sendMulticast($message, $chunk);
                $results[] = $result;
            }

            // Handle invalid tokens
            $this->cleanupInvalidTokens($tokens, $results);

            return [
                'success' => true,
                'sent' => count($tokens),
                'results' => $results,
            ];
        } catch (\Exception $e) {
            Log::error('FCM send failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clean up invalid tokens
     */
    protected function cleanupInvalidTokens(array $tokens, array $results): void
    {
        // Extract invalid tokens from results and delete them
        foreach ($results as $result) {
            foreach ($result->invalidTokens() as $invalidToken) {
                UserDevice::where('fcm_token', $invalidToken)->delete();
            }
        }
    }
}
```

---

## 🔧 Configuration

### 1. Environment Variables

**File:** `.env`

```env
# Firebase Configuration
FIREBASE_CREDENTIALS_PATH=storage/app/firebase-credentials.json
FCM_SERVER_KEY=your_fcm_server_key_here
```

### 2. Firebase Credentials

**File:** `storage/app/firebase-credentials.json`

-   Download from Firebase Console → Project Settings → Service Accounts
-   Store securely (add to `.gitignore`)

### 3. Composer Package

```bash
composer require kreait/firebase-php
```

---

## 📝 Implementation Checklist

### Phase 1: Database & Models

-   [ ] Create `user_devices` migration
-   [ ] Create `UserDevice` model
-   [ ] Update `User` model with relationships
-   [ ] Run migration

### Phase 2: Token Management

-   [ ] Update `AuthController::login()` to store FCM token
-   [ ] Update `AuthController::logout()` to delete FCM token
-   [ ] (Optional) Add token refresh endpoint

### Phase 3: FCM Service

-   [ ] Install `kreait/firebase-php` package
-   [ ] Create `FcmService` class
-   [ ] Add Firebase credentials file
-   [ ] Configure environment variables
-   [ ] Test FCM service with test tokens

### Phase 4: Notification Integration

-   [ ] Update `DisasterVictimController::createDisasterVictim()`
-   [ ] Create database notifications for volunteers
-   [ ] Send FCM push notifications
-   [ ] Add error handling and logging

### Phase 5: Testing

-   [ ] Test token storage on login
-   [ ] Test token deletion on logout
-   [ ] Test notification creation
-   [ ] Test FCM push notification sending
-   [ ] Test with multiple devices
-   [ ] Test error handling (invalid tokens, network errors)

### Phase 6: Documentation

-   [ ] Update API documentation
-   [ ] Document FCM token endpoints
-   [ ] Add Android integration guide

---

## 🚀 API Changes Summary

### New Endpoints (Optional)

1. **Update Device Token**
    - `PUT /api/v1/profile/device-token`
    - Body: `{ "fcm_token": "...", "platform": "android" }`

### Modified Endpoints

1. **Login** (`POST /api/v1/auth/login`)

    - Add optional: `fcm_token`, `platform`, `device_id`, `device_name`, `app_version`

2. **Logout** (`POST /api/v1/auth/logout`)

    - Add optional: `fcm_token` (to delete specific device)

3. **Create Disaster Victim** (`POST /api/v1/disasters/{id}/victims`)
    - No API changes, but now sends push notifications

---

## 🔒 Security Considerations

1. **Token Validation**

    - Validate FCM token format
    - Check token belongs to authenticated user
    - Prevent token hijacking

2. **Rate Limiting**

    - Limit token updates per user
    - Prevent spam notifications

3. **Error Handling**

    - Don't expose FCM errors to clients
    - Log errors for debugging
    - Graceful degradation (notifications work even if FCM fails)

4. **Token Cleanup**
    - Remove invalid tokens automatically
    - Clean up old inactive tokens periodically

---

## 📊 Database Schema Summary

### New Table: `user_devices`

| Column         | Type        | Description                |
| -------------- | ----------- | -------------------------- |
| `id`           | uuid        | Primary key                |
| `user_id`      | uuid        | Foreign key to users       |
| `fcm_token`    | string(255) | FCM device token (unique)  |
| `platform`     | enum        | android/ios/web            |
| `device_id`    | string(100) | Optional device identifier |
| `device_name`  | string(100) | Optional device name       |
| `app_version`  | string(20)  | Optional app version       |
| `is_active`    | boolean     | Active status              |
| `last_used_at` | timestamp   | Last usage timestamp       |
| `created_at`   | timestamp   | Creation timestamp         |
| `updated_at`   | timestamp   | Update timestamp           |

---

## 🎯 Next Steps

1. **Review this plan** and confirm approach
2. **Set up Firebase project** (if not done)
3. **Download Firebase credentials** JSON file
4. **Start with Phase 1** (Database & Models)
5. **Test incrementally** after each phase

---

## 📚 Resources

-   [Firebase Cloud Messaging Documentation](https://firebase.google.com/docs/cloud-messaging)
-   [kreait/firebase-php Package](https://github.com/kreait/firebase-php)
-   [FCM HTTP v1 API](https://firebase.google.com/docs/cloud-messaging/migrate-v1)

---

**Questions or concerns?** Review this plan and let me know if you want to adjust anything before implementation!
