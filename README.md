# Couple Gallery (PHP)

Современный сайт-галерея для пары с анимациями, адаптивным UI и авторизацией:
- регистрация пользователя;
- логин/логаут;
- персональная галерея для каждого аккаунта;
- добавление фото в двух форматах: файл или URL (JPG/PNG/WEBP/GIF);
- выбор даты фото при создании;
- настройка фокуса кадра (позиция X/Y) для корректного отображения карточки;
- просмотр фото на отдельной странице с названием, описанием и датой;
- плавные анимации и корректный вид на телефоне/планшете/ПК.

## Запуск локально (Windows)

1. Перейдите в папку проекта:
   ```powershell
   Set-Location d:\AI\couple-gallery
   ```

2. Запустите PHP-сервер:
   ```powershell
   php -S localhost:8000 -t public
   ```

   Если `php` не добавлен в `PATH`, используйте полный путь к интерпретатору:
   ```powershell
   d:\AI\tools\php\php.exe -S localhost:8000 -t public
   ```

3. Откройте:
   ```
   http://localhost:8000/register.php
   ```

По умолчанию локально используется SQLite (`storage/gallery.sqlite`).

## Бесплатный деплой (Oracle Cloud Always Free) — правильно по шагам

## Найпростіший варіант через GitHub Student: DigitalOcean (автодеплой)

Якщо у вас є GitHub Student вигода, найпростіше для цього проєкту: `DigitalOcean Droplet + GitHub Actions`.

### 1) Створіть Droplet (одноразово)

1. Увійдіть у DigitalOcean через студентську вигоду.
2. Створіть `Ubuntu Droplet`.
3. Додайте ваш SSH public key під час створення.
4. Візьміть `IP` сервера.

### 2) Перший запуск на сервері (одноразово)

```bash
git clone <YOUR_REPO_URL> /var/www/couple-gallery
cd /var/www/couple-gallery
chmod +x scripts/digitalocean/setup_server.sh
./scripts/digitalocean/setup_server.sh
```

### 3) Додайте GitHub Secrets (одноразово)

`Settings` → `Secrets and variables` → `Actions`:

- `DO_HOST` = IP сервера
- `DO_USER` = SSH користувач (зазвичай `root` або `ubuntu`)
- `DO_PORT` = `22`
- `DO_SSH_KEY` = приватний SSH ключ (повний вміст)

### 4) Автоматичний деплой

Уже додано workflow:

- `.github/workflows/deploy-digitalocean.yml`

Після цього все просто: пушиш у `main` → деплой виконується автоматично.

Серверний скрипт деплою:

- `scripts/digitalocean/deploy_remote.sh`

### Автоматично через GitHub (щоб майже не паритись)

> Важливо: «повністю безлімітного» безкоштовного хостингу не буває. `Oracle Always Free` безстроковий, але має квоти ресурсів.

Після одноразової реєстрації в Oracle і налаштування секретів у GitHub, кожен `push` у `main` буде деплоїти сайт автоматично.

#### 1) Одноразово на Oracle VM

1. Завантажте репозиторій на сервер.
2. Запустіть:

   ```bash
   chmod +x scripts/oracle/setup_server.sh
   ./scripts/oracle/setup_server.sh
   ```

Це встановить `nginx + php-fpm`, підготує `/var/www/couple-gallery`, створить базовий Nginx-конфіг і `src/config.local.php` (SQLite).

#### 2) Одноразово в GitHub репозиторії

`Settings` → `Secrets and variables` → `Actions` → `New repository secret`:

- `ORACLE_HOST` = IP сервера
- `ORACLE_USER` = користувач SSH (часто `ubuntu`)
- `ORACLE_PORT` = `22`
- `ORACLE_SSH_KEY` = приватний SSH ключ (повний вміст)

#### 3) Автодеплой

У репозиторії вже є workflow:

- `.github/workflows/deploy-oracle-free.yml`

Що робить:
- пакує проєкт;
- копіює архів на Oracle;
- запускає `scripts/oracle/deploy_remote.sh`;
- виставляє права і перезапускає сервіси.

Після цього: просто пушиш у `main` → сайт оновлюється сам.

### Быстрый путь (максимально просто, без MySQL)

Если хотите запустить сайт без лишней настройки БД, используйте SQLite (уже встроено в проект).

