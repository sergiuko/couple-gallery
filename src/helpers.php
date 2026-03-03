<?php

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_validate(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function flash_get(string $type): ?string
{
    if (!isset($_SESSION['flash'][$type])) {
        return null;
    }

    $message = $_SESSION['flash'][$type];
    unset($_SESSION['flash'][$type]);

    return $message;
}

function media_storage_dir(array $config): string
{
    $configured = trim((string) ($config['media']['storage_dir'] ?? ''));
    if ($configured === '') {
        $configured = __DIR__ . '/../public/uploads';
    }

    return rtrim($configured, '/\\');
}

function media_public_url_prefix(array $config): string
{
    $prefix = trim((string) ($config['media']['public_url_prefix'] ?? '/uploads'));
    if ($prefix === '') {
        $prefix = '/uploads';
    }

    $prefix = '/' . ltrim($prefix, '/');

    return rtrim($prefix, '/');
}

function media_public_file_url(array $config, string $fileName): string
{
    return media_public_url_prefix($config) . '/' . rawurlencode($fileName);
}
