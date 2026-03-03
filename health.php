<?php

require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/helpers.php';

header('Content-Type: application/json; charset=UTF-8');

$storageDir = media_storage_dir($config);
$publicPrefix = media_public_url_prefix($config);

$response = [
    'php_version' => PHP_VERSION,
    'azure_app_service' => (bool) getenv('WEBSITE_INSTANCE_ID'),
    'media_storage_dir' => $storageDir,
    'media_storage_dir_exists' => is_dir($storageDir),
    'media_storage_dir_writable' => is_dir($storageDir) ? is_writable($storageDir) : false,
    'media_public_url_prefix' => $publicPrefix,
    'media_skip_local_file_check' => media_skip_local_file_check($config),
    'db_driver' => (string) ($config['db']['driver'] ?? 'unknown'),
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
