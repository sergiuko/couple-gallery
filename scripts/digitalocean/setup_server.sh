#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/couple-gallery"
NGINX_AVAILABLE="/etc/nginx/sites-available/couple-gallery"
NGINX_ENABLED="/etc/nginx/sites-enabled/couple-gallery"
PHP_FPM_SOCK="/run/php/php8.2-fpm.sock"

if [ ! -S "$PHP_FPM_SOCK" ]; then
  PHP_FPM_SOCK="/run/php/php8.1-fpm.sock"
fi

sudo apt update
sudo apt install -y nginx php-fpm php-sqlite3 php-mbstring php-curl php-xml php-gd unzip

sudo mkdir -p "$APP_DIR"
sudo chown -R "$USER":"$USER" "$APP_DIR"

if [ ! -f "$APP_DIR/src/config.local.php" ]; then
  sudo mkdir -p "$APP_DIR/src"
  cat <<'PHP' | sudo tee "$APP_DIR/src/config.local.php" > /dev/null
<?php
return [
    'app' => [
        'session_name' => 'couple_gallery_session',
        'debug' => false,
    ],
    'db' => [
        'driver' => 'sqlite',
        'sqlite_path' => __DIR__ . '/../storage/gallery.sqlite',
    ],
];
PHP
fi

cat <<NGINX | sudo tee "$NGINX_AVAILABLE" > /dev/null
server {
    listen 80;
    server_name _;

    root $APP_DIR/public;
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_FPM_SOCK;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
NGINX

sudo ln -sf "$NGINX_AVAILABLE" "$NGINX_ENABLED"
sudo nginx -t
sudo systemctl restart php8.2-fpm || true
sudo systemctl restart php8.1-fpm || true
sudo systemctl restart nginx

echo "DigitalOcean server setup complete."