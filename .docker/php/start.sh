#!/bin/sh
set -eu

composer install --no-interaction --prefer-dist

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -Eq '^APP_KEY=base64:.+' .env; then
    php artisan key:generate --force
fi

php artisan migrate --force
php artisan smartwallet:generate-recurrences --recovery
php artisan serve --host=0.0.0.0 --port=8000
