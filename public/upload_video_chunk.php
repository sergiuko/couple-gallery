<?php

require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$respond = static function (int $statusCode, array $payload): void {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

$user = $auth->user();
if (!$user) {
    $respond(401, ['ok' => false, 'error' => 'Требуется авторизация.']);
}

if (!is_post()) {
    $respond(405, ['ok' => false, 'error' => 'Метод не поддерживается.']);
}

$token = $_POST['csrf_token'] ?? null;
if (!csrf_validate($token)) {
    $respond(400, ['ok' => false, 'error' => 'Неверный CSRF токен.']);
}

$uploadId = trim((string) ($_POST['upload_id'] ?? ''));
$chunkIndex = (int) ($_POST['chunk_index'] ?? -1);
$totalChunks = (int) ($_POST['total_chunks'] ?? 0);
$originalName = trim((string) ($_POST['original_name'] ?? 'video.mp4'));
$fileSize = (int) ($_POST['file_size'] ?? 0);

if (preg_match('/^[A-Za-z0-9_-]{8,80}$/', $uploadId) !== 1) {
    $respond(400, ['ok' => false, 'error' => 'Некорректный идентификатор загрузки.']);
}

if ($chunkIndex < 0 || $totalChunks < 1 || $chunkIndex >= $totalChunks) {
    $respond(400, ['ok' => false, 'error' => 'Некорректные параметры чанка.']);
}

if ($fileSize > 100 * 1024 * 1024) {
    $respond(413, ['ok' => false, 'error' => 'Максимальный размер видео — 100MB.']);
}

$chunk = $_FILES['chunk'] ?? null;
if (!$chunk) {
    $respond(400, ['ok' => false, 'error' => 'Чанк не передан.']);
}

$chunkError = (int) ($chunk['error'] ?? UPLOAD_ERR_NO_FILE);
if ($chunkError !== UPLOAD_ERR_OK) {
    $respond(400, ['ok' => false, 'error' => 'Ошибка загрузки чанка. Код: ' . $chunkError]);
}

$tmpPath = (string) ($chunk['tmp_name'] ?? '');
if ($tmpPath === '' || !is_file($tmpPath)) {
    $respond(400, ['ok' => false, 'error' => 'Временный файл чанка недоступен.']);
}

$uploadDir = media_storage_dir($config);
if (!is_dir($uploadDir)) {
    $created = mkdir($uploadDir, 0755, true);
    if (!$created && !is_dir($uploadDir)) {
        $respond(500, ['ok' => false, 'error' => 'Не удалось создать папку для медиа.']);
    }
}

if (!is_writable($uploadDir)) {
    $respond(500, ['ok' => false, 'error' => 'Папка для медиа недоступна для записи.']);
}

$tempRoot = rtrim(sys_get_temp_dir(), '/\\');
$chunksBaseDir = $tempRoot . '/couple_gallery_chunks/' . (int) $user['id'];
if (!is_dir($chunksBaseDir)) {
    $created = mkdir($chunksBaseDir, 0755, true);
    if (!$created && !is_dir($chunksBaseDir)) {
        $respond(500, ['ok' => false, 'error' => 'Не удалось создать папку для временных чанков.']);
    }
}

$partPath = $chunksBaseDir . '/' . $uploadId . '.part';
$metaPath = $chunksBaseDir . '/' . $uploadId . '.json';

$meta = ['next_index' => 0, 'total_chunks' => $totalChunks, 'original_name' => $originalName];
if (is_file($metaPath)) {
    $decoded = json_decode((string) file_get_contents($metaPath), true);
    if (is_array($decoded)) {
        $meta = array_replace($meta, $decoded);
    }
}

$expectedIndex = (int) ($meta['next_index'] ?? 0);
$expectedTotal = (int) ($meta['total_chunks'] ?? $totalChunks);
if ($expectedTotal !== $totalChunks) {
    @unlink($partPath);
    @unlink($metaPath);
    $respond(409, ['ok' => false, 'error' => 'Конфликт параметров загрузки. Начните загрузку заново.']);
}

if ($chunkIndex !== $expectedIndex) {
    $respond(409, ['ok' => false, 'error' => 'Нарушен порядок чанков. Начните загрузку заново.']);
}

$chunkData = file_get_contents($tmpPath);
if (!is_string($chunkData) || $chunkData === '') {
    $respond(400, ['ok' => false, 'error' => 'Пустой чанк.']);
}

$appendMode = $chunkIndex === 0 ? 'wb' : 'ab';
$handle = fopen($partPath, $appendMode);
if ($handle === false) {
    $respond(500, ['ok' => false, 'error' => 'Не удалось открыть временный файл чанков.']);
}

$written = fwrite($handle, $chunkData);
fclose($handle);

if ($written === false) {
    $respond(500, ['ok' => false, 'error' => 'Не удалось записать чанк на диск.']);
}

$meta['next_index'] = $chunkIndex + 1;
$meta['total_chunks'] = $totalChunks;
$meta['original_name'] = $originalName;
file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

if ($meta['next_index'] < $totalChunks) {
    $respond(200, [
        'ok' => true,
        'completed' => false,
        'uploaded_chunks' => $meta['next_index'],
        'total_chunks' => $totalChunks,
    ]);
}

$detectMime = static function (string $path): ?string {
    $mime = null;

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $path) : null;
        if ($finfo) {
            finfo_close($finfo);
        }
    }

    if (!$mime && function_exists('mime_content_type')) {
        $detected = mime_content_type($path);
        if (is_string($detected) && $detected !== '') {
            $mime = $detected;
        }
    }

    return $mime;
};

