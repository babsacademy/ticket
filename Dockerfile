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
# Based on the PHP image (not a bare node image): the Wayfinder Vite plugin
# shells out to `php artisan wayfinder:generate` during `vite build` to
# (re)generate resources/js/actions & resources/js/routes from the app's
# actual routes, so a working `artisan` + vendor/ must be present here too.
#
FROM php:8.3-cli-alpine AS node-builder
RUN apk add --no-cache nodejs npm
WORKDIR /app

# Full app + installed vendor/ from stage 1 (needed for `artisan` to boot).
COPY --from=composer-builder /app ./

RUN npm ci
RUN npm run build

#
# ---- Stage 3: runtime image (PHP-FPM + Nginx) ----
#
FROM php:8.3-fpm-alpine AS runtime

RUN apk add --no-cache \
        nginx \
        bash \
        gettext \
        icu-libs \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        oniguruma \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        bcmath \
        intl \
        zip \
        gd \
        pcntl \
        opcache \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/*

WORKDIR /var/www/html

# Application code (respects .dockerignore: no .env, node_modules, vendor,
# tests, or local git metadata end up in the image).
COPY . .

# Vendored PHP dependencies and built frontend assets from the earlier
# stages (both already contain everything `composer install`/`vite build`
# produced — this just replaces the dev-time vendor/public/build with the
# production ones).
COPY --from=composer-builder /app/vendor ./vendor
COPY --from=node-builder /app/public/build ./public/build

# Nginx/PHP-FPM/PHP configuration.
COPY docker/nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY deploy.sh /usr/local/bin/deploy.sh
RUN chmod +x /usr/local/bin/deploy.sh

# The public storage symlink is a static filesystem operation (no runtime
# env needed), so it's safe — and faster — to create it once at build time.
RUN php artisan storage:link \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8080

CMD ["/usr/local/bin/deploy.sh"]
