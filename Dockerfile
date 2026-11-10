FROM php:8.3-cli

# Install system dependencies and PHP extensions for SQLite, MySQL, and PostgreSQL
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libsqlite3-dev \
    libpq-dev \
    zip \
    unzip \
    sqlite3 \
    && docker-php-ext-install pdo_mysql pdo_pgsql pdo_sqlite mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files first for layer caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy application source
COPY . .

# Ensure storage and database directories are writable
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs bootstrap/cache database \
    && touch database/database.sqlite \
    && chmod -R 775 storage bootstrap/cache database

EXPOSE 8080

# Run migrations, idempotent seed, storage link, and start the server on Railway's $PORT
CMD sh -c "touch database/database.sqlite && chmod 777 database/database.sqlite && php artisan migrate --force && php artisan db:seed --force || true; php artisan storage:link --force || true; php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"

