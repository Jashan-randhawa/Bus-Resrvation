#!/bin/sh
set -e

# Automatically run database migrations if DB is configured
if [ -n "$DB_HOST" ]; then
    echo "[busres] Running database migrations..."
    if php /var/www/html/database/db_migrate.php; then
        echo "[busres] Database migrations completed successfully (exit code 0)."
    else
        EXIT_CODE=$?
        echo "[busres] WARNING: Database migrations failed with exit code ${EXIT_CODE}. Server will continue starting."
    fi
fi

exec "$@"
