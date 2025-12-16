# Android FCM Integration - Context for Gemini AI

## 🎯 Project Context

**Project:** e-Disaster Android Application  
**Backend:** Laravel REST API with Firebase Cloud Messaging (FCM)  
**Task:** Integrate FCM push notifications to receive real-time alerts when disaster victim reports are created

---

## 📋 Current Backend API Status

### Backend is Already Implemented ✅

The Laravel backend is **fully implemented** and ready. You only need to implement the **Android client side**.

**Backend Endpoints:**

-   `POST /api/v1/auth/login` - Accepts FCM token (optional fields)
-   `POST /api/v1/auth/logout` - Accepts FCM token for deletion (optional)
-   `POST /api/v1/disasters/{id}/victims` - Creates victim and sends push notifications

**Backend Behavior:**

-   When a disaster victim is created, backend automatically:
    1. Creates database notifications for all assigned volunteers (except creator)
    2. Sends FCM push notifications to all active devices of assigned volunteers
    3. Excludes the creator from receiving notifications

---

## 🔧 What Needs to Be Implemented

### 1. Update Login API Call

-   Get FCM token before login
-   Include FCM token in login request body
-   Handle token retrieval errors gracefully

### 2. Handle FCM Token Refresh

-   Listen for token refresh events
-   Update backend when token changes (or let it update on next login)

### 3. Update Logout API Call (Optional)

-   Include FCM token in logout request to delete it from backend

### 4. Handle Incoming Push Notifications

-   Display notifications when received
-   Handle notification clicks
-   Navigate to appropriate screen based on notification data

### 5. Request Notification Permissions

-   Request POST_NOTIFICATIONS permission for Android 13+

---

## 📡 Backend API Specifications

### Login Endpoint

**URL:** `POST /api/v1/auth/login`

**Request Body:**

```json
{
    "email": "user@example.com",
    "password": "password123",
    "fcm_token": "dK3jH8f9L2mN4pQ5rS6tU7vW8xY9zA0bC1dE2fG3hI4jK5lM6nO7pQ8rS9tU0vW1xY2zA3bC4dE5f", // Optional
    "platform": "android", // Optional, enum: ["android", "ios", "web"]
    "device_id": "device_12345", // Optional
    "device_name": "Samsung Galaxy S21", // Optional
    "app_version": "1.0.0" // Optional
}
```

**Response (200):**

```json
{
    "message": "Login successful",
    "user": {
        "id": "uuid",
        "name": "User Name",
        "email": "user@example.com",
        "type": "volunteer",
        "status": "active"
    },
    "token": "sanctum_token_here"
}
```

**Notes:**

-   `fcm_token` and related fields are **optional**
-   If provided, backend stores the token in `user_devices` table
-   Backend uses `updateOrCreate` - same token updates existing record, new token creates new device

---

### Logout Endpoint

**URL:** `POST /api/v1/auth/logout`

**Headers:**

```
Authorization: Bearer {sanctum_token}
```

**Request Body (Optional):**

```json
{
    "fcm_token": "dK3jH8f9L2mN4pQ5rS6tU7vW8xY9zA0bC1dE2fG3hI4jK5lM6nO7pQ8rS9tU0vW1xY2zA3bC4dE5f"
}
```

**Response (200):**

```json
{
    "message": "Logged out successfully"
}
```

**Notes:**

-   If `fcm_token` is provided, backend deletes that device token
-   This prevents notifications from being sent to logged-out devices

---

### Create Disaster Victim Endpoint

**URL:** `POST /api/v1/disasters/{disaster_id}/victims`

**Headers:**

```
Authorization: Bearer {sanctum_token}
```

**Request Body:**

```json
{
    "nik": "1234567890123456",
    "name": "John Doe",
    "date_of_birth": "1990-01-01",
    "gender": true,
    "status": "minor_injury",
    "contact_info": "081234567890",
    "description": "Victim description",
    "is_evacuated": false
}
```

**Response (201):**

```json
{
  "message": "Disaster victim created successfully.",
  "data": {
    "id": "victim_uuid",
    "disaster_id": "disaster_uuid",
    "name": "John Doe",
    ...
  }
}
```

