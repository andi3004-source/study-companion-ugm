# Production image for docker-compose.yml (web + queue worker share it).
# Local development uses Dockerfile.dev / docker-compose.dev.yml instead.

# 1. Front-end assets (Vite). Only what the build needs is copied.
FROM node:22-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
RUN npm run build

# 2. PHP dependencies without dev packages.
FROM composer:2 AS vendor
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

# 3. Runtime: FrankenPHP (Caddy + PHP) serving plain HTTP on :80.
#    TLS is done by the separate "proxy" service (Caddy) in docker-compose.yml.
FROM dunglas/frankenphp:1-php8.3-alpine

RUN apk add --no-cache ffmpeg && install-php-extensions pcntl opcache

ENV APP_DATA=/data

WORKDIR /var/www/html

COPY --from=vendor /build /var/www/html
COPY --from=assets /build/public/build /var/www/html/public/build
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/frankenphp.Caddyfile /etc/app/Caddyfile

# storage/ lives on the shared volume (/data/storage). The image keeps a template
# (empty framework dirs) and the committed seed data, copied in by the entrypoint.
RUN chmod +x /usr/local/bin/app-entrypoint \
    && mv storage /opt/storage-template \
    && ln -s /data/storage storage \
    && rm -f bootstrap/cache/*.php \
    && date -u +%Y%m%d%H%M%S > /opt/build-id

EXPOSE 80

ENTRYPOINT ["app-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/app/Caddyfile"]
