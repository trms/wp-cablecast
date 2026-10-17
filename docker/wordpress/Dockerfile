ARG PHP_VERSION=8.2
FROM wordpress:php${PHP_VERSION}-apache

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

COPY xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini
