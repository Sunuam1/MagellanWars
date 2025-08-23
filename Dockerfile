# Multi-stage build for MagellanWars
# Use specific builder stage for C++ compilation
FROM ubuntu:20.04 AS builder

# Prevent timezone prompts
ENV DEBIAN_FRONTEND=noninteractive

# Install build dependencies with proper package names
RUN apt-get update && apt-get install -y \
    build-essential \
    g++ \
    make \
    default-libmysqlclient-dev \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Copy source code
WORKDIR /build
COPY src/ /build/src/

# Build the game server
WORKDIR /build/src/apps/archspace
RUN make clean && make

# Production image
FROM php:7.4-apache

# Install runtime dependencies with correct package names
RUN apt-get update && apt-get install -y \
    default-libmysqlclient-dev \
    libzip-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite

# Copy game server binary from builder
COPY --from=builder /build/src/apps/archspace/archspace /usr/local/bin/archspace
RUN chmod +x /usr/local/bin/archspace

# Copy web files
COPY src/web/ /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Configure Apache for Railway
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Create startup script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Railway uses PORT environment variable
ENV PORT=80
EXPOSE 80

# Start services
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]