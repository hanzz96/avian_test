#!/bin/sh
set -e
cd /var/www/html

[ -f .env ] || cp .env.example .env

if [ -z "$APP_KEY" ] && ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
