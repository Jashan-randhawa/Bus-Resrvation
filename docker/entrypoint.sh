#!/bin/sh
set -e

# Automatically run database migrations if DB is configured
if [ -n "$DB_HOST" ]; then
    echo "[busres] Running database migrations..."
    php /var/www/html/database/db_migrate.php || echo "[busres] Migrations deferred or failed; server will continue starting."
fi

exec "$@"
