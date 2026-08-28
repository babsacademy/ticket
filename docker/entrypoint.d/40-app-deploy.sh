#!/bin/sh
# Custom serversideup/php entrypoint.d script — runs on every container
# start (both the "web" and "worker" Railway services use this same image),
# before the image's own CMD takes over (nginx+php-fpm for web,
# `php artisan queue:work` for the worker). Rebuilds Laravel's config/view
# caches against whatever runtime environment Railway just injected. Safe to
# run on both services: both artisan commands are idempotent and each
# container only needs to warm its own filesystem layer.
#
# Deliberately NOT here:
#   - `php artisan migrate --force`: lives in Railway's `preDeployCommand`
#     instead (see railway.toml), so it runs exactly once per deploy rather
#     than once per container start/restart — avoiding a migration race
#     between the web and worker services starting at the same time.
#   - `php artisan route:cache`: routes/api.php's stock `GET /user` route
#     (added by `install:api`) is a Closure, and route:cache hard-fails on
#     any closure-based route — caching it would break every deploy.
#   - `php artisan key:generate`: APP_KEY must be set once as a Railway
#     variable and stay stable, or every deploy would invalidate existing
#     sessions and encrypted data.
set -e

cd "${APP_BASE_DIR:-/var/www/html}"

echo "[app-deploy] Caching configuration and views..."
php artisan config:cache
php artisan view:cache
