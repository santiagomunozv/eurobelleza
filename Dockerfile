FROM php:8.3-fpm as base

ARG user

ARG uid

RUN apt-get update && apt-get install -y curl libpng-dev libonig-dev libxml2-dev unzip libzip-dev libmagickwand-dev default-mysql-client

RUN pecl install imagick

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd xml soap intl zip

RUN docker-php-ext-configure opcache --enable-opcache && docker-php-ext-install opcache

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

RUN apt-get autoclean

RUN apt-get autoremove

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN useradd -G www-data,root -u $uid -d /home/$user $user

RUN mkdir -p /home/$user/.composer && chown -R $user:$user /home/$user

WORKDIR /var/www

RUN chown -R $user:$user /var/www

RUN chown -R www-data:www-data /var/www

RUN chmod -R 777 /var/www

EXPOSE 80
