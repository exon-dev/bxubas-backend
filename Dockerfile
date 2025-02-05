FROM php:8.3.10

# Install necessary dependencies and MySQL client libraries
RUN apt-get update -y && apt-get install -y \
    openssl \
    zip \
    unzip \
    git \
    default-mysql-client \
    libmariadb-dev \
    && docker-php-ext-install pdo pdo_mysql

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /app

# Copy project files
COPY . /app

# Set up Laravel environment
RUN cp .env.example .env
RUN composer install
RUN php artisan config:clear && php artisan cache:clear

# Expose port 8000 and run Laravel server
EXPOSE 8000
CMD php artisan serve --host=0.0.0.0 --port=8000