1. Подключитесь к Oracle VM по SSH и выполните команды:

   ```bash
   sudo apt update
   sudo apt install -y nginx php-fpm php-sqlite3 php-mbstring php-curl php-xml php-gd unzip
   sudo mkdir -p /var/www/couple-gallery
   ```

2. Загрузите проект в `/var/www/couple-gallery` (через `scp`, Git или SFTP).

3. Выставьте права:

   ```bash
   sudo chown -R www-data:www-data /var/www/couple-gallery
   sudo find /var/www/couple-gallery -type d -exec chmod 755 {} \;
   sudo find /var/www/couple-gallery -type f -exec chmod 644 {} \;
   sudo chmod -R 775 /var/www/couple-gallery/storage /var/www/couple-gallery/public/uploads
   ```

4. Создайте `src/config.local.php`:

   ```php
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
   ```

5. Создайте Nginx конфиг `/etc/nginx/sites-available/couple-gallery`:

   ```nginx
   server {
      listen 80;
      server_name _;

      root /var/www/couple-gallery/public;
      index index.php index.html;

      location / {
         try_files $uri $uri/ =404;
      }

      location ~ \.php$ {
         include snippets/fastcgi-php.conf;
         fastcgi_pass unix:/run/php/php8.2-fpm.sock;
         fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
         include fastcgi_params;
      }

      location ~ /\.ht {
         deny all;
      }
   }
   ```

6. Включите сайт и перезапустите сервисы:

   ```bash
   sudo ln -s /etc/nginx/sites-available/couple-gallery /etc/nginx/sites-enabled/couple-gallery
   sudo nginx -t
   sudo systemctl restart php8.2-fpm
   sudo systemctl restart nginx
   ```

7. Откройте: `http://SERVER_IP/register.php`

Это самый простой рабочий вариант. MySQL можно подключить позже, когда понадобится.

### 1) Создайте VM в Oracle Cloud

1. Зарегистрируйтесь в Oracle Cloud Free Tier.
2. Создайте Compute Instance (Ubuntu).
3. Откройте в Security List порты `80` и `443`.
4. Подключитесь по SSH к серверу.

### 2) Установите стек

На сервере установите:
- `nginx`
- `php-fpm` и расширения (`php-mysql`, `php-sqlite3`, `php-mbstring`, `php-curl`, `php-xml`, `php-gd`)
- `mysql-server` (или используйте SQLite)

### 3) Разверните проект

Рекомендуемая структура:

- `/var/www/couple-gallery/public` ← веб-корень (docroot)
- `/var/www/couple-gallery/src`
- `/var/www/couple-gallery/storage`

### 4) Настройте конфиг проекта

1. Скопируйте файл `src/config.local.example.php` в `src/config.local.php`.
2. Заполните БД (для MySQL на Oracle VM):
   - `driver` = `mysql`
   - `host` = `127.0.0.1`
   - `port` = `3306`
   - `name`, `user`, `pass` — ваши значения
3. Для продакшена выставьте:
   - `app.debug` = `false`

### 5) Права доступа

- Для `storage` и `public/uploads` нужны права записи для пользователя веб-сервера (`www-data`).
- Рекомендуется: владелец `www-data:www-data` и права `755` на папки (`644` на файлы).

### 6) Nginx docroot

В конфиге сайта укажите root на:

`/var/www/couple-gallery/public`

Это важно, чтобы backend (`src`) не был доступен напрямую из веба.

### 7) Проверка после деплоя

1. Откройте `http://<SERVER_IP>/register.php`.
2. Зарегистрируйте аккаунт.
3. Войдите через `http://<SERVER_IP>/login.php`.
4. Загрузите тестовый файл и проверьте отображение.

### 8) SSL и домен (по желанию)

- Подключите домен к IP сервера (A-запись).
- Выпустите SSL через Let's Encrypt (`certbot`), чтобы получить `https://`.

### 9) Типовые проблемы

- `502/504` → проверьте, что запущен `php-fpm` и корректно указан сокет в Nginx.
- `500` → проверьте права на `storage` и `public/uploads`.
- Нет соединения с БД → проверьте `src/config.local.php` и доступы MySQL.

## Структура

- `public/` — страницы сайта (`index`, `login`, `register`, `logout`) и статические файлы.
- `src/` — backend-логика (БД, auth, helpers).
- `storage/` — SQLite-файл для локального режима.
