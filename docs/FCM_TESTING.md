# FCM Push Notification Testing Guide

## ✅ Setup Complete

Your setup is complete:
- ✅ Firebase package installed (`kreait/firebase-php`)
- ✅ Firebase credentials file added
- ✅ Migration run (`user_devices` table created)

---

## 🧪 Testing Steps

### 1. Test FCM Service Initialization

```bash
php artisan tinker
```

```php
$fcm = new \App\Services\FcmService();
$fcm->isEnabled(); // Should return true
```

### 2. Test Token Storage (Login)

**API Request:**
```bash
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "fathur@edisaster.test",
  "password": "password",
  "fcm_token": "test_fcm_token_12345",
  "platform": "android",
  "device_name": "Test Device"
}
```

**Verify in Database:**
```bash
php artisan tinker
```

```php
\App\Models\UserDevice::all();
// Should show your test device
```

### 3. Test Notification Creation

**Create a Disaster Victim Report:**
```bash
POST /api/v1/disasters/{disaster_id}/victims
Authorization: Bearer {your_token}
Content-Type: application/json

{
  "nik": "1234567890123456",
  "name": "Test Victim",
  "date_of_birth": "1990-01-01",
  "gender": true,
  "status": "minor_injury",
  "description": "Test victim for notification"
}
```

**Check Results:**
1. **Database Notifications:**
```php
\App\Models\Notification::where('category', 'new_disaster_victim_report')->latest()->first();
```

2. **Logs:**
```bash
tail -f storage/logs/laravel.log | grep FCM
```

3. **FCM Push:** Check if push notification was sent (if you have real FCM tokens)

### 4. Test Token Deletion (Logout)

```bash
POST /api/v1/auth/logout
Authorization: Bearer {your_token}
Content-Type: application/json

{
  "fcm_token": "test_fcm_token_12345"
}
```

**Verify:**
```php
\App\Models\UserDevice::where('fcm_token', 'test_fcm_token_12345')->count();
// Should return 0
```

---

## 📱 Real Device Testing

### Prerequisites

1. **Android App Setup:**
   - App must get FCM token from Firebase SDK
   - Send token in login request
   - Handle token refresh

2. **Test Flow:**
   - Login with real FCM token from Android device
   - Create disaster victim report
   - Check if push notification received on device

---

## 🔍 Debugging

### Check FCM Service Status

```php
$fcm = app(\App\Services\FcmService::class);
$fcm->isEnabled(); // true = ready, false = check logs
```

### Check Logs

```bash
tail -f storage/logs/laravel.log
```

Look for:
- `FCM notifications sent` - Success
- `FCM service is not enabled` - Configuration issue
- `FCM send failed` - Error details

### Common Issues

**1. FCM Service Not Enabled**
- Check: `storage/app/firebase-credentials.json` exists
- Check: File permissions
- Check: Firebase package installed

**2. No Devices Found**
- Verify user has active devices: `UserDevice::where('is_active', true)->get()`
- Check if volunteers are assigned to disaster

**3. Invalid Tokens**
- Tokens are automatically cleaned up
- Check logs for cleanup messages

---

## 📊 Monitoring

### Database Queries

```php
// Check active devices
\App\Models\UserDevice::where('is_active', true)->count();

// Check notifications sent
\App\Models\Notification::where('category', 'new_disaster_victim_report')->count();

// Check recent FCM activity
\App\Models\UserDevice::where('last_used_at', '>', now()->subDay())->count();
```

---

## ✅ Success Criteria

- [ ] FCM service initializes successfully
- [ ] Tokens stored on login
- [ ] Tokens deleted on logout
- [ ] Database notifications created
- [ ] FCM push notifications sent (if real tokens)
- [ ] Invalid tokens cleaned up automatically
- [ ] Creator excluded from notifications

---

## 🚀 Next Steps

1. **Test with Real Android Device:**
   - Get real FCM token from device
   - Login with token
   - Create victim report
   - Verify push notification received

2. **Monitor Production:**
   - Check logs regularly
   - Monitor token cleanup
   - Track notification delivery rates

3. **Extend to Other Features:**
   - Disaster reports
   - Disaster aids
   - Disaster status changes

---

**Ready to test!** Start with Step 1 above.

