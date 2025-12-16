# Android FCM Integration Guide

## 📱 Overview

This guide will help you integrate Firebase Cloud Messaging (FCM) push notifications into your Android app to work with the Laravel backend.

---

## ✅ Prerequisites

- Firebase project already configured (you mentioned you can receive notifications from Firebase console)
- `google-services.json` file in your Android project
- Firebase Cloud Messaging dependency added
- Android app can already receive test notifications from Firebase console

---

## 🔧 Implementation Steps

### 1. Add FCM Token to Login Request

**Location:** Your login API call (likely in `AuthRepository`, `AuthService`, or `ApiService`)

**Before:**
```kotlin
data class LoginRequest(
    val email: String,
    val password: String
)
```

**After:**
```kotlin
data class LoginRequest(
    val email: String,
    val password: String,
    val fcm_token: String? = null,        // Add this
    val platform: String? = "android",     // Add this
    val device_id: String? = null,          // Optional
    val device_name: String? = null,       // Optional
    val app_version: String? = null         // Optional
)
```

**Get FCM Token and Include in Login:**
```kotlin
import com.google.firebase.messaging.FirebaseMessaging

// In your login function
fun login(email: String, password: String) {
    // Get FCM token
    FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
        if (task.isSuccessful) {
            val fcmToken = task.result
            
            // Create login request with FCM token
            val loginRequest = LoginRequest(
                email = email,
                password = password,
                fcm_token = fcmToken,
                platform = "android",
                device_id = getDeviceId(),        // Optional: Use Settings.Secure.ANDROID_ID
                device_name = Build.MODEL,        // Optional: e.g., "Samsung Galaxy S21"
                app_version = BuildConfig.VERSION_NAME  // Optional: e.g., "1.0.0"
            )
            
            // Make API call
            apiService.login(loginRequest).enqueue(...)
        } else {
            // Handle error getting token, but still proceed with login
            Log.e("FCM", "Failed to get FCM token: ${task.exception}")
            
            // Login without FCM token (optional)
            val loginRequest = LoginRequest(
                email = email,
                password = password
            )
            apiService.login(loginRequest).enqueue(...)
        }
    }
}

// Helper function to get device ID
private fun getDeviceId(): String {
    return Settings.Secure.getString(
        context.contentResolver,
        Settings.Secure.ANDROID_ID
    )
}
```

---

### 2. Handle FCM Token Refresh

FCM tokens can change (app reinstall, token refresh, etc.). You need to listen for token refresh and update the backend.

**Create a Token Refresh Service:**
```kotlin
import com.google.firebase.messaging.FirebaseMessaging
import android.util.Log

class FcmTokenManager(private val apiService: ApiService) {
    
    fun initializeTokenRefresh() {
        FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
            if (task.isSuccessful) {
                val token = task.result
                Log.d("FCM", "FCM Token: $token")
                
                // Update backend if user is logged in
                updateTokenOnBackend(token)
            } else {
                Log.e("FCM", "Failed to get FCM token: ${task.exception}")
            }
        }
    }
    
    private fun updateTokenOnBackend(token: String) {
        // Check if user is logged in (store auth state in SharedPreferences or similar)
        val isLoggedIn = // Your auth state check
        
        if (isLoggedIn) {
            // You can create a separate endpoint for token update, or
            // just update on next login (token will be updated via login)
            // For now, we'll update on next login automatically
            Log.d("FCM", "Token refreshed. Will update on next login.")
        }
    }
}
```

**Initialize in Application Class:**
```kotlin
class MyApplication : Application() {
    override fun onCreate() {
        super.onCreate()
        
        // Initialize FCM token refresh listener
        val apiService = // Your API service instance
        FcmTokenManager(apiService).initializeTokenRefresh()
    }
}
```

**Or use a FirebaseMessagingService:**
```kotlin
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage

class MyFirebaseMessagingService : FirebaseMessagingService() {
    
    override fun onNewToken(token: String) {
        super.onNewToken(token)
        Log.d("FCM", "New FCM token: $token")
        
        // Update backend if user is logged in
        updateTokenOnBackend(token)
    }
    
    private fun updateTokenOnBackend(token: String) {
        // Check if user is logged in
        val isLoggedIn = // Your auth state check
        
        if (isLoggedIn) {
            // Option 1: Update via login (simplest)
            // Token will be updated automatically on next login
            
            // Option 2: Create separate endpoint (if you implement it)
            // apiService.updateDeviceToken(token).enqueue(...)
        }
    }
    
    override fun onMessageReceived(remoteMessage: RemoteMessage) {
        super.onMessageReceived(remoteMessage)
        
        // Handle incoming push notification
        handleNotification(remoteMessage)
    }
    
    private fun handleNotification(remoteMessage: RemoteMessage) {
        val notification = remoteMessage.notification
        val data = remoteMessage.data
        
        if (notification != null) {
            // Show notification
            showNotification(
                title = notification.title ?: "New Notification",
                body = notification.body ?: "",
                data = data
            )
        }
    }
    
    private fun showNotification(title: String, body: String, data: Map<String, String>) {
        // Create and show notification
        // Use NotificationCompat.Builder
        // Handle click action based on data payload
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

---

### 3. Send FCM Token on Logout (Optional but Recommended)

**Update Logout Request:**
```kotlin
data class LogoutRequest(
    val fcm_token: String? = null  // Optional
)
```

**In your logout function:**
```kotlin
fun logout() {
    // Get current FCM token
    FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
        val fcmToken = if (task.isSuccessful) task.result else null
        
        val logoutRequest = LogoutRequest(
            fcm_token = fcmToken
        )
        
        apiService.logout(logoutRequest).enqueue(
            object : Callback<LogoutResponse> {
                override fun onResponse(
                    call: Call<LogoutResponse>,
                    response: Response<LogoutResponse>
                ) {
                    if (response.isSuccessful) {
                        // Clear local auth state
                        clearAuthState()
                    }
                }
                
                override fun onFailure(call: Call<LogoutResponse>, t: Throwable) {
                    // Handle error
                }
            }
        )
    }
}
```

---

### 4. Handle Incoming Push Notifications

**Create Notification Handler:**
```kotlin
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import androidx.core.app.NotificationCompat