**Backend Behavior:**

-   After creating victim, backend automatically:
    1. Creates database notifications for all volunteers assigned to disaster (except creator)
    2. Sends FCM push notifications to all active devices
    3. Notification payload includes: `type`, `disaster_id`, `victim_id`, `disaster_title`, `victim_name`

---

## 📨 FCM Push Notification Payload

When a disaster victim is created, backend sends FCM notification with this structure:

**Notification:**

```json
{
    "title": "New Disaster Victim Report",
    "body": "A new victim report has been added to {disaster_title}"
}
```

**Data Payload:**

```json
{
    "type": "new_disaster_victim_report",
    "disaster_id": "uuid",
    "victim_id": "uuid",
    "disaster_title": "Earthquake in Jakarta",
    "victim_name": "John Doe"
}
```

**Use this data to:**

-   Navigate to victim detail screen when notification is clicked
-   Show appropriate UI based on notification type
-   Update local database/cache if needed

---

## 🏗️ Implementation Requirements

### 1. Data Classes

**Update LoginRequest:**

```kotlin
data class LoginRequest(
    val email: String,
    val password: String,
    val fcm_token: String? = null,
    val platform: String? = "android",
    val device_id: String? = null,
    val device_name: String? = null,
    val app_version: String? = null
)
```

**Update LogoutRequest (if exists):**

```kotlin
data class LogoutRequest(
    val fcm_token: String? = null
)
```

---

### 2. FCM Token Management

**Get FCM Token:**

```kotlin
FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
    if (task.isSuccessful) {
        val token = task.result
        // Use token in login request
    } else {
        // Handle error - can still login without token
    }
}
```

**Device ID Helper:**

```kotlin
fun getDeviceId(context: Context): String {
    return Settings.Secure.getString(
        context.contentResolver,
        Settings.Secure.ANDROID_ID
    )
}
```

**Device Name:**

```kotlin
Build.MODEL  // e.g., "SM-G991B" or "Pixel 5"
```

**App Version:**

```kotlin
BuildConfig.VERSION_NAME  // e.g., "1.0.0"
```

---

### 3. Login Implementation

**Pseudocode:**

```
1. User enters email/password
2. Get FCM token (async)
3. Create LoginRequest with email, password, and FCM token (if available)
4. Make API call
5. Handle response (store token, navigate to home, etc.)
```

**Important:**

-   Don't block login if FCM token retrieval fails
-   Token is optional - login should work even without it
-   If token retrieval takes time, you can:
    -   Wait for token before login (better UX)
    -   Login first, then update token separately (faster login)

---

### 4. FCM Token Refresh Handling

**Create FirebaseMessagingService:**

```kotlin
class MyFirebaseMessagingService : FirebaseMessagingService() {

    override fun onNewToken(token: String) {
        super.onNewToken(token)
        // Token refreshed - update backend on next login
        // Or create separate endpoint to update token
    }

    override fun onMessageReceived(remoteMessage: RemoteMessage) {
        // Handle incoming notification
    }
}
```

**Register in AndroidManifest.xml:**

```xml
<service
    android:name=".MyFirebaseMessagingService"
    android:exported="false">
    <intent-filter>
        <action android:name="com.google.firebase.MESSAGING_EVENT" />
    </intent-filter>
</service>
```

**Token Refresh Strategy:**

-   **Option 1 (Simplest):** Token updates automatically on next login
-   **Option 2:** Create separate endpoint `PUT /api/v1/profile/device-token` to update token immediately

---

### 5. Notification Display

**Create Notification Channel (Android 8.0+):**

```kotlin
private fun createNotificationChannel(context: Context) {
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
        val channel = NotificationChannel(
            "disaster_notifications",
            "Disaster Notifications",
            NotificationManager.IMPORTANCE_HIGH
        ).apply {
            description = "Notifications for disaster updates"
        }

        val notificationManager = context.getSystemService(
            Context.NOTIFICATION_SERVICE
        ) as NotificationManager

        notificationManager.createNotificationChannel(channel)
    }
}
```

