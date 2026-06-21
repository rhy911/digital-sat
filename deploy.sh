#!/bin/bash

# ========================================================
# DIGITAL SAT - PRODUCTION DEPLOYMENT SCRIPT
# ========================================================

# Exit on errors, unset variables, and failed pipelines.
set -Eeuo pipefail

restore_application() {
    php artisan up || true
}

trap restore_application EXIT

echo "🚀 Starting Deployment Process..."

# 1. Pull the latest code
echo "📦 Pulling latest code from GitHub..."
git pull --ff-only origin main

# 2. Install PHP Dependencies (No Dev packages)
echo "🐘 Installing Composer dependencies..."
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# 3. Install NPM Dependencies & Build Assets
echo "📦 Installing NPM dependencies and building assets..."
npm ci
npm run build

# Keep downtime limited to database and cache changes. The EXIT trap restores
# the app even when a later deployment step fails.
php artisan down --retry=60

# 4. Run Migrations (Force bypasses the production prompt)
echo "🗄️ Running database migrations..."
php artisan migrate --force

# 5. Link Storage (Ensures images load correctly)
echo "🔗 Linking storage..."
php artisan storage:link

# 6. Optimize Laravel Caches
echo "⚡ Optimizing application caches..."
php artisan optimize

# 7. Restart Queue Worker
echo "🔄 Restarting Queue Worker..."
# Restart supervisor if you're using it (Uncomment line below if using Supervisor)
# sudo supervisorctl restart all
# Or if using basic restart
php artisan queue:restart

php artisan up
trap - EXIT

echo "✅ Deployment Complete! Digital SAT is live."
