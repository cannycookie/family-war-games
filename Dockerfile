# FrankenPHP: one process, serves Laravel directly, no nginx/php-fpm pair
# to keep alive. Good fit for a small always-on Railway service.
FROM dunglas/frankenphp:1-php8.4

WORKDIR /app

RUN install-php-extensions pdo_sqlite intl opcache zip \
    && apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Dependencies first so this layer caches between deploys.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && chmod +x docker/entrypoint.sh

# Railway sets $PORT; FrankenPHP is told about it in the entrypoint.
ENV SERVER_NAME=:8080
EXPOSE 8080

ENTRYPOINT ["docker/entrypoint.sh"]
