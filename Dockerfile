FROM php:8.4-fpm-alpine AS base

RUN apk update && apk upgrade
RUN apk add --no-cache gnupg git zip
RUN apk add --no-cache libzip-dev curl icu-dev
RUN apk add --no-cache freetype-dev libjpeg-turbo-dev libpng-dev
RUN apk add --no-cache openssh libxml2-dev
RUN apk add --no-cache nodejs npm

RUN curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

RUN docker-php-ext-install intl gd zip && docker-php-ext-enable intl gd zip

RUN adduser -D www-root

### Backend prep
FROM base AS backend
WORKDIR /var/www/html
