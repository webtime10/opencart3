FROM php:8.2-apache

# Включаем mod_rewrite для Apache (нужно для ЧПУ в OpenCart)
RUN a2enmod rewrite

# Устанавливаем системные зависимости и необходимые для OpenCart расширения PHP
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mysqli pdo_mysql zip

# Выставляем правильные права на директорию
RUN chown -R www-data:www-data /var/www/html