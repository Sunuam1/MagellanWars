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

  # Create the complete turn processor
  RUN cat > /var/www/html/turn_processor.php << 'PROCESSOR'
  #!/usr/bin/env php
  <?php
  \$db_host = getenv('DB_HOST') ?: 'localhost';
  \$db_name = getenv('DB_NAME') ?: 'Archspace2';
  \$db_user = getenv('DB_USER') ?: 'archspace';
  \$db_pass = getenv('DB_PASSWORD') ?: 'archspace123';

  echo "[TURNS] Starting turn processor\\n";

  while (true) {
      try {
          \$pdo = new PDO("mysql:host=\$db_host;dbname=\$db_name", \$db_user, \$db_pass);
          \$stmt = \$pdo->query("SELECT MIN(tick) as next_tick, MAX(turn) as current_turn FROM player WHERE game_id
   > 0 AND game_id != 9999999");
          \$info = \$stmt->fetch(PDO::FETCH_ASSOC);

          \$currentTime = time();
          \$nextTick = \$info['next_tick'] ?: 0;
          \$currentTurn = \$info['current_turn'] ?: 0;

          if (\$nextTick <= \$currentTime || \$nextTick == 0) {
              \$newTurn = \$currentTurn + 1;
              \$newTick = \$currentTime + 300;

              echo "[TURNS] Processing turn \$currentTurn -> \$newTurn\\n";

              // Update turns
              \$stmt = \$pdo->prepare("UPDATE player SET turn = ?, tick = ? WHERE game_id > 0 AND game_id !=
  9999999");
              \$stmt->execute([\$newTurn, \$newTick]);

              // Update resources
              \$pdo->exec("UPDATE player SET production = production + 100, research = research + 10, military =
  military + 5 WHERE game_id > 0 AND game_id != 9999999");

              echo "[TURNS] Turn \$newTurn complete!\\n";
          }
      } catch (Exception \$e) {
          echo "[TURNS] Error: " . \$e->getMessage() . "\\n";
      }
      sleep(30);
  }
  ?>
  PROCESSOR

  RUN chmod +x /var/www/html/turn_processor.php

  # Supervisor configuration
  RUN cat > /etc/supervisor/conf.d/supervisord.conf << 'SUPERVISOR'
  [supervisord]
  nodaemon=true
  logfile=/var/log/supervisor/supervisord.log

  [program:apache2]
  command=/usr/sbin/apache2ctl -D FOREGROUND
  autostart=true
  autorestart=true
  stdout_logfile=/dev/stdout
  stdout_logfile_maxbytes=0
  stderr_logfile=/dev/stderr
  stderr_logfile_maxbytes=0

  [program:turnprocessor]
  command=php /var/www/html/turn_processor.php
  autostart=true
  autorestart=true
  stdout_logfile=/dev/stdout
  stdout_logfile_maxbytes=0
  stderr_logfile=/dev/stderr
  stderr_logfile_maxbytes=0
  environment=DB_HOST="%(ENV_DB_HOST)s",DB_NAME="%(ENV_DB_NAME)s",DB_USER="%(ENV_DB_USER)s",DB_PASSWORD="%(ENV_DB_P
  ASSWORD)s"
  SUPERVISOR

  # Startup script
  RUN cat > /start.sh << 'SCRIPT'
  #!/bin/bash
  echo "=== MagellanWars Starting ==="
  echo "Waiting for database..."

  for i in {1..30}; do
      if mysql -h\${DB_HOST} -u\${DB_USER} -p\${DB_PASSWORD} \${DB_NAME} -e "SELECT 1" &>/dev/null; then
          echo "Database ready!"
          mysql -h\${DB_HOST} -u\${DB_USER} -p\${DB_PASSWORD} \${DB_NAME} -e "UPDATE player SET tick =
  UNIX_TIMESTAMP() + 300 WHERE game_id > 0 AND (tick = 0 OR tick IS NULL)"
          break
      fi
      sleep 2
  done

  echo "Starting supervisor..."
  exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
  SCRIPT

  RUN chmod +x /start.sh

  EXPOSE 8080

  CMD ["/start.sh"]
