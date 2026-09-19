FROM php:8.5-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends git libicu-dev libonig-dev libzip-dev unzip && docker-php-ext-install bcmath intl mbstring pdo_mysql zip && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY .docker/php/start.sh /usr/local/bin/smartwallet-start
COPY .docker/php/scheduler-start.sh /usr/local/bin/smartwallet-scheduler-start

RUN chmod +x /usr/local/bin/smartwallet-start /usr/local/bin/smartwallet-scheduler-start

WORKDIR /var/www/html

EXPOSE 8000