**Show Notification:**

```kotlin
fun showNotification(
    context: Context,
    title: String,
    body: String,
    data: Map<String, String>
) {
    // Create intent based on notification type
    val intent = when (data["type"]) {
        "new_disaster_victim_report" -> {
            Intent(context, DisasterVictimDetailActivity::class.java).apply {
                putExtra("disaster_id", data["disaster_id"])
                putExtra("victim_id", data["victim_id"])
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            }
        }
        else -> {
            Intent(context, NotificationsActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            }
        }
    }

    val pendingIntent = PendingIntent.getActivity(
        context,
        0,
        intent,
        PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
    )

    val notification = NotificationCompat.Builder(context, "disaster_notifications")
        .setSmallIcon(R.drawable.ic_notification)
        .setContentTitle(title)
        .setContentText(body)
        .setStyle(NotificationCompat.BigTextStyle().bigText(body))
        .setPriority(NotificationCompat.PRIORITY_HIGH)
        .setContentIntent(pendingIntent)
        .setAutoCancel(true)
        .build()

    val notificationManager = context.getSystemService(
        Context.NOTIFICATION_SERVICE
    ) as NotificationManager

    notificationManager.notify(System.currentTimeMillis().toInt(), notification)
}
```

---

### 6. Notification Permissions (Android 13+)

**Request Permission:**

```kotlin
if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
    if (ContextCompat.checkSelfPermission(
            this,
            Manifest.permission.POST_NOTIFICATIONS
        ) != PackageManager.PERMISSION_GRANTED
    ) {
        ActivityCompat.requestPermissions(
            this,
            arrayOf(Manifest.permission.POST_NOTIFICATIONS),
            REQUEST_NOTIFICATION_PERMISSION
        )
    }
}
```

**Add to AndroidManifest.xml:**

```xml
<uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
```

---

## 📝 Implementation Checklist

### Phase 1: Basic Integration

-   [ ] Update `LoginRequest` data class with FCM fields
-   [ ] Get FCM token before login
-   [ ] Include FCM token in login API call
-   [ ] Test login with FCM token
-   [ ] Verify token stored in backend (check database or logs)

### Phase 2: Token Refresh

-   [ ] Create `FirebaseMessagingService` class
-   [ ] Implement `onNewToken()` method
-   [ ] Register service in AndroidManifest.xml
-   [ ] Test token refresh (clear app data, login again)

### Phase 3: Notification Handling

-   [ ] Implement `onMessageReceived()` in FirebaseMessagingService
-   [ ] Create notification channel
-   [ ] Display notifications
-   [ ] Handle notification clicks
-   [ ] Navigate to appropriate screen based on data payload

### Phase 4: Logout Integration

-   [ ] Update `LogoutRequest` with FCM token (optional)
-   [ ] Include FCM token in logout API call
-   [ ] Test logout deletes token from backend

### Phase 5: Permissions & Polish

-   [ ] Request notification permission (Android 13+)
-   [ ] Handle permission denied gracefully
-   [ ] Add error handling for FCM token retrieval
-   [ ] Test end-to-end flow

---

## 🧪 Testing Scenarios

### Test 1: Login with FCM Token

1. Login with app
2. Check backend database: `SELECT * FROM user_devices WHERE user_id = 'your_user_id'`
3. Verify token is stored

### Test 2: Receive Push Notification

1. Login with Device A (User 1)
2. Login with Device B (User 2)
3. Both users assigned to same disaster
4. Create victim report from Device A
5. Verify Device B receives push notification

### Test 3: Token Refresh

1. Clear app data (simulates token refresh)
2. Login again
3. Verify new token stored in backend

### Test 4: Logout Deletes Token

1. Login with FCM token
2. Logout with FCM token
3. Verify token deleted from backend
4. Create victim report - verify logged-out device doesn't receive notification

---

## 🔍 Debugging Tips

### Check FCM Token

```kotlin
FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
    if (task.isSuccessful) {
        Log.d("FCM", "Token: ${task.result}")
    } else {
        Log.e("FCM", "Error: ${task.exception}")
    }
}
```

