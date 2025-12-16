# Firebase Credentials Deployment Guide

Since `firebase-credentials.json` is in `.gitignore` (for security), you need to deploy it to your remote server separately. Here are secure methods:

---

## 🔐 Method 1: Manual SCP/SFTP Upload (Recommended for Quick Setup)

### Step 1: Upload the file via SCP

```bash
# From your local machine
scp storage/app/firebase-credentials.json user@your-server.com:/path/to/laravel/storage/app/

# Example:
scp storage/app/firebase-credentials.json root@e-disaster.fathur.tech:/var/www/e-disaster/storage/app/
```

### Step 2: Set proper permissions

```bash
# SSH into your server
ssh user@your-server.com

# Navigate to your Laravel project
cd /path/to/laravel

# Set permissions (Laravel needs read access)
chmod 600 storage/app/firebase-credentials.json
chown www-data:www-data storage/app/firebase-credentials.json  # Adjust user/group as needed
```

---

## 🚀 Method 2: GitHub Actions Secrets (For CI/CD Deployment)

If you're using GitHub Actions for deployment:

### Step 1: Add Secret to GitHub

1. Go to your GitHub repository
2. **Settings** → **Secrets and variables** → **Actions**
3. Click **New repository secret**
4. Name: `FIREBASE_CREDENTIALS_JSON`
5. Value: Paste the entire contents of `firebase-credentials.json`
6. Click **Add secret**

### Step 2: Create/Update Deployment Workflow

Create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup SSH
        uses: webfactory/ssh-agent@v0.9.0
        with:
          ssh-private-key: ${{ secrets.SSH_PRIVATE_KEY }}

      - name: Deploy to server
        run: |
          # Create credentials file from secret
          echo '${{ secrets.FIREBASE_CREDENTIALS_JSON }}' > storage/app/firebase-credentials.json
          
          # Deploy via rsync or SSH
          rsync -avz --exclude='.git' \
            --exclude='node_modules' \
            --exclude='vendor' \
            ./ user@your-server.com:/path/to/laravel/
          
          # Or use SSH commands
          ssh user@your-server.com << 'EOF'
            cd /path/to/laravel
            echo '${{ secrets.FIREBASE_CREDENTIALS_JSON }}' > storage/app/firebase-credentials.json
            chmod 600 storage/app/firebase-credentials.json
            chown www-data:www-data storage/app/firebase-credentials.json
            php artisan config:cache
            php artisan route:cache
          EOF
```

---

## 🔧 Method 3: Base64 Encoded Environment Variable

If you prefer using environment variables:

### Step 1: Encode the credentials file

```bash
# On your local machine
base64 -i storage/app/firebase-credentials.json | pbcopy  # macOS
# or
base64 storage/app/firebase-credentials.json | xclip -selection clipboard  # Linux
```

### Step 2: Add to server's `.env` file

```bash
# SSH into server
ssh user@your-server.com

# Edit .env file
nano /path/to/laravel/.env

# Add this line:
FIREBASE_CREDENTIALS_BASE64=<paste_base64_string_here>
```

### Step 3: Update FcmService to decode (if using this method)

You would need to modify `app/Services/FcmService.php` to decode from base64:

```php
// In FcmService constructor
$credentialsPath = config('services.firebase.credentials_path');

if (env('FIREBASE_CREDENTIALS_BASE64')) {
    // Decode from base64
    $credentialsJson = base64_decode(env('FIREBASE_CREDENTIALS_BASE64'));
    $tempPath = storage_path('app/firebase-credentials-temp.json');
    file_put_contents($tempPath, $credentialsJson);
    $credentialsPath = $tempPath;
}
```

**Note:** This method is more complex. Method 1 or 2 is recommended.

---

## 📦 Method 4: Server Secrets Management

If you're using a service like **Laravel Forge**, **Ploi**, or **AWS Secrets Manager**:

### Laravel Forge
1. Go to your server in Forge
2. Navigate to **Files** → **storage/app/**
3. Upload `firebase-credentials.json` directly
4. Set permissions via Forge's file manager

### AWS Secrets Manager / Parameter Store
1. Store credentials in AWS Secrets Manager
2. Use AWS SDK to retrieve in your deployment script
3. Write to file on server during deployment

---

## ✅ Verification After Deployment

After deploying the credentials file, verify it works:

```bash
# SSH into server
ssh user@your-server.com
cd /path/to/laravel

# Test FCM service initialization
php artisan tinker
```

```php
// In tinker
$fcm = app(\App\Services\FcmService::class);
$fcm->isEnabled(); // Should return true
```

Or test via API:
```bash
# Create a test notification (if you have a test endpoint)
curl -X POST https://e-disaster.fathur.tech/api/test-fcm \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 🔒 Security Best Practices

1. **File Permissions**: Always set `600` (read/write for owner only)
   ```bash
   chmod 600 storage/app/firebase-credentials.json
   ```

2. **File Ownership**: Ensure web server user can read it
   ```bash
   chown www-data:www-data storage/app/firebase-credentials.json
   ```

3. **Never commit**: Keep it in `.gitignore` ✅ (already done)

4. **Rotate credentials**: If compromised, generate new credentials in Firebase Console

5. **Backup securely**: Store encrypted backup of credentials in secure location

---

## 🎯 Recommended Approach

For most setups, **Method 1 (SCP/SFTP)** is the simplest and most secure:
- ✅ Direct file transfer
- ✅ No additional infrastructure needed
- ✅ Full control over file permissions
- ✅ Works with any server setup

For automated deployments, use **Method 2 (GitHub Actions Secrets)**:
- ✅ Automated deployment
- ✅ No manual file transfers
- ✅ Integrates with CI/CD pipeline

---

## 📝 Quick Reference

**One-time setup (Method 1):**
```bash
scp storage/app/firebase-credentials.json user@server:/path/to/laravel/storage/app/
ssh user@server "cd /path/to/laravel && chmod 600 storage/app/firebase-credentials.json"
```

**Verify:**
```bash
ssh user@server "cd /path/to/laravel && ls -la storage/app/firebase-credentials.json"
# Should show: -rw------- (600 permissions)
```

