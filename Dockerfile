# Use official PHP Apache image
FROM php:8.1-apache

# Install required extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Create and initialize all required files
RUN touch /var/www/html/movies.csv \
    && touch /var/www/html/bot.log \
    && echo '{"requests":[],"last_id":0}' > /var/www/html/requests.json \
    && echo '{"users":[],"total_requests":0}' > /var/www/html/users.json

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 666 /var/www/html/movies.csv \
    && chmod 666 /var/www/html/requests.json \
    && chmod 666 /var/www/html/bot.log \
    && chmod 666 /var/www/html/users.json

# Configure PHP
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
