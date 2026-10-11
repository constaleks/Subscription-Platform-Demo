FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        bcmath \
        pcntl \
        intl \
        opcache \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# Containers run as the host UID, which has no home directory in this image
ENV COMPOSER_HOME=/tmp/composer

COPY _docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini

WORKDIR /var/www/backend

EXPOSE 9000

CMD ["php-fpm"]
