#!/bin/bash

# ========================================================
# DIGITAL SAT - PRODUCTION DEPLOYMENT SCRIPT
# ========================================================

# Exit immediately if a command exits with a non-zero status
set -e

echo "🚀 Starting Deployment Process..."

# 1. Pull the latest code
echo "📦 Pulling latest code from GitHub..."
git pull origin main

# 2. Install PHP Dependencies (No Dev packages)
echo "🐘 Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader

# 3. Install NPM Dependencies & Build Assets
echo "📦 Installing NPM dependencies and building assets..."
npm install
npm run build

# 4. Run Migrations (Force bypasses the production prompt)
echo "🗄️ Running database migrations..."
php artisan migrate --force

# 5. Link Storage (Ensures images load correctly)
echo "🔗 Linking storage..."
php artisan storage:link

# 6. Optimize Laravel Caches
echo "⚡ Optimizing application caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 7. Restart Queue Worker
echo "🔄 Restarting Queue Worker..."
# Restart supervisor if you're using it (Uncomment line below if using Supervisor)
# sudo supervisorctl restart all
# Or if using basic restart
php artisan queue:restart

echo "✅ Deployment Complete! Digital SAT is live."
