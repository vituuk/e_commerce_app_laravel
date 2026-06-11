FROM richarvey/nginx-php-fpm:3.1.6

# Set working directory
WORKDIR /var/www/html

# Install PostgreSQL PHP extensions
RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo_pgsql pgsql

# Copy project files
COPY . .

# Image configuration for richarvey/nginx-php-fpm
ENV SKIP_COMPOSER 1
ENV WEBROOT /var/www/html/public
ENV PHP_ERRORS_STDERR 1
ENV RUN_SCRIPTS 1
ENV REAL_IP_HEADER 1
ENV PHP_CATCHALL 1

# Laravel configuration defaults
ENV APP_ENV production
ENV APP_DEBUG false
ENV LOG_CHANNEL stderr

# Allow Composer to run as root
ENV COMPOSER_ALLOW_SUPERUSER 1

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Expose port (Render automatically maps this, but standardizing on port 80/8080 or exposing is good practice)
EXPOSE 80

# The default start command in the base image is /start.sh, which handles Nginx and PHP-FPM
CMD ["/start.sh"]
