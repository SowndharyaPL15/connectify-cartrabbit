#!/bin/sh

# Set correct permissions
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Wait for MySQL to be ready (optional but recommended in production)
echo "Waiting for database connection..."
sleep 10

# Clear cache and optimize
php artisan optimize:clear
php artisan optimize

# Run database migrations
php artisan migrate --force

# Start PHP-FPM
php-fpm
