# MagellanWars Game Server
FROM php:7.4-apache

# Install supervisor and mysql
RUN apt-get update && apt-get install -y supervisor default-mysql-client && \
    docker-php-ext-install mysqli pdo pdo_mysql && \
    rm -rf /var/lib/apt/lists/*

# Configure Apache for port 8080
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf && \
    sed -i 's/:80/:8080/' /etc/apache2/sites-available/000-default.conf

# Copy web files
COPY src/web /var/www/html
COPY src /var/www/src

# Copy turn processor and supervisor config
COPY turn_processor.php /turn_processor.php
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Make executable
RUN chmod +x /turn_processor.php

EXPOSE 8080

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]