# syntax=docker/dockerfile:1.7

FROM composer:2.8 AS php-dependencies
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts

FROM node:22-alpine AS frontend
WORKDIR /var/www/html
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY . .
RUN npm run build

FROM php:8.5-fpm-alpine AS runtime
WORKDIR /var/www/html
RUN apk add --no-cache icu-libs libpq libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev oniguruma-dev postgresql-dev \
    && docker-php-ext-install -j1 bcmath intl mbstring pdo_pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-kidoki.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-kidoki.conf
COPY --from=php-dependencies /var/www/html/vendor ./vendor
COPY . .
COPY --from=frontend /var/www/html/public/build ./public/build
RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
COPY docker/entrypoint.sh /usr/local/bin/kidoki-entrypoint
RUN chmod 0755 /usr/local/bin/kidoki-entrypoint
USER www-data
ENTRYPOINT ["kidoki-entrypoint"]
CMD ["php-fpm", "-F"]
