<?php

function db_connect(array $config): PDO
{
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $driver = $config['driver'] ?? 'sqlite';

    if ($driver === 'mysql') {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'] ?? '127.0.0.1',
            (int) ($config['port'] ?? 3306),
            $config['name'] ?? '',
            $config['charset'] ?? 'utf8mb4'
        );

        $pdo = new PDO($dsn, $config['user'] ?? '', $config['pass'] ?? '', $options);
        ensure_mysql_schema($pdo);

        return $pdo;
    }

    $sqlitePath = $config['sqlite_path'] ?? (__DIR__ . '/../storage/gallery.sqlite');
    $dir = dirname($sqlitePath);
    if (!is_dir($dir)) {
        $created = mkdir($dir, 0755, true);
        if (!$created && !is_dir($dir)) {
            throw new RuntimeException('Не удалось создать директорию для SQLite: ' . $dir);
        }
    }

    $pdo = new PDO('sqlite:' . $sqlitePath, null, null, $options);
    ensure_sqlite_schema($pdo);

    return $pdo;
}

function ensure_sqlite_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS photos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT NOT NULL,
            file_name TEXT NOT NULL,
            media_type TEXT NOT NULL DEFAULT "photo",
            preview_file_name TEXT NULL,
            photo_date TEXT NULL,
            tags TEXT NULL,
            card_focus_x INTEGER NOT NULL DEFAULT 50,
            card_focus_y INTEGER NOT NULL DEFAULT 50,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )'
    );

    $columns = $pdo->query('PRAGMA table_info(photos)')->fetchAll();
    $hasPhotoDate = false;

    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'photo_date') {
            $hasPhotoDate = true;
            break;
        }
    }

    if (!$hasPhotoDate) {
        $pdo->exec('ALTER TABLE photos ADD COLUMN photo_date TEXT NULL');
    }

    $hasFocusX = false;
    $hasFocusY = false;
    $hasTags = false;
    $hasMediaType = false;
    $hasPreviewFileName = false;

    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'card_focus_x') {
            $hasFocusX = true;
        }

        if (($column['name'] ?? '') === 'card_focus_y') {
            $hasFocusY = true;
        }

        if (($column['name'] ?? '') === 'tags') {
            $hasTags = true;
        }

        if (($column['name'] ?? '') === 'media_type') {
            $hasMediaType = true;
        }

        if (($column['name'] ?? '') === 'preview_file_name') {
            $hasPreviewFileName = true;
        }
    }

    if (!$hasTags) {
        $pdo->exec('ALTER TABLE photos ADD COLUMN tags TEXT NULL');
    }

    if (!$hasFocusX) {
        $pdo->exec('ALTER TABLE photos ADD COLUMN card_focus_x INTEGER NOT NULL DEFAULT 50');
    }

    if (!$hasFocusY) {
        $pdo->exec('ALTER TABLE photos ADD COLUMN card_focus_y INTEGER NOT NULL DEFAULT 50');
    }

    if (!$hasMediaType) {
        $pdo->exec('ALTER TABLE photos ADD COLUMN media_type TEXT NOT NULL DEFAULT "photo"');
    }

    if (!$hasPreviewFileName) {
        $pdo->exec('ALTER TABLE photos ADD COLUMN preview_file_name TEXT NULL');
    }
}

function ensure_mysql_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS photos (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            title VARCHAR(120) NOT NULL,
            description TEXT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            media_type VARCHAR(10) NOT NULL DEFAULT "photo",
            preview_file_name VARCHAR(255) NULL,
            photo_date DATE NULL,
            tags TEXT NULL,
            card_focus_x TINYINT UNSIGNED NOT NULL DEFAULT 50,
            card_focus_y TINYINT UNSIGNED NOT NULL DEFAULT 50,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_photos_users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $checkStmt = $pdo->prepare(
        'SELECT COUNT(*) AS cnt
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );

    $checkStmt->execute([
        ':table_name' => 'photos',
        ':column_name' => 'photo_date',
    ]);

    $exists = (int) ($checkStmt->fetch()['cnt'] ?? 0) > 0;

    if (!$exists) {
        $pdo->exec('ALTER TABLE photos ADD COLUMN photo_date DATE NULL AFTER file_name');
    }

    $checkStmt->execute([
        ':table_name' => 'photos',
        ':column_name' => 'card_focus_x',
    ]);
    $hasFocusX = (int) ($checkStmt->fetch()['cnt'] ?? 0) > 0;

    if (!$hasFocusX) {
        $pdo->exec('ALTER TABLE photos ADD COLUMN card_focus_x TINYINT UNSIGNED NOT NULL DEFAULT 50 AFTER photo_date');
    }

    $checkStmt->execute([
        ':table_name' => 'photos',
        ':column_name' => 'card_focus_y',
    ]);
    $hasFocusY = (int) ($checkStmt->fetch()['cnt'] ?? 0) > 0;

    if (!$hasFocusY) {
        $pdo->exec('ALTER TABLE photos ADD COLUMN card_focus_y TINYINT UNSIGNED NOT NULL DEFAULT 50 AFTER card_focus_x');
    }

    $checkStmt->execute([
        ':table_name' => 'photos',
        ':column_name' => 'tags',
    ]);
    $hasTags = (int) ($checkStmt->fetch()['cnt'] ?? 0) > 0;

    if (!$hasTags) {
        $pdo->exec('ALTER TABLE photos ADD COLUMN tags TEXT NULL AFTER photo_date');
    }

    $checkStmt->execute([
        ':table_name' => 'photos',
        ':column_name' => 'media_type',
    ]);
    $hasMediaType = (int) ($checkStmt->fetch()['cnt'] ?? 0) > 0;

    if (!$hasMediaType) {
        $pdo->exec('ALTER TABLE photos ADD COLUMN media_type VARCHAR(10) NOT NULL DEFAULT "photo" AFTER file_name');
    }

    $checkStmt->execute([
        ':table_name' => 'photos',
        ':column_name' => 'preview_file_name',
    ]);
    $hasPreviewFileName = (int) ($checkStmt->fetch()['cnt'] ?? 0) > 0;

    if (!$hasPreviewFileName) {
        $pdo->exec('ALTER TABLE photos ADD COLUMN preview_file_name VARCHAR(255) NULL AFTER media_type');
    }
}
