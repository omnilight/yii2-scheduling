FROM composer:2 AS composer

FROM php:8.2-cli

RUN apt-get update && \
    apt-get install -y \
        git \
        unzip \
        libonig-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install mbstring

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json .

RUN composer install --prefer-dist --no-interaction --no-ansi

COPY . .
