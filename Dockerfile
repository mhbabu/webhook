# Use PHP 8.2 to satisfy Laravel 12+ requirements
FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    build-essential \
    git \
    cron \
    curl \
    unzip \
    supervisor \
    nano \
    libgd-dev \
    libpng-dev \
    libjpeg-dev \
    libzip-dev \
    libbsd-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    libicu-dev \
    zlib1g-dev \
    librdkafka-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        gd \
        zip \
        bcmath \
        pcntl \
        mbstring \
        xml \
        intl \
        opcache \
        curl \
        sockets \
    && pecl install redis rdkafka \
    && docker-php-ext-enable redis rdkafka

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

# Copy project files
COPY --chown=root:root . /var/www/html

# Let Git trust the repo
RUN git config --global --add safe.directory /var/www/html

# Set permissions
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Install PHP dependencies (production)
RUN composer install --no-dev --optimize-autoloader

# Supervisor config
COPY ./docker/supervisor/* /etc/supervisor/conf.d/

# Set user
USER root

# Cron setup
RUN echo "* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/webhook-cron \
    && chmod 0644 /etc/cron.d/webhook-cron \
    && crontab /etc/cron.d/webhook-cron

# Optional ports
# EXPOSE 9000
# EXPOSE 6001

# Optional supervisor startup
# CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
