FROM dunglas/frankenphp:php8.3

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    gd \
    pdo \
    pdo_mysql \
    intl \
    zip \
    pcntl \
    posix

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

RUN composer install

EXPOSE 8000