class NotificationHandler(private val context: Context) {
    
    private val channelId = "disaster_notifications"
    private val channelName = "Disaster Notifications"
    
    init {
        createNotificationChannel()
    }
    
    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                channelId,
                channelName,
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
    
    fun showNotification(
        title: String,
        body: String,
        data: Map<String, String> = emptyMap()
    ) {
        // Create intent based on notification type
        val intent = when (data["type"]) {
            "new_disaster_victim_report" -> {
                // Navigate to victim details
                Intent(context, DisasterVictimDetailActivity::class.java).apply {
                    putExtra("disaster_id", data["disaster_id"])
                    putExtra("victim_id", data["victim_id"])
                    flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
                }
            }
            else -> {
                // Default: Navigate to notifications list
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
        
        val notification = NotificationCompat.Builder(context, channelId)
            .setSmallIcon(R.drawable.ic_notification)  // Your notification icon
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
        
        // Use unique ID for each notification
        val notificationId = System.currentTimeMillis().toInt()
        notificationManager.notify(notificationId, notification)
    }
}
```

**Update FirebaseMessagingService:**
```kotlin
class MyFirebaseMessagingService : FirebaseMessagingService() {
    
    private val notificationHandler by lazy {
        NotificationHandler(applicationContext)
    }
    
    override fun onMessageReceived(remoteMessage: RemoteMessage) {
        super.onMessageReceived(remoteMessage)
        
        val notification = remoteMessage.notification
        val data = remoteMessage.data
        
        if (notification != null) {
            notificationHandler.showNotification(
                title = notification.title ?: "New Notification",
                body = notification.body ?: "",
                data = data
            )
        }
    }
    
    override fun onNewToken(token: String) {
        super.onNewToken(token)
        Log.d("FCM", "New FCM token: $token")
        // Token will be updated on next login
    }
}
```

---

### 5. Request Notification Permissions (Android 13+)

**In your MainActivity or SplashActivity:**
```kotlin
import android.Manifest
import android.os.Build
import androidx.activity.result.contract.ActivityResultContracts

class MainActivity : AppCompatActivity() {
    
    private val requestPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { isGranted ->
        if (isGranted) {
            Log.d("Notifications", "Permission granted")
        } else {
            Log.d("Notifications", "Permission denied")
        }
    }
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        // Request notification permission for Android 13+
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            requestPermissionLauncher.launch(Manifest.permission.POST_NOTIFICATIONS)
        }
    }
}
```

**Add to AndroidManifest.xml:**
```xml
<uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
```

---

## 📋 Complete Implementation Checklist

- [ ] Update `LoginRequest` data class to include FCM token fields
- [ ] Get FCM token before login and include in request
- [ ] Handle FCM token refresh (onNewToken)
- [ ] Update logout to optionally send FCM token
- [ ] Create notification handler for displaying notifications
- [ ] Update FirebaseMessagingService to handle incoming messages
- [ ] Request notification permissions (Android 13+)
- [ ] Test login with FCM token
- [ ] Test receiving push notifications
- [ ] Test token refresh
- [ ] Test logout with FCM token

---

## 🧪 Testing

### 1. Test Token Storage

1. Login with your app
2. Check backend database:
   ```sql
   SELECT * FROM user_devices WHERE user_id = 'your_user_id';
   ```
3. Verify token is stored

### 2. Test Push Notification

1. Login with two different devices/users assigned to same disaster
2. Create a disaster victim report from one device
3. Verify other device receives push notification

### 3. Test Token Refresh

1. Clear app data (simulates token refresh)
2. Login again
3. Verify new token is stored in database

---

## 📝 Data Payload Structure

When a disaster victim is created, the backend sends this data:

```json
{
  "type": "new_disaster_victim_report",
  "disaster_id": "uuid",
  "victim_id": "uuid",
  "disaster_title": "Earthquake in Jakarta",
  "victim_name": "John Doe"
}
```

Use this data to navigate to the appropriate screen when notification is clicked.

---

## 🔍 Debugging

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

### Check Notification Receipt

Add logging in `onMessageReceived`:
```kotlin
override fun onMessageReceived(remoteMessage: RemoteMessage) {
    Log.d("FCM", "From: ${remoteMessage.from}")
    Log.d("FCM", "Message data: ${remoteMessage.data}")
    Log.d("FCM", "Notification: ${remoteMessage.notification?.title}")
    // ... rest of code
}
```

---

## 🚀 Quick Start Code Snippet

**Minimal Login Implementation:**
```kotlin
// In your login function
FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
    val loginRequest = LoginRequest(
        email = email,
        password = password,
        fcm_token = task.resultOrNull,
        platform = "android"
    )
    
    apiService.login(loginRequest).enqueue(...)
}
```

---

## 📚 Additional Resources

- [Firebase Cloud Messaging Android Guide](https://firebase.google.com/docs/cloud-messaging/android/client)
- [FCM Token Management](https://firebase.google.com/docs/cloud-messaging/manage-tokens)
- [Android Notification Guide](https://developer.android.com/develop/ui/views/notifications)

---

**Need help?** Check the backend logs or test with Firebase Console first to ensure FCM is working.

