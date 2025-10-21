# Use official PHP 7.4 FPM image as base
FROM php:7.4-fpm

# Set working directory
WORKDIR /var/www/html/webhook

# Avoid interactive prompts during package installation
ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    supervisor \
    less \
    zlib1g-dev \
    libcurl4-gnutls-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libxpm-dev \
    libwebp-dev \
    libicu-dev \
    locales \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Set permissions for Laravel
RUN chown -R www-data:www-data /var/www/html/webhook \
    && chmod -R 755 /var/www/html/webhook

# Copy existing application code
COPY . .

# Expose port 9000 and start PHP-FPM
EXPOSE 9000
CMD ["php-fpm"]
