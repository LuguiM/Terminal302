#!/bin/sh
set -eu

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

su -s /bin/sh www-data -c "php artisan config:cache"
su -s /bin/sh www-data -c "php artisan view:cache"

if [ "$#" -gt 0 ] && [ "$1" != "serve" ]; then
    exec "$@"
fi

php-fpm -D
exec nginx -g "daemon off;"
