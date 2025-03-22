FROM php:8.3.10-fpm

# Install necessary dependencies and MySQL client libraries
RUN apt-get update -y && apt-get install -y \
    openssl \
    zip \
    unzip \
    git \
    default-mysql-client \
    libmariadb-dev \
    nginx \
    gettext-base \
    && docker-php-ext-install pdo pdo_mysql

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /app

# Copy project files
COPY . /app

# Copy Nginx configuration
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Copy wait-for-db.sh and make it executable
COPY wait-for-db.sh /app/wait-for-db.sh
RUN chmod +x /app/wait-for-db.sh

# Set up Laravel environment
RUN cp .env.example .env

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Add environment variable for PORT with default
ENV PORT=8080

# Expose the port
EXPOSE ${PORT}

# Modified startup sequence
CMD ["/bin/sh", "-c", "\
    envsubst '$$PORT' < /etc/nginx/conf.d/default.conf > /etc/nginx/conf.d/default.conf.tmp && \
    mv /etc/nginx/conf.d/default.conf.tmp /etc/nginx/conf.d/default.conf && \
    service nginx start && \
    /app/wait-for-db.sh && \
    php artisan migrate --force && \
    php-fpm"]
