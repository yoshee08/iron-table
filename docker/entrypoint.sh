#!/bin/sh
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# IRon Table container entrypoint.
# Usage: iron-table-entrypoint <role>
#   app     web application (FrankenPHP), runs database migrations first
#   reverb  live updates (WebSocket server)
#   worker  queue worker (imports, exports, AI requests)
# Any other value is run as a command, for example: iron-table-entrypoint php artisan about

set -eu

role="${1:-app}"
cd /app

if [ -z "${APP_KEY:-}" ]; then
    echo "iron-table: APP_KEY is not set. Generate one and put it in .env (see docs/development.md)." >&2
    exit 1
fi

cache_config() {
    php artisan config:cache --no-interaction
    php artisan route:cache --no-interaction
    php artisan view:cache --no-interaction
    php artisan event:cache --no-interaction
}

case "$role" in
    app)
        if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
            php artisan migrate --force --no-interaction
        fi
        cache_config
        # Creates the first administrator only when no user exists (scripts/setup.sh
        # writes INITIAL_ADMIN_*). Invalid settings stop the start with an error.
        php artisan iron-table:first-admin --no-interaction
        # No storage:link. Uploaded IR plans are private files and are never
        # served from public/ (spec SEC-04, SEC-09).
        # docker/Caddyfile: PHP app and the /app/* WebSocket path to Reverb, one port.
        exec frankenphp run --config /etc/iron-table/Caddyfile
        ;;
    reverb)
        cache_config
        exec php artisan reverb:start --host=0.0.0.0 --port="${REVERB_SERVER_PORT:-8080}" --no-interaction
        ;;
    worker)
        cache_config
        exec php artisan queue:work --tries=3 --max-time=3600 --no-interaction
        ;;
    *)
        exec "$@"
        ;;
esac
