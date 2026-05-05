FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
  git unzip libzip-dev libpng-dev libonig-dev libbrotli-dev \
  && docker-php-ext-install pdo_mysql mbstring zip bcmath pcntl \
  && pecl install swoole-5.1.3 \
  && docker-php-ext-enable swoole \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . /var/www