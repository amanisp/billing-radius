FROM php:8.3-fpm

# Install dependencies + cron + bash
RUN apt-get update && apt-get install -y \
    git curl zip unzip supervisor libpng-dev libonig-dev libxml2-dev \
    libzip-dev default-mysql-client cron bash tzdata \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy supervisor config
COPY ./docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY ./docker/supervisor/horizon.conf /etc/supervisor/conf.d/horizon.conf

# Set timezone (ubah sesuai kebutuhan)
ENV TZ=Asia/Jakarta

# Jalankan php-fpm sebagai default CMD
CMD ["php-fpm"]
