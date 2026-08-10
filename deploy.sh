#!/bin/bash

# ========================================================
# DIGITAL SAT - PRODUCTION DEPLOYMENT SCRIPT
# ========================================================

# Exit on errors, unset variables, and failed pipelines.
set -Eeuo pipefail

# The `php` on PATH for this cPanel account is older than 8.2 and cannot boot
# Laravel 12 (it fails on ReflectionFunction::isAnonymous). Every artisan call
# below must use the EA-PHP 8.2 binary explicitly, or the deploy silently does
# nothing while still reporting success. Override with PHP_BIN=... if the host
# path ever changes.
PHP_BIN="${PHP_BIN:-/opt/cpanel/ea-php82/root/usr/bin/php}"

if [ ! -x "$PHP_BIN" ]; then
    echo "❌ PHP binary not found or not executable: $PHP_BIN"
    exit 1
fi

if ! "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.2", ">=") ? 0 : 1);'; then
    echo "❌ $PHP_BIN is $("$PHP_BIN" -r 'echo PHP_VERSION;') — Laravel 12 requires PHP >= 8.2"
    exit 1
fi

echo "🐘 Using PHP: $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"

# Composer resolves `php` from PATH via its own shebang/wrapper, so pointing
# PATH at the 8.2 binary fixes composer without guessing whether the `composer`
# on this host is a phar or a shell wrapper.
export PATH="$(dirname "$PHP_BIN"):$PATH"

restore_application() {
    "$PHP_BIN" artisan up || true
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
"$PHP_BIN" artisan down --retry=60

# 4. Run Migrations (Force bypasses the production prompt)
echo "🗄️ Running database migrations..."
"$PHP_BIN" artisan migrate --force

# 5. Link Storage (Ensures images load correctly)
echo "🔗 Linking storage..."
"$PHP_BIN" artisan storage:link

# 6. Optimize Laravel Caches
echo "⚡ Optimizing application caches..."
"$PHP_BIN" artisan optimize

# 7. Restart Queue Worker
echo "🔄 Restarting Queue Worker..."
# Restart supervisor if you're using it (Uncomment line below if using Supervisor)
# sudo supervisorctl restart all
# Or if using basic restart. The cron-driven flock workers pick the signal up
# and are relaunched within a minute by their next cron tick.
"$PHP_BIN" artisan queue:restart

"$PHP_BIN" artisan up
trap - EXIT

# The queue worker count has been invisible during past incidents. Print it so a
# deploy can never again leave "is anything processing jobs?" unanswered.
echo "🔎 Queue workers running: $(ps -eo cmd | grep -c '[a]rtisan queue:work' || true)"
echo "🗄️ Migration status:"
"$PHP_BIN" artisan migrate:status | tail -n 15

echo "✅ Deployment Complete! Digital SAT is live."
