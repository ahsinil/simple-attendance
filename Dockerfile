FROM php:8.4-fpm

WORKDIR /var/www/html

# Install system packages required for extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Database Migration
RUN php artisan migrate:fresh --seed --force

# Storage Link
RUN php artisan storage:link

# Optional: Install Composer
# COPY --from=composer:latest /usr/bin/composer /usr/bin/composer