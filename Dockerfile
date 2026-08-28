# syntax=docker/dockerfile:1

#
# ---- Stage 1: PHP dependencies (Composer, no dev packages) ----
#
FROM composer:2 AS composer-builder
WORKDIR /app

COPY composer.json composer.lock ./
# `composer install`'s post-autoload-dump scripts (package:discover) need
# the full application present, not just composer.json.
COPY . .

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-ansi \
        --prefer-dist \
        --optimize-autoloader \
    && composer clear-cache

#
# ---- Stage 2: frontend build (Vite / React / Tailwind) ----
#
# Based on a PHP image (not a bare node image): the Wayfinder Vite plugin
# shells out to `php artisan wayfinder:generate` during `vite build` to
# (re)generate resources/js/actions & resources/js/routes from the app's
# actual routes, so a working `artisan` must be present here too.
#
FROM php:8.3-cli-alpine AS node-builder
RUN apk add --no-cache nodejs npm oniguruma \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" mbstring \
    && apk del .build-deps
WORKDIR /app

# Full app + installed vendor/ from stage 1 (needed for `artisan` to boot).
COPY --from=composer-builder /app ./

RUN npm ci
RUN npm run build

#
# ---- Stage 3: runtime image (serversideup/php: PHP-FPM + Nginx + S6) ----
#
# serversideup/php:8.3-fpm-nginx ships PHP-FPM, Nginx, and S6 Overlay process
# supervision in one image, with a set of PHP extensions already precompiled:
#   - their own additions: opcache, pcntl, pdo_mysql, pdo_pgsql, redis, zip
#   - bundled by the official upstream php images: ctype, curl, dom,
#     fileinfo, filter, hash, mbstring, openssl, pcre, session, tokenizer, xml
# (confirmed against serversideup's "Default Configurations" docs). That
# already covers everything this app needs — mbstring, pdo_mysql, pcntl —
# EXCEPT `gd` (used by endroid/qr-code to rasterize ticket QR codes), which
# is not in their default set and is the only extension installed below.
#
FROM serversideup/php:8.3-fpm-nginx AS runtime

# Images are unprivileged by default (run as www-data) — switch to root to
# install the extension and set file ownership, then drop back down at the
# end, per serversideup's documented convention.
USER root

RUN install-php-extensions gd

WORKDIR /var/www/html

# Application code, vendored PHP dependencies, and built frontend assets —
# owned by www-data from the start (COPY --chown) rather than a separate
# chown pass. .dockerignore keeps .env, node_modules, vendor, tests, and
# local git metadata out of the build context.
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=composer-builder /app/vendor ./vendor
COPY --chown=www-data:www-data --from=node-builder /app/public/build ./public/build

# The public storage symlink is a static filesystem operation (no runtime
# env needed), so it's safe — and faster — to create it once at build time.
# Still running as root here, so fix ownership on what storage:link and
# these directories create.
RUN php artisan storage:link \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public/storage \
    && chmod -R 775 storage bootstrap/cache

# Custom startup step: config/view cache, run once per container start via
# serversideup's entrypoint.d mechanism (executes before the image's own CMD
# — nginx+php-fpm for the web service, `php artisan queue:work` for the
# worker service, see railway.toml / railway.worker.toml). See the script
# itself for why `migrate` and `route:cache` are deliberately NOT in it.
COPY docker/entrypoint.d/40-app-deploy.sh /etc/entrypoint.d/40-app-deploy.sh
RUN chmod 755 /etc/entrypoint.d/40-app-deploy.sh

# OPcache ships disabled by default on this image; this is the sane
# production default. Override with PHP_OPCACHE_ENABLE=0 at runtime (e.g.
# Railway service variable) if it's ever needed for debugging.
ENV PHP_OPCACHE_ENABLE=1

# Drop back to the unprivileged user for runtime.
USER www-data

EXPOSE 8080 8443
