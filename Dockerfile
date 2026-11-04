# Skuul V3 — Render Docker deployment

# Install PHP packages first because Vite imports Livewire and April UI from vendor/.
FROM composer:2 AS vendor-build
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress \
      --no-scripts --optimize-autoloader --ignore-platform-reqs

FROM node:24-bookworm-slim AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY --from=vendor-build /app/vendor ./vendor
COPY . .
RUN npm run build

FROM composer:2 AS composer-bin

FROM php:8.5-apache-bookworm
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public COMPOSER_ALLOW_SUPERUSER=1 PORT=10000

RUN apt-get update && apt-get install -y --no-install-recommends \
      git unzip zip libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev \
      libfreetype6-dev libonig-dev libxml2-dev libcurl4-openssl-dev \
      libssl-dev supervisor gettext-base default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath exif gd intl mbstring \
         pcntl pdo_mysql xml zip opcache \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer-bin /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
COPY --from=frontend /app/public/build ./public/build

# Add the S3-compatible Flysystem adapter for Backblaze B2.
RUN composer require league/flysystem-aws-s3-v3:^3.0 predis/predis:^3.0 \
      --no-interaction --no-progress --no-scripts --no-install \
    && composer install --no-dev --prefer-dist --no-interaction \
      --no-progress --optimize-autoloader --no-scripts \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/ports.conf.template /etc/apache2/ports.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/skuul.conf
COPY docker/start.sh /usr/local/bin/skuul-start
RUN chmod +x /usr/local/bin/skuul-start

EXPOSE 10000
ENTRYPOINT ["/usr/local/bin/skuul-start"]
