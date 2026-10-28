# GIYA - production image for Vercel (Container Runtime) and Render.
#
# FrankenPHP = PHP + the Caddy web server in one binary, so there is no
# separate nginx/php-fpm to wire together. The same image runs on both hosts;
# see DEPLOY.md.

FROM dunglas/frankenphp:1-php8.3

# PHP extensions GIYA needs: PostgreSQL (Neon), GD (photos), plus the usual
# Laravel set. install-php-extensions ships with the FrankenPHP image.
RUN install-php-extensions pdo_pgsql pgsql gd intl zip bcmath opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencies first, so editing app code does not re-download vendor/.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .

RUN composer dump-autoload --optimize --no-dev \
 && php artisan package:discover --ansi \
 && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache \
 && chmod -R ug+rwX storage bootstrap/cache \
 && chmod +x docker/start.sh

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-giya.ini

# Safe production defaults. Everything secret (APP_KEY, DB_*, keys) is set in
# the host's dashboard, never baked into the image.
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    TRUSTED_PROXIES=* \
    FORCE_HTTPS=true \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync \
    MAIL_MAILER=log

# Vercel sends traffic to port 80 unless PORT is set; Render sets PORT itself.
EXPOSE 80

CMD ["/app/docker/start.sh"]
