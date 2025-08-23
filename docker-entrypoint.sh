#!/bin/bash

# Update Apache to listen on Railway's PORT
if [ ! -z "$PORT" ]; then
    sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
    sed -i "s/:80>/:$PORT>/g" /etc/apache2/sites-enabled/000-default.conf
fi

# Start game server in background (if database is available)
if [ ! -z "$DATABASE_URL" ] || [ ! -z "$MYSQL_URL" ]; then
    /usr/local/bin/archspace &
fi

# Start Apache in foreground
apache2-foreground