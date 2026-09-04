#!/bin/sh
set -e

# ---------------------------------------------------------------------------
# The SQLite file lives on the Railway volume, NOT in the image. Without a
# volume mounted at /data every deploy would start from an empty scoreboard,
# so this fails loudly rather than quietly losing your games.
# ---------------------------------------------------------------------------
DB_DIR="${DB_DIR:-/data}"
DB_FILE="${DB_DIR}/database.sqlite"

if [ ! -d "$DB_DIR" ]; then
    echo "FATAL: ${DB_DIR} does not exist."
    echo "Add a Railway volume mounted at ${DB_DIR}, or the scores will be wiped on every deploy."
    exit 1
fi

if [ ! -f "$DB_FILE" ]; then
    echo "Creating ${DB_FILE}"
    touch "$DB_FILE"
fi

export DB_CONNECTION=sqlite
export DB_DATABASE="$DB_FILE"

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache || true

php artisan config:clear
php artisan migrate --force

# The seeder is idempotent: it creates the two players and the three starter
# games only if they are missing, and only carries the old spreadsheet games
# across when Rummikub has no results yet.
php artisan db:seed --force

php artisan config:cache
php artisan view:cache
# Deliberately no route:cache - routes/web.php uses closures, which cannot
# be serialised. Caching them would fail the boot.

# Railway injects $PORT. FrankenPHP reads SERVER_NAME for its listen address.
export SERVER_NAME=":${PORT:-8080}"

echo "Scoreboard starting on ${SERVER_NAME}"

exec frankenphp run --config /etc/caddy/Caddyfile
