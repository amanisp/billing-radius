FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git curl zip unzip supervisor libpng-dev libonig-dev libxml2-dev \
    libzip-dev default-mysql-client cron bash tzdata \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY ./docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY ./docker/supervisor/horizon.conf /etc/supervisor/conf.d/horizon.conf
COPY ./docker/cron/laravel /etc/cron.d/laravel

RUN chmod 0644 /etc/cron.d/laravel && crontab /etc/cron.d/laravel

ENV TZ=Asia/Jakarta

CMD service cron start && php-fpm
