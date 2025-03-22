#!/usr/bin/env bash

echo "Setting up Laravel application..."

# Clear and cache Laravel configurations
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "Caching configurations..."
php artisan config:cache
php artisan route:cache

echo "Running migrations..."
php artisan migrate --force

echo "Deployment complete!"