### Log Notification Receipt

```kotlin
override fun onMessageReceived(remoteMessage: RemoteMessage) {
    Log.d("FCM", "From: ${remoteMessage.from}")
    Log.d("FCM", "Data: ${remoteMessage.data}")
    Log.d("FCM", "Title: ${remoteMessage.notification?.title}")
    Log.d("FCM", "Body: ${remoteMessage.notification?.body}")
}
```

### Verify Backend Integration

-   Check Laravel logs: `storage/logs/laravel.log`
-   Look for "FCM notifications sent" messages
-   Check for any FCM errors

---

## 📚 Key Points for Implementation

1. **FCM Token is Optional:** Login should work even if token retrieval fails
2. **Token Updates Automatically:** Same token updates existing device, new token creates new device
3. **Creator Excluded:** User who creates victim won't receive notification (backend handles this)
4. **Multiple Devices:** User can have multiple devices - all receive notifications
5. **Token Cleanup:** Invalid tokens are automatically cleaned up by backend
6. **Error Handling:** FCM failures don't break the app - handle gracefully

---

## 🎯 Expected Behavior

### When User Logs In:

1. App gets FCM token
2. App sends login request with token
3. Backend stores token in `user_devices` table
4. User can now receive push notifications

### When Victim Report is Created:

1. Creator creates victim report via API
2. Backend creates database notifications for all assigned volunteers (except creator)
3. Backend sends FCM push to all active devices of assigned volunteers
4. Other volunteers receive push notification on their devices
5. Clicking notification navigates to victim detail screen

### When Token Refreshes:

1. Firebase calls `onNewToken()`
2. App can update backend immediately (if endpoint exists) or wait for next login
3. Backend updates token automatically on next login

---

## 🚨 Common Issues & Solutions

### Issue: Token Not Stored

-   **Check:** Is FCM token being retrieved successfully?
-   **Check:** Is token included in login request?
-   **Check:** Backend logs for errors

### Issue: Not Receiving Notifications

-   **Check:** Is user assigned to disaster?
-   **Check:** Does user have active device token?
-   **Check:** Is notification permission granted (Android 13+)?
-   **Check:** Backend logs for FCM sending status

### Issue: Token Refresh Not Working

-   **Check:** Is FirebaseMessagingService registered in manifest?
-   **Check:** Is `onNewToken()` being called?
-   **Check:** Is token being sent on next login?

---

## 📖 Code Structure Recommendations

### Repository Pattern (Recommended)

```kotlin
class AuthRepository {
    suspend fun login(email: String, password: String): Result<LoginResponse> {
        // Get FCM token
        val fcmToken = getFcmToken()

        // Create request
        val request = LoginRequest(
            email = email,
            password = password,
            fcm_token = fcmToken,
            platform = "android"
        )

        // Make API call
        return apiService.login(request)
    }

    private suspend fun getFcmToken(): String? {
        return try {
            FirebaseMessaging.getInstance().token.await()
        } catch (e: Exception) {
            null
        }
    }
}
```

### ViewModel Pattern

```kotlin
class LoginViewModel : ViewModel() {
    fun login(email: String, password: String) {
        viewModelScope.launch {
            val result = authRepository.login(email, password)
            // Handle result
        }
    }
}
```

---

## ✅ Success Criteria

Implementation is successful when:

1. ✅ User can login with FCM token
2. ✅ Token is stored in backend database
3. ✅ User receives push notification when victim report is created
4. ✅ Notification click navigates to correct screen
5. ✅ Token refresh works correctly
6. ✅ Logout deletes token (optional but recommended)

---

## 🎓 Additional Notes

-   **Backend is Ready:** All backend code is implemented and tested
-   **Focus on Android:** Only Android client implementation is needed
-   **Follow Android Best Practices:** Use coroutines, ViewModel, Repository pattern
-   **Error Handling:** Always handle FCM errors gracefully
-   **Testing:** Test with real devices, not just emulator
-   **Documentation:** Comment your code for future maintenance

---

**This context provides everything needed to implement FCM push notifications in the Android app. Follow the checklist and test each phase before moving to the next.**
