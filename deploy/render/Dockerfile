# syntax=docker/dockerfile:1
FROM php:8.4-fpm-bookworm AS php-base
RUN apt-get update && apt-get install -y --no-install-recommends     libicu-dev libonig-dev libzip-dev unzip ca-certificates     && docker-php-ext-install -j2 bcmath pdo_mysql mbstring intl zip opcache     && rm -rf /var/lib/apt/lists/*
WORKDIR /app

FROM php-base AS dependencies
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader
COPY app app
COPY bootstrap/app.php bootstrap/providers.php bootstrap/
COPY config config
COPY database database
COPY routes routes
COPY artisan ./
RUN mkdir -p bootstrap/cache storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs     && composer dump-autoload --no-dev --classmap-authoritative --no-scripts     && php artisan package:discover --ansi     && composer check-platform-reqs --no-dev

FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources resources
# Tailwind explicitly scans Laravel pagination templates in app.css.
COPY --from=dependencies /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

FROM dependencies AS test
RUN apt-get update && apt-get install -y --no-install-recommends libsqlite3-dev     && docker-php-ext-install pdo_sqlite && rm -rf /var/lib/apt/lists/*
RUN composer install --no-interaction --prefer-dist
COPY tests tests
COPY phpunit.xml ./
COPY resources resources
COPY public public
COPY --from=assets /app/public/build public/build
# An ephemeral test-only key; never used by the production stage.
CMD ["sh", "-c", "export APP_KEY=\"$(php -r 'echo \"base64:\".base64_encode(random_bytes(32));')\"; exec php vendor/bin/phpunit tests/Unit/DatabaseConfigurationTest.php tests/Feature/Auth"]

FROM php-base AS runtime
RUN set -eux; \
    if ! getent group 1000 >/dev/null; then groupadd --gid 1000 render-secrets; fi; \
    usermod --append --groups "$(getent group 1000 | cut -d: -f1)" www-data
RUN apt-get update && apt-get install -y --no-install-recommends nginx curl gettext-base tini     && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default
COPY --from=dependencies /app /app
COPY resources resources
COPY public public
COPY --from=assets /app/public/build /app/public/build
COPY deploy/render/nginx.conf.template /etc/nginx/templates/trackpro.conf.template
COPY deploy/render/php-fpm.conf /usr/local/etc/php-fpm.d/zz-trackpro.conf
COPY deploy/render/php.ini /usr/local/etc/php/conf.d/zz-trackpro.ini
COPY deploy/render/start.sh /usr/local/bin/trackpro-start
RUN chmod 755 /usr/local/bin/trackpro-start     && chown -R www-data:www-data /app/bootstrap/cache /app/storage
ENV PORT=10000 APP_ENV=production APP_DEBUG=false     SESSION_DRIVER=file CACHE_STORE=file QUEUE_CONNECTION=sync     FILESYSTEM_DISK=local LOG_CHANNEL=stderr
EXPOSE 10000
# Override the FPM base image SIGQUIT: the Bash supervisor traps SIGTERM.
STOPSIGNAL SIGTERM
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3     CMD curl --fail --silent --output /dev/null "http://127.0.0.1:${PORT}/up" || exit 1
ENTRYPOINT ["/usr/bin/tini", "--"]
CMD ["/usr/local/bin/trackpro-start"]
