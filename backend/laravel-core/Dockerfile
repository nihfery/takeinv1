FROM php:8.3-fpm

ARG PHPREDIS_VERSION=6.3.0

RUN apt-get update && apt-get upgrade -y && apt-get install -y --no-install-recommends \
    git unzip gzip default-mysql-client \
    libzip-dev libpng-dev libonig-dev libxml2-dev \
    libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo_mysql zip mbstring exif pcntl bcmath gd \
    && pecl install "redis-${PHPREDIS_VERSION}" \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY backend/laravel-core/composer.json backend/laravel-core/composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts

COPY backend/laravel-core/ ./

COPY platform/docker/laravel-entrypoint.sh /usr/local/bin/laravel-entrypoint
COPY platform/docker/php-fpm-production.conf /usr/local/etc/php-fpm.d/zz-youyaku.conf

RUN mkdir -p \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000 8080

CMD ["sh", "/usr/local/bin/laravel-entrypoint"]
