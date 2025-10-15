FROM php:8.3-fpm

# ===========================
# 1️⃣ Install dependencies
# ===========================
RUN apt-get update && apt-get install -y \
    git curl zip unzip supervisor \
    libpng-dev libonig-dev libxml2-dev libzip-dev libjpeg-dev libfreetype6-dev \
    default-mysql-client cron bash tzdata \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ===========================
# 2️⃣ Install Composer
# ===========================
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ===========================
# 3️⃣ Working Directory
# ===========================
WORKDIR /var/www/html

# ===========================
# 4️⃣ Copy project files
# ===========================
COPY . /var/www/html

# ===========================
# 5️⃣ Supervisor Configuration
# ===========================
COPY ./docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY ./docker/supervisor/horizon.conf /etc/supervisor/conf.d/horizon.conf

# ===========================
# 6️⃣ Timezone & Permissions
# ===========================
ENV TZ=Asia/Jakarta
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

# ===========================
# 7️⃣ Default Command
# ===========================
CMD ["php-fpm"]
