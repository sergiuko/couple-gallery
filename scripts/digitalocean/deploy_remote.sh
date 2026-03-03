#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/couple-gallery"
ARCHIVE_PATH="/tmp/couple-gallery.tgz"

sudo mkdir -p "$APP_DIR"
sudo tar -xzf "$ARCHIVE_PATH" -C "$APP_DIR"

sudo mkdir -p "$APP_DIR/storage" "$APP_DIR/public/uploads"
sudo chown -R www-data:www-data "$APP_DIR"
sudo find "$APP_DIR" -type d -exec chmod 755 {} \;
sudo find "$APP_DIR" -type f -exec chmod 644 {} \;
sudo chmod -R 775 "$APP_DIR/storage" "$APP_DIR/public/uploads"

if [ ! -f "$APP_DIR/src/config.local.php" ]; then
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

sudo systemctl restart php8.2-fpm || true
sudo systemctl restart php8.1-fpm || true
sudo systemctl reload nginx || true

echo "Deploy complete."