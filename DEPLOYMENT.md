# Hostinger Shared Hosting Deployment Guide

## Prerequisites

- Hostinger Business plan (SSH access required)
- Domain configured and pointing to Hostinger
- PHP 8.2+ (check via hPanel > Advanced > PHP Configuration)
- MySQL database created via hPanel

## Pre-Deployment: Local Build

Before uploading, build the frontend assets locally:

```bash
# Install dependencies and build
npm install
npm run build

# Generate Wayfinder routes
php artisan wayfinder:generate

# Verify build output exists
ls -la public/build/
```

## Step 1: Configure Hosting Environment

### 1.1 Set PHP Version
1. Go to hPanel > Advanced > PHP Configuration
2. Select PHP 8.2 or 8.3 (8.4 may not be available yet)
3. Enable required extensions:
   - `pdo_mysql`
   - `mbstring`
   - `openssl`
   - `tokenizer`
   - `xml`
   - `ctype`
   - `json`
   - `bcmath`
   - `fileinfo`

### 1.2 Create MySQL Database
1. Go to hPanel > Databases > MySQL Databases
2. Create a new database and note:
   - Database name
   - Username
   - Password

## Step 2: Upload Files

### Option A: Via SSH (Recommended)

```bash
# SSH into Hostinger
ssh u123456789@your-server.hostinger.com

# Navigate to public_html or your domain folder
cd ~/domains/yourdomain.com/public_html

# Clone or upload your project (outside public_html first)
cd ~/domains/yourdomain.com
git clone https://github.com/your-repo/rackaudit.git app

# Create symlink for public files
rm -rf public_html
ln -s app/public public_html
```

### Option B: Via File Manager/FTP

1. Upload all project files to `~/domains/yourdomain.com/app/`
2. Keep only the `public/` folder contents in `public_html/`
3. Update `public/index.php` paths to point to `../app/`

## Step 3: Configure Environment

### 3.1 Create Production .env

```bash
cd ~/domains/yourdomain.com/app
cp .env.production .env
nano .env
```

Update these values:
```env
APP_KEY=                          # Generate with: php artisan key:generate
APP_URL=https://rackaudit.hdsystem.io

DB_DATABASE=u123456789_rackaudit
DB_USERNAME=u123456789_dbuser
DB_PASSWORD=your_secure_password

# Pusher credentials (free tier: https://pusher.com)
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=mt1

VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

### 3.2 Generate Application Key

```bash
php artisan key:generate
```

## Step 4: Install Dependencies & Migrate

```bash
# Install PHP dependencies (no dev)
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate --force

# Seed initial data (if needed)
php artisan db:seed --force

# Cache configuration for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Step 5: Set Directory Permissions

```bash
# Storage and cache directories must be writable
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Create storage link
php artisan storage:link
```

## Step 6: Configure Cron Jobs

Go to hPanel > Advanced > Cron Jobs and add:

### Laravel Scheduler (Required)
```
* * * * * cd ~/domains/yourdomain.com/app && php artisan schedule:run >> /dev/null 2>&1
```

### Queue Worker (For background jobs)
```
* * * * * cd ~/domains/yourdomain.com/app && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

> **Note**: The `--stop-when-empty --max-time=50` flags ensure the worker exits before the next cron run, preventing overlapping processes on shared hosting.

## Step 7: Set Up Pusher (WebSockets)

Since Reverb requires a persistent server process (not possible on shared hosting), use Pusher:

1. Create account at https://pusher.com
2. Create a new Channels app
3. Copy credentials to `.env`:
   - App ID → `PUSHER_APP_ID`
   - Key → `PUSHER_APP_KEY`
   - Secret → `PUSHER_APP_SECRET`
   - Cluster → `PUSHER_APP_CLUSTER`

4. The frontend (`echo.ts`) automatically detects Pusher when `VITE_PUSHER_APP_CLUSTER` is set.

## Step 8: SSL Configuration

Hostinger provides free SSL. Enable it:
1. Go to hPanel > Security > SSL
2. Install the free SSL certificate
3. Enable "Force HTTPS" redirect

## Post-Deployment Checklist

- [ ] Application loads without errors
- [ ] Login/authentication works
- [ ] Database operations work (create/read/update/delete)
- [ ] File uploads work (check storage permissions)
- [ ] Email sending works (test password reset)
- [ ] Real-time features work (Pusher WebSockets)
- [ ] Scheduled tasks run (check logs)
- [ ] Queue jobs process (check `jobs` table empties)

## Troubleshooting

### 500 Internal Server Error
```bash
# Check Laravel logs
tail -f storage/logs/laravel.log

# Check PHP error log
tail -f ~/logs/error.log
```

### Permission Denied Errors
```bash
chmod -R 775 storage bootstrap/cache
```

### Assets Not Loading
```bash
# Rebuild frontend
npm run build

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

### Queue Jobs Not Processing
Check cron job is running:
```bash
php artisan queue:work --once
```

## Maintenance Mode

```bash
# Enable maintenance mode
php artisan down --secret="your-bypass-token"

# Access site during maintenance
https://yourdomain.com/your-bypass-token

# Disable maintenance mode
php artisan up
```

## Updating the Application

```bash
# Enable maintenance mode
php artisan down

# Pull latest changes
git pull origin main

# Update dependencies
composer install --no-dev --optimize-autoloader

# Run new migrations
php artisan migrate --force

# Rebuild frontend (locally, then upload public/build)
npm run build

# Clear and rebuild caches
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Disable maintenance mode
php artisan up
```
