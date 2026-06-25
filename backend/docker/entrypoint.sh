#!/bin/sh
set -eu

if [ ! -s .env ]; then
    {
        echo "APP_NAME=\"${APP_NAME:-Terminal302}\""
        echo "APP_ENV=${APP_ENV:-local}"
        echo "APP_KEY=${APP_KEY:-}"
        echo "APP_DEBUG=${APP_DEBUG:-true}"
        echo "APP_URL=${APP_URL:-http://localhost:8000}"
        echo
        echo "APP_LOCALE=${APP_LOCALE:-en}"
        echo "APP_FALLBACK_LOCALE=${APP_FALLBACK_LOCALE:-en}"
        echo "APP_FAKER_LOCALE=${APP_FAKER_LOCALE:-en_US}"
        echo
        echo "LOG_CHANNEL=${LOG_CHANNEL:-stack}"
        echo "LOG_STACK=${LOG_STACK:-single}"
        echo "LOG_LEVEL=${LOG_LEVEL:-debug}"
        echo
        echo "DB_CONNECTION=${DB_CONNECTION:-pgsql}"
        echo "DB_HOST=${DB_HOST:-postgres}"
        echo "DB_PORT=${DB_PORT:-5432}"
        echo "DB_DATABASE=${DB_DATABASE:-terminal302}"
        echo "DB_USERNAME=${DB_USERNAME:-terminal302}"
        echo "DB_PASSWORD=${DB_PASSWORD:-}"
        echo
        echo "INITIAL_ADMIN_NAME=\"${INITIAL_ADMIN_NAME:-Administrador Terminal302}\""
        echo "INITIAL_ADMIN_EMAIL=${INITIAL_ADMIN_EMAIL:-admin@terminal302.local}"
        echo
        echo "SESSION_DRIVER=${SESSION_DRIVER:-database}"
        echo "SESSION_LIFETIME=${SESSION_LIFETIME:-120}"
        echo "SESSION_ENCRYPT=${SESSION_ENCRYPT:-false}"
        echo "SESSION_PATH=${SESSION_PATH:-/}"
        echo "SESSION_DOMAIN=${SESSION_DOMAIN:-null}"
        echo
        echo "CACHE_STORE=${CACHE_STORE:-database}"
        echo "QUEUE_CONNECTION=${QUEUE_CONNECTION:-database}"
        echo "FILESYSTEM_DISK=${FILESYSTEM_DISK:-local}"
        echo
        echo "MAIL_MAILER=${MAIL_MAILER:-log}"
        echo "MAIL_SCHEME=${MAIL_SCHEME:-null}"
        echo "MAIL_HOST=${MAIL_HOST:-127.0.0.1}"
        echo "MAIL_PORT=${MAIL_PORT:-2525}"
        echo "MAIL_USERNAME=${MAIL_USERNAME:-null}"
        echo "MAIL_PASSWORD=${MAIL_PASSWORD:-null}"
        echo "MAIL_FROM_ADDRESS=${MAIL_FROM_ADDRESS:-hello@example.com}"
        echo "MAIL_FROM_NAME=\"${MAIL_FROM_NAME:-Terminal302}\""
    } > .env
fi

exec "$@"
