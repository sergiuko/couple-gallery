<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/db.php';

$config = require __DIR__ . '/src/config.php';

$checks = [
    'php_version' => PHP_VERSION,
    'sqlite_extension_loaded' => extension_loaded('pdo_sqlite'),
    'azure_app_service' => (bool) getenv('WEBSITE_INSTANCE_ID'),
    'configured_sqlite_path' => (string) ($config['db']['sqlite_path'] ?? ''),
    'configured_sqlite_dir_writable' => false,
    'db_connect' => false,
    'db_error' => null,
];

$sqlitePath = (string) ($config['db']['sqlite_path'] ?? '');
if ($sqlitePath !== '') {
    $sqliteDir = dirname($sqlitePath);
    $checks['configured_sqlite_dir_writable'] = is_dir($sqliteDir) ? is_writable($sqliteDir) : false;
}

try {
    $pdo = db_connect($config['db']);
    $pdo->query('SELECT 1');
    $checks['db_connect'] = true;
} catch (Throwable $e) {
    $checks['db_error'] = $e->getMessage();
}

http_response_code($checks['db_connect'] ? 200 : 500);
echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
