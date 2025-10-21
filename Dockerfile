# Use PHP 8.3 FPM base image
FROM php:8.3-fpm

# Arguments for non-root user
ARG USER=babu
ARG UID=1000
ARG GID=1000

# Create non-root user
RUN groupadd -g ${GID} ${USER} && \
    useradd -u ${UID} -g ${GID} -m ${USER}

# Set working directory
WORKDIR /var/www/html/webhook

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
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy Supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf

# Ensure proper permissions for Laravel storage & cache
RUN mkdir -p storage bootstrap/cache \
    && chown -R ${USER}:${USER} storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Switch to non-root user
USER ${USER}

# Expose ports
EXPOSE 9000 8080

# Start Supervisor
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf", "-n"]
