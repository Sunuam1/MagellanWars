  # MagellanWars Complete Game Server
  FROM php:7.4-apache

  # Install everything needed
  RUN apt-get update && apt-get install -y \
      supervisor \
      default-mysql-client \
      && docker-php-ext-install mysqli pdo pdo_mysql \
      && rm -rf /var/lib/apt/lists/*

  # Configure Apache for port 8080
  RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf \
      && sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
      && sed -i 's/:80/:8080/' /etc/apache2/sites-available/000-default.conf

  # Copy web files
  COPY MagellanWars-main/src/web /var/www/html
  COPY MagellanWars-main/src /var/www/src

  # Create log directories
  RUN mkdir -p /var/log/archspace /var/log/supervisor

  # Create the turn processor as a separate file
  RUN echo '#!/usr/bin/env php' > /usr/local/bin/turn_processor.php && \
      echo '<?php' >> /usr/local/bin/turn_processor.php && \
      echo 'while (true) {' >> /usr/local/bin/turn_processor.php && \
      echo '  try {' >> /usr/local/bin/turn_processor.php && \
      echo '    $pdo = new PDO("mysql:host=" . getenv("DB_HOST") . ";dbname=" . getenv("DB_NAME"),
  getenv("DB_USER"), getenv("DB_PASSWORD"));' >> /usr/local/bin/turn_processor.php && \
      echo '    $stmt = $pdo->query("SELECT MIN(tick) as next_tick, MAX(turn) as current_turn FROM player WHERE
  game_id > 0 AND game_id != 9999999");' >> /usr/local/bin/turn_processor.php && \
      echo '    $info = $stmt->fetch(PDO::FETCH_ASSOC);' >> /usr/local/bin/turn_processor.php && \
      echo '    if ($info["next_tick"] <= time() || $info["next_tick"] == 0) {' >>
  /usr/local/bin/turn_processor.php && \
      echo '      $newTurn = ($info["current_turn"] ?: 0) + 1;' >> /usr/local/bin/turn_processor.php && \
      echo '      $newTick = time() + 300;' >> /usr/local/bin/turn_processor.php && \
      echo '      echo "[TURNS] Processing turn " . $info["current_turn"] . " to " . $newTurn . "\n";' >>
  /usr/local/bin/turn_processor.php && \
      echo '      $pdo->exec("UPDATE player SET turn = $newTurn, tick = $newTick, production = production + 100,
  research = research + 10 WHERE game_id > 0 AND game_id != 9999999");' >> /usr/local/bin/turn_processor.php && \
      echo '    }' >> /usr/local/bin/turn_processor.php && \
      echo '  } catch (Exception $e) { echo "Error: " . $e->getMessage() . "\n"; }' >>
  /usr/local/bin/turn_processor.php && \
      echo '  sleep(30);' >> /usr/local/bin/turn_processor.php && \
      echo '}' >> /usr/local/bin/turn_processor.php && \
      chmod +x /usr/local/bin/turn_processor.php

  # Create supervisor config
  RUN echo '[supervisord]' > /etc/supervisor/conf.d/supervisord.conf && \
      echo 'nodaemon=true' >> /etc/supervisor/conf.d/supervisord.conf && \
      echo '[program:apache2]' >> /etc/supervisor/conf.d/supervisord.conf && \
      echo 'command=/usr/sbin/apache2ctl -D FOREGROUND' >> /etc/supervisor/conf.d/supervisord.conf && \
      echo 'autostart=true' >> /etc/supervisor/conf.d/supervisord.conf && \
      echo 'autorestart=true' >> /etc/supervisor/conf.d/supervisord.conf && \
      echo '[program:turnprocessor]' >> /etc/supervisor/conf.d/supervisord.conf && \
      echo 'command=php /usr/local/bin/turn_processor.php' >> /etc/supervisor/conf.d/supervisord.conf && \
      echo 'autostart=true' >> /etc/supervisor/conf.d/supervisord.conf && \
      echo 'autorestart=true' >> /etc/supervisor/conf.d/supervisord.conf

  EXPOSE 8080

  CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
