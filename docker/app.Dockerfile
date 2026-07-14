# =============================================================
# Stage 1: Build Frontend (Vue.js)
# =============================================================
FROM node:22-alpine AS frontend-builder

WORKDIR /app/frontend

# Copy package files first to leverage Docker layer caching.
# npm install only re-runs when package.json/lock changes.
COPY frontend/package*.json ./
RUN npm ci

COPY frontend/ ./

# Accept VITE_API_URL as a build arg (defaults to /api for same-origin monolith)
ARG VITE_API_URL=/api
ENV VITE_API_URL=$VITE_API_URL

RUN npm run build

# =============================================================
# Stage 2: PHP/Laravel + Nginx + Supervisor (production image)
# =============================================================
FROM php:8.2-fpm-alpine AS app

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    libxml2-dev \
    oniguruma-dev \
    zip \
    unzip \
    git \
    mysql-client

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        gd \
        bcmath \
        mbstring \
        zip \
        opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure PHP for uploads
RUN echo "upload_max_filesize = 20M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 20M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Set working directory
WORKDIR /var/www/html

# Copy composer files first to cache vendor install layer
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy the rest of the application
COPY . .

# Run post-install scripts now that full app is present
RUN composer run-script post-autoload-dump 2>/dev/null || true

# Copy built frontend assets into Laravel public directory
COPY --from=frontend-builder /app/frontend/dist ./public/

# Copy Docker support files
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
