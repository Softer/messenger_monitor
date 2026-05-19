FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
        supervisor \
        libzip-dev \
        libsqlite3-dev \
        unzip \
        git \
    && docker-php-ext-install zip pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app/demo

EXPOSE 8080

CMD ["supervisord", "-c", "/app/demo/supervisor/supervisord.conf"]
