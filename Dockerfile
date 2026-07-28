FROM php:8.2-fpm-alpine AS base

RUN apk add --no-cache \
    nginx \
    supervisor \
    postgresql-dev \
    icu-dev \
    oniguruma-dev \
    libzip-dev \
    autoconf \
    build-base \
 && docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    intl \
    mbstring \
    bcmath \
    opcache \
    zip \
    sockets \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del autoconf build-base

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload --optimize --no-dev \
 && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
