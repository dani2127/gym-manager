#!/bin/sh
set -e

# Only the HTTP container runs migrations and warms caches, so the worker,
# scheduler and Reverb containers never race each other on startup.
if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
    php artisan storage:link --quiet 2>/dev/null || true
    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        php artisan migrate --force --no-interaction
    fi
    php artisan optimize
fi

exec "$@"
