FROM php:8.3-fpm

WORKDIR /var/www/html/webhook

# Set non-interactive mode for apt
ENV DEBIAN_FRONTEND=noninteractive

# Install required system dependencies (openssh-client removed)
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    zip unzip \
    libzip-dev \
    libpng-dev libpng-tools \
    libonig-dev \
    libxml2-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Redis PHP extension
RUN pecl install redis && docker-php-ext-enable redis

# Copy Composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application code
COPY . .

# Set permissions for storage and cache
RUN mkdir -p storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
