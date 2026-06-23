FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .
COPY docker/entrypoint.sh /usr/local/bin/terminal302-entrypoint
RUN chmod +x /usr/local/bin/terminal302-entrypoint \
    && touch .env
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 8000

ENTRYPOINT ["terminal302-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
