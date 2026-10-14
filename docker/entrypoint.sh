#!/bin/sh
set -e

cd /var/www

# APP_KEY y BACKEND_API_URL llegan como variables de entorno (Render).
if [ -z "$APP_KEY" ]; then
    echo "[entrypoint] ERROR: falta la variable APP_KEY."
    exit 1
fi
if [ -z "$BACKEND_API_URL" ]; then
    echo "[entrypoint] ERROR: falta la variable BACKEND_API_URL (ej. https://<backend>.onrender.com/api)."
    exit 1
fi

# Directorios escribibles que git no versiona.
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

php artisan package:discover --ansi
php artisan config:cache
php artisan view:cache

echo "[entrypoint] Listo. Arrancando: $*"
exec "$@"
