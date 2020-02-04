FROM php:7.2-fpm-alpine

RUN \
    apk add --update --no-cache \
        bash \
        bash-completion \
        curl \
        openssl \
        make \
        gnupg \
        unzip \
        wget \
        zlib-dev \
        libzip-dev \
    && docker-php-ext-install zip

RUN \
    curl -sl https://getcomposer.org/composer.phar -o /usr/local/bin/composer; \
    chmod +x /usr/local/bin/composer;

ARG UID
RUN \
    apk add --no-cache --virtual .build-deps shadow; \
    mkdir -p /var/www/html; \
    adduser -D -s /bin/bash -u ${UID} docker; \
    chown -R docker /var/www/html; \
    apk del --no-network .build-deps;

COPY docker/php/.bashrc /home/docker/.bashrc

USER docker

RUN composer global require hirak/prestissimo;

WORKDIR /var/www/html
