# syntax=docker/dockerfile:1

FROM composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer

FROM php:8.4-fpm-alpine@sha256:78cd8de9970a9cd6ff4d98860a94eb5bf37dd2d5776b4785562dbfad01c29d5a AS base

RUN apk add --no-cache icu-libs libzip oniguruma unzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" bcmath intl mbstring pdo_mysql zip opcache \
    && apk del .build-deps

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/merchant-flow.ini
COPY docker/php/entrypoint.sh /usr/local/bin/merchant-flow-entrypoint

ENV COMPOSER_HOME=/tmp/composer
WORKDIR /app
ENTRYPOINT ["/bin/sh", "/usr/local/bin/merchant-flow-entrypoint"]

# Dependencies change far less often than the application, so they get their
# own layer. The Nova credential is mounted for this step only and never
# stored in a layer.
FROM base AS vendor
COPY php/composer.json php/composer.lock ./
RUN --mount=type=secret,id=composer_auth,target=/tmp/composer/auth.json \
    sh -c 'test -s /tmp/composer/auth.json || { echo "Missing Nova credentials: create .secrets/composer-auth.json (see README)." >&2; exit 1; }' \
    && composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

FROM base AS runtime
COPY --chown=www-data:www-data php/ /app/
COPY --from=vendor --chown=www-data:www-data /app/vendor /app/vendor
RUN composer dump-autoload --no-dev --optimize --no-scripts \
    && php artisan package:discover --ansi \
    && php artisan vendor:publish --tag=laravel-assets --force \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache
EXPOSE 9000
CMD ["php-fpm"]

# Test image: dev dependencies plus the shared charge cases from contracts/.
FROM base AS test
COPY php/composer.json php/composer.lock ./
RUN --mount=type=secret,id=composer_auth,target=/tmp/composer/auth.json \
    sh -c 'test -s /tmp/composer/auth.json || { echo "Missing Nova credentials: create .secrets/composer-auth.json (see README)." >&2; exit 1; }' \
    && composer install --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader
COPY php/ /app/
COPY contracts/ /contracts/
RUN touch /app/.env \
    && composer dump-autoload --optimize --no-scripts \
    && php artisan package:discover --ansi \
    && php artisan vendor:publish --tag=laravel-assets --force
ENV CONTRACTS_PATH=/contracts
CMD ["php", "artisan", "test"]
