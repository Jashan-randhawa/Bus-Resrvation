#!/bin/sh
set -e

# Conditionally run database migrations if configured and enabled (Issue 17)
if [ "${MIGRATE_ON_START:-1}" = "1" ] && { [ -n "$DB_HOST" ] || [ -n "$DATABASE_URL" ]; }; then
    echo "[busres] Running database migrations (MIGRATE_ON_START=1)..."
    if php /var/www/html/database/db_migrate.php; then
        echo "[busres] Database migrations completed successfully (exit code 0)."
    else
        EXIT_CODE=$?
        echo "[busres] WARNING: Database migrations failed with exit code ${EXIT_CODE}. Server will continue starting."
    fi
else
    echo "[busres] Startup migrations skipped (MIGRATE_ON_START != 1 or DB not configured)."
fi

exec "$@"
