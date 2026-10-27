# One image runs every backend role: the HTTP API (FrankenPHP), the queue
# worker, the scheduler and the Reverb WebSocket server. docker-compose.yml
# picks the role through the container command.
FROM dunglas/frankenphp:1-php8.3-alpine

RUN install-php-extensions \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        redis \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-app.ini

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Plain HTTP inside the network; TLS terminates at the Caddy edge proxy.
ENV SERVER_NAME=:8000

COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

EXPOSE 8000
ENTRYPOINT ["app-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
