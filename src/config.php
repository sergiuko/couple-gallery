<?php

$defaultSqlitePath = __DIR__ . '/../storage/gallery.sqlite';
$isAzureAppService = (bool) getenv('WEBSITE_INSTANCE_ID');

if ($isAzureAppService && PHP_OS_FAMILY !== 'Windows') {
    $defaultSqlitePath = '/home/data/couple-gallery/gallery.sqlite';
}

$config = [
    'app' => [
        'session_name' => getenv('APP_SESSION_NAME') ?: 'couple_gallery_session',
        'debug' => filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOL),
    ],
    'db' => [
        'driver' => getenv('DB_DRIVER') ?: 'sqlite',
        'sqlite_path' => getenv('SQLITE_PATH') ?: $defaultSqlitePath,
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: '',
        'user' => getenv('DB_USER') ?: '',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
];

$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (is_array($localConfig)) {
        $config = array_replace_recursive($config, $localConfig);
    }
}

return $config;
