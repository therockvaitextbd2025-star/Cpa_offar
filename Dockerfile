
FROM php:8.2-apache

# Enable mod_rewrite
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Permission
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
