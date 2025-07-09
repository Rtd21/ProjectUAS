FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y unzip git zip libzip-dev && docker-php-ext-install zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Install PHP dependencies
RUN composer install

# Expose port
EXPOSE 80
