#!/bin/sh
set -eu

until [ -f /var/www/html/vendor/autoload.php ] && [ -f /var/www/html/.env ]; do
    sleep 2
done

php artisan schedule:work