$allowedVideoMime = [
    'video/mp4' => 'mp4',
    'video/webm' => 'webm',
    'video/ogg' => 'ogv',
    'video/quicktime' => 'mov',
    'video/x-matroska' => 'mkv',
];

$allowedVideoExtensions = ['mp4', 'webm', 'ogv', 'mov', 'mkv'];

$videoMime = $detectMime($partPath);
$originalExtension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
$cleanedExtension = (string) preg_replace('/[^a-z0-9]+/', '', $originalExtension);

$mimeIsAllowed = $videoMime && isset($allowedVideoMime[$videoMime]);
$extensionAllowed = $cleanedExtension !== '' && in_array($cleanedExtension, $allowedVideoExtensions, true);

if (!$mimeIsAllowed && !$extensionAllowed) {
    @unlink($partPath);
    @unlink($metaPath);
    $respond(400, ['ok' => false, 'error' => 'Неподдерживаемый формат видео.']);
}

$safeExtension = 'mp4';
if ($mimeIsAllowed) {
    $safeExtension = (string) $allowedVideoMime[$videoMime];
} elseif ($extensionAllowed) {
    $safeExtension = $cleanedExtension;
}

try {
    $safeFileName = bin2hex(random_bytes(16)) . '.' . $safeExtension;
} catch (Throwable $exception) {
    @unlink($partPath);
    @unlink($metaPath);
    $respond(500, ['ok' => false, 'error' => 'Не удалось сгенерировать имя видеофайла.']);
}

$targetPath = $uploadDir . '/' . $safeFileName;
$moved = @rename($partPath, $targetPath);
if (!$moved) {
    $copied = @copy($partPath, $targetPath);
    if ($copied) {
        @unlink($partPath);
        $moved = true;
    }
}

@unlink($metaPath);

if (!$moved || !is_file($targetPath)) {
    $respond(500, ['ok' => false, 'error' => 'Не удалось сохранить собранный видеофайл.']);
}

if (filesize($targetPath) > 100 * 1024 * 1024) {
    @unlink($targetPath);
    $respond(413, ['ok' => false, 'error' => 'Максимальный размер видео — 100MB.']);
}

$respond(200, [
    'ok' => true,
    'completed' => true,
    'file_name' => $safeFileName,
]);
