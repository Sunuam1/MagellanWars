#!/bin/bash

# Set the PORT for Apache from Railway environment
if [ -z "$PORT" ]; then
    PORT=80
fi

echo "Starting Apache on port $PORT"

# Update Apache configuration to use Railway's PORT
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/:80>/:$PORT>/g" /etc/apache2/sites-enabled/000-default.conf

# Start Apache in foreground
exec apache2-foreground