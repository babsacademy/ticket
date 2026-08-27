#!/usr/bin/env sh
# Startup entrypoint for the "web" Railway service. Runs once per container
# start (i.e. on every deploy, and on every restart): applies pending
# migrations, rebuilds the config/route/view caches against the runtime
# environment Railway just injected, then hands off to PHP-FPM + Nginx.
#
# This deliberately does NOT run `php artisan key:generate` — APP_KEY must
# be set once as a Railway variable and stay stable, or every deploy would
# invalidate existing sessions and encrypted data.
#
# The worker service does not use this script: its Railway config
# (railway.worker.toml) overrides the start command directly to
# `php artisan queue:work`, bypassing Nginx/PHP-FPM entirely.
set -e

cd /var/www/html

echo "[deploy] Substituting \$PORT into the Nginx config..."
export PORT="${PORT:-8080}"
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

echo "[deploy] Running database migrations..."
php artisan migrate --force

echo "[deploy] Caching configuration and views..."
php artisan config:cache
php artisan view:cache
# Deliberately no `route:cache`: routes/api.php's stock `GET /user` route
# (from `install:api`) is a Closure, and `route:cache` hard-fails on any
# closure-based route — caching it would break every deploy. Route
# resolution without the cache is still fast at this app's route count.

echo "[deploy] Starting PHP-FPM..."
php-fpm -D

echo "[deploy] Starting Nginx on port ${PORT}..."
exec nginx -g "daemon off;"
