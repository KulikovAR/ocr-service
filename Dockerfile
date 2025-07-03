FROM php:8.3-fpm

# Установка системных зависимостей и расширений PHP
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        zip \
        unzip \
        git \
        curl \
        libonig-dev \
        libxml2-dev \
        libpq-dev \
        nodejs \
        npm \
        libhiredis-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl bcmath gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Установка Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Копировать исходники (если нужно для сборки)
# COPY . /var/www/html

# Установка nodejs (если нужен более свежий)
# RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
#     && apt-get install -y nodejs

# Открываем порт (опционально)
EXPOSE 9000

CMD ["php-fpm"] 