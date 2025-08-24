  # MagellanWars Complete Game Server
  FROM php:7.4-apache

  # Install everything needed
  RUN apt-get update && apt-get install -y supervisor default-mysql-client && \
      docker-php-ext-install mysqli pdo pdo_mysql && \
      rm -rf /var/lib/apt/lists/*

  # Configure Apache for port 8080
  RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf && \
      sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf && \
      sed -i 's/:80/:8080/' /etc/apache2/sites-available/000-default.conf

  # Copy web files
  COPY MagellanWars-main/src/web /var/www/html
  COPY MagellanWars-main/src /var/www/src

  # Create directories
  RUN mkdir -p /var/log/archspace /var/log/supervisor

  # Create a simple turn processor script
  COPY --from=busybox:latest /bin/sh /bin/sh
  RUN echo '<?php while(true){try{$p=new PDO("mysql:host=".getenv("DB_HOST").";dbname=".getenv("DB_NAME"),getenv("D
  B_USER"),getenv("DB_PASSWORD"));$s=$p->query("SELECT MIN(tick) as t,MAX(turn) as n FROM player WHERE game_id>0
  AND game_id!=9999999");$r=$s->fetch();if($r["t"]<=time()){$n=$r["n"]+1;$p->exec("UPDATE player SET
  turn=$n,tick=".(time()+300).",production=production+100 WHERE game_id>0");echo "Turn $n\n";}}catch(Exception
  $e){}sleep(30);}' > /turn.php

  # Create supervisor config
  RUN printf '[supervisord]\nnodaemon=true\n[program:apache2]\ncommand=/usr/sbin/apache2ctl -D
  FOREGROUND\n[program:turns]\ncommand=php /turn.php\nautorestart=true\n' > /etc/supervisor/supervisord.conf

  EXPOSE 8080
  CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]
