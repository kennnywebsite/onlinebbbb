# Use PHP 8.1
FROM php:8.1-apache

# Install System Dependencies
RUN apt-get update && apt-get install -y \
    git unzip libpng-dev libjpeg-dev libfreetype6-dev libbcmath-dev \
    && rm -rf /var/lib/apt/lists/*

# Install Extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd bcmath mysqli pdo_mysql

# Apache Config (Points web root to /public)
RUN a2enmod rewrite
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Copy files
COPY . .

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install Dependencies and run artisan
# We run migrate here. Note: This assumes your DB env variables are 
# set in the Render Dashboard "Environment" tab.
RUN composer install --no-dev --optimize-autoloader --no-scripts --ignore-platform-reqs && \
    php artisan package:discover --ansi && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80