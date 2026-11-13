# syntax=docker/dockerfile:1
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# IRon Table - one image, three roles (app, reverb, worker).
# The role is the first argument to the entrypoint (see docker/entrypoint.sh).
#
# Build:  docker compose build
# Needs:  a bootstrapped Laravel application in the repository root
#         (run scripts/bootstrap-laravel.sh one time first).

# Image versions are pinned in the FROM lines. Change them together with
# scripts/bootstrap-laravel.sh (COMPOSER_IMAGE, NODE_IMAGE).

# ---------------------------------------------------------------------------
# Stage 1: PHP dependencies (no dev packages)
# ---------------------------------------------------------------------------
FROM composer:2.8.12 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# The composer image does not have the runtime PHP extensions (pdo_pgsql,
# pcntl, ...). The runtime stage has them, so platform checks are skipped here.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist \
        --ignore-platform-reqs
COPY . .
RUN composer dump-autoload \
        --no-dev \
        --no-scripts \
        --optimize \
        --classmap-authoritative

# ---------------------------------------------------------------------------
# Stage 2: front-end assets (Vite build)
# ---------------------------------------------------------------------------
FROM node:22.22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
# Tailwind scans some Blade views inside vendor/.
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 3: runtime
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1.13.4-php8.4-bookworm AS runtime

ARG APP_UID=10001
ARG APP_GID=10001

# curl is for the container health checks. The base image tag pins the
# Debian release, so the curl package version is not pinned here.
# hadolint ignore=DL3008
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions pdo_pgsql intl zip opcache pcntl bcmath redis \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/iron-table.ini "$PHP_INI_DIR/conf.d/zz-iron-table.ini"

WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/iron-table-entrypoint

# 1. Composer scripts were skipped in stage 1, so build the package manifest.
# 2. Make the non-root user. Only storage/, bootstrap/cache/ and the Caddy
#    data and config folders are writable by it.
RUN php artisan package:discover --ansi \
    && groupadd --gid "${APP_GID}" app \
    && useradd --uid "${APP_UID}" --gid app --no-create-home --shell /usr/sbin/nologin app \
    && mkdir -p storage/framework/cache storage/framework/sessions \
                storage/framework/views storage/logs storage/app/private bootstrap/cache \
    && chown -R app:app storage bootstrap/cache /data/caddy /config/caddy \
    && chmod 0755 /usr/local/bin/iron-table-entrypoint

USER app

# Port 8080 so that the non-root user can bind it. TLS is set at the
# reverse proxy, or by FrankenPHP with a Caddyfile (planned, see docs).
EXPOSE 8080

# The base image checks the Caddy admin port (2019). Only the "app" role runs
# Caddy, so that check fails for "reverb" and "worker". Each role sets its own
# health check in compose.yaml.
HEALTHCHECK NONE

ENTRYPOINT ["iron-table-entrypoint"]
CMD ["app"]
