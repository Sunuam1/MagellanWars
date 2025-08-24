# Ultra-light Dockerfile for Railway deployment
FROM php:7.4-apache-buster

# Install only the MySQL extension without extra packages
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy web files
COPY src/web/ /var/www/html/

# Copy startup script
COPY start.sh /start.sh
RUN chmod +x /start.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Configure Apache
RUN a2enmod rewrite && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Railway uses dynamic PORT
EXPOSE ${PORT}

# Start Apache with dynamic port
CMD ["/start.sh"]