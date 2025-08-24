# Ultra-light Dockerfile for Railway deployment
FROM php:7.4-apache-buster

# Install only the MySQL extension without extra packages
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy web files
COPY src/web/ /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Configure Apache
RUN a2enmod rewrite && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Railway uses PORT environment variable
ENV APACHE_DOCUMENT_ROOT /var/www/html
ENV PORT 80

EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]