# FCM Push Notification Setup Guide

## ✅ Implementation Complete

All backend code has been implemented. Follow these steps to complete the setup:

---

## 📋 Setup Steps

### 1. Install Firebase PHP Package

```bash
composer require kreait/firebase-php
```

### 2. Get Firebase Credentials

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Select your project
3. Go to **Project Settings** → **Service Accounts**
4. Click **Generate New Private Key**
5. Download the JSON file
6. Save it as `storage/app/firebase-credentials.json`

**Important:** Add to `.gitignore`:
```
storage/app/firebase-credentials.json
```

### 3. Configure Environment (Optional)

Add to `.env` (optional, defaults to `storage/app/firebase-credentials.json`):
```env
FIREBASE_CREDENTIALS_PATH=storage/app/firebase-credentials.json
```

### 4. Run Migration

```bash
php artisan migrate
```

This will create the `user_devices` table.

---

## 🧪 Testing

### Test Token Storage

1. **Login with FCM token:**
```bash
POST /api/v1/auth/login
{
  "email": "user@example.com",
  "password": "password",
  "fcm_token": "test_token_123",
  "platform": "android"
}
```

2. **Check database:**
```bash
php artisan tinker
>>> App\Models\UserDevice::all();
```

### Test Notification Sending

1. Create a disaster victim report via API
2. Check logs: `storage/logs/laravel.log`
3. Verify notifications were created in database
4. Check if FCM push was sent (if Firebase is configured)

---

## 📱 Android App Integration

Your Android app needs to:

1. **Get FCM token:**
```kotlin
FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
    if (task.isSuccessful) {
        val fcmToken = task.result
        // Include in login request
    }
}
```

2. **Send token on login:**
```kotlin
POST /api/v1/auth/login
{
  "email": "...",
  "password": "...",
  "fcm_token": fcmToken,  // ← Add this
  "platform": "android"   // ← Add this
}
```

3. **Handle token refresh:**
```kotlin
// Listen for token refresh
FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
    // Update backend with new token
}
```

4. **Send token on logout (optional):**
```kotlin
POST /api/v1/auth/logout
{
  "fcm_token": currentToken  // ← Optional
}
```

---

## 🔍 Verification Checklist

- [ ] Firebase package installed (`composer require kreait/firebase-php`)
- [ ] Firebase credentials file placed in `storage/app/firebase-credentials.json`
- [ ] Migration run (`php artisan migrate`)
- [ ] Test login with FCM token
- [ ] Verify token stored in `user_devices` table
- [ ] Test creating disaster victim report
- [ ] Verify notifications created in database
- [ ] Check logs for FCM sending status

---

## 🐛 Troubleshooting

### FCM Service Not Enabled

**Error:** `FCM service is not enabled`

**Solution:**
1. Check if `kreait/firebase-php` is installed
2. Verify `firebase-credentials.json` exists
3. Check file permissions
4. Review logs: `storage/logs/laravel.log`

### Invalid Tokens

**Issue:** Tokens are being deleted automatically

**Solution:** This is normal. FCM automatically cleans up invalid tokens when they're detected.

### No Notifications Sent

**Check:**
1. Are volunteers assigned to the disaster?
2. Do volunteers have active devices?
3. Is FCM service enabled?
4. Check logs for errors

---

## 📊 Database Schema

### `user_devices` Table

- Stores FCM tokens for each user device
- Supports multiple devices per user
- Tracks platform (android/ios/web)
- Automatically cleans up invalid tokens

---

## 🔐 Security Notes

1. **Token Storage:** Tokens are stored securely in database
2. **Token Cleanup:** Invalid tokens are automatically removed
3. **Error Handling:** FCM failures don't break the API
4. **Logging:** All FCM operations are logged for debugging

---

## 📝 Next Steps

1. Complete Android app integration (send tokens on login)
2. Test end-to-end notification flow
3. Monitor logs for any issues
4. Consider adding more notification types (reports, aids, etc.)

---

**Questions?** Check the logs or review the implementation in:
- `app/Services/FcmService.php`
- `app/Http/Controllers/Api/V1/DisasterVictimController.php`
- `app/Http/Controllers/Api/V1/AuthController.php`

