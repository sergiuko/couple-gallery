<?php

require_once __DIR__ . '/../src/bootstrap.php';

$user = $auth->user();
if (!$user) {
    redirect('/login.php');
}

$uploadDir = media_storage_dir($config);
if (!is_dir($uploadDir)) {
    $created = mkdir($uploadDir, 0755, true);
    if (!$created && !is_dir($uploadDir)) {
        flash_set('error', 'Не удалось создать папку для медиа на сервере.');
        redirect('/add.php');
    }
}

$allowedImageMime = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

$allowedVideoMime = [
    'video/mp4' => 'mp4',
    'video/webm' => 'webm',
    'video/ogg' => 'ogv',
];

$allowedVideoExtensions = ['mp4', 'webm', 'ogv'];

function detect_file_mime(string $path): ?string
{
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
}

function detect_image_mime(string $path): ?string
{
    $mime = detect_file_mime($path);

    if (!$mime && function_exists('exif_imagetype')) {
        $imageType = exif_imagetype($path);
        $typeMap = [
            IMAGETYPE_JPEG => 'image/jpeg',
            IMAGETYPE_PNG => 'image/png',
            IMAGETYPE_GIF => 'image/gif',
            IMAGETYPE_WEBP => 'image/webp',
        ];

        if ($imageType !== false && isset($typeMap[$imageType])) {
            $mime = $typeMap[$imageType];
        }
    }

    if (!$mime) {
        $imageInfo = @getimagesize($path);
        if (is_array($imageInfo) && !empty($imageInfo['mime']) && is_string($imageInfo['mime'])) {
            $mime = $imageInfo['mime'];
        }
    }

    return $mime;
}

function save_remote_image(string $url, string $uploadDir, array $allowedImageMime): ?array
{
    $raw = null;

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'follow_location' => 1,
            'max_redirects' => 3,
            'user_agent' => 'CoupleGallery/1.0',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $downloaded = @file_get_contents($url, false, $context);
    if (is_string($downloaded) && $downloaded !== '') {
        $raw = $downloaded;
    }

    if ($raw === null && function_exists('curl_init')) {
        $curl = curl_init($url);
        if ($curl !== false) {
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_USERAGENT => 'CoupleGallery/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            $curlData = curl_exec($curl);
            $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if (is_string($curlData) && $curlData !== '' && $httpCode >= 200 && $httpCode < 400) {
                $raw = $curlData;
            }
        }
    }

    if (!is_string($raw) || $raw === '') {
        return null;
    }

    if (strlen($raw) > 8 * 1024 * 1024) {
        return null;
    }

    $tempPath = tempnam(sys_get_temp_dir(), 'cg_');
    if ($tempPath === false) {
        return null;
    }

    $written = file_put_contents($tempPath, $raw);
    if ($written === false) {
        @unlink($tempPath);
        return null;
    }

    $mime = detect_image_mime($tempPath);
    if (!$mime || !isset($allowedImageMime[$mime])) {
        @unlink($tempPath);
        return null;
    }

    if (@getimagesize($tempPath) === false) {
        @unlink($tempPath);
        return null;
    }

    try {
        $safeFileName = bin2hex(random_bytes(16)) . '.' . $allowedImageMime[$mime];
    } catch (Throwable $exception) {
        @unlink($tempPath);
        return null;
    }

    $targetPath = $uploadDir . '/' . $safeFileName;
    $saved = @rename($tempPath, $targetPath);

    if (!$saved) {
        $saved = @copy($tempPath, $targetPath);
        @unlink($tempPath);
    }

    if (!$saved || !is_file($targetPath)) {
        return null;
    }

    return ['file_name' => $safeFileName];
}

function normalize_tags(string $tagsRaw): string
{
    $parts = preg_split('/[,;]+/', $tagsRaw) ?: [];
    $result = [];

    foreach ($parts as $part) {
        $tag = trim($part);
        if ($tag === '') {
            continue;
        }

        $normalized = function_exists('mb_strtolower') ? mb_strtolower($tag) : strtolower($tag);
        if (!in_array($normalized, $result, true)) {
            $result[] = $normalized;
        }
    }

    return implode(',', $result);
}

function save_video_preview_from_data_url(string $dataUrl, string $uploadDir, array $allowedImageMime): ?string
{
    if (!preg_match('#^data:image/[a-zA-Z0-9.+-]+;base64,#', $dataUrl)) {
        return null;
    }

    $parts = explode(',', $dataUrl, 2);
    if (count($parts) !== 2) {
        return null;
    }

    $binary = base64_decode($parts[1], true);
    if (!is_string($binary) || $binary === '') {
        return null;
    }

    if (strlen($binary) > 8 * 1024 * 1024) {
        return null;
    }

    $tempPath = tempnam(sys_get_temp_dir(), 'cgvp_');
    if ($tempPath === false) {
        return null;
    }

    $written = file_put_contents($tempPath, $binary);
    if ($written === false) {
        @unlink($tempPath);
        return null;
    }

    $previewMime = detect_image_mime($tempPath);
    if (!$previewMime || !isset($allowedImageMime[$previewMime])) {
        @unlink($tempPath);
        return null;
    }

    try {
        $previewFileName = bin2hex(random_bytes(16)) . '.' . $allowedImageMime[$previewMime];
    } catch (Throwable $exception) {
        @unlink($tempPath);
        return null;
    }

    $previewTargetPath = $uploadDir . '/' . $previewFileName;
    $previewStored = @rename($tempPath, $previewTargetPath);

    if (!$previewStored) {
        $previewStored = @copy($tempPath, $previewTargetPath);
        @unlink($tempPath);
    }

    if (!$previewStored || !is_file($previewTargetPath)) {
        return null;
    }

    return $previewFileName;
}

function create_default_video_preview_file(string $uploadDir): ?string
{
    $fallbackBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wn2d5kAAAAASUVORK5CYII=';
    $binary = base64_decode($fallbackBase64, true);

    if (!is_string($binary) || $binary === '') {
        return null;
    }

    try {
        $previewFileName = bin2hex(random_bytes(16)) . '.png';
    } catch (Throwable $exception) {
        return null;
    }

    $targetPath = $uploadDir . '/' . $previewFileName;
    $written = @file_put_contents($targetPath, $binary);

    if ($written === false || !is_file($targetPath)) {
        return null;
    }

    return $previewFileName;
}

if (is_post()) {
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

    if ($contentLength > 0 && empty($_POST) && empty($_FILES)) {
        $postMaxSize = (string) ini_get('post_max_size');
        flash_set('error', 'Сервер отклонил запрос по лимиту размера (post_max_size=' . $postMaxSize . '). Перезапустите App Service или увеличьте лимит PHP до 128M.');
        redirect('/add.php');
    }

    $token = $_POST['csrf_token'] ?? null;

    if (!csrf_validate($token)) {
        flash_set('error', 'Неверный токен формы. Попробуйте еще раз.');
        redirect('/add.php');
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $tagsRaw = trim((string) ($_POST['tags'] ?? ''));
    $photoDateRaw = trim((string) ($_POST['photo_date'] ?? ''));
    $mediaType = (string) ($_POST['media_type'] ?? 'photo');
    if ($mediaType !== 'photo' && $mediaType !== 'video') {
        $mediaType = 'photo';
    }

    $sourceType = (string) ($_POST['source_type'] ?? 'upload');
    if ($sourceType !== 'upload' && $sourceType !== 'url') {
        $sourceType = 'upload';
    }

    if ($mediaType === 'video') {
        $sourceType = 'upload';
    }

    $photoUrl = trim((string) ($_POST['photo_url'] ?? ''));

    $focusX = (int) ($_POST['card_focus_x'] ?? 50);
    $focusY = (int) ($_POST['card_focus_y'] ?? 50);
    $focusX = max(0, min(100, $focusX));
    $focusY = max(0, min(100, $focusY));

    $titleLength = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);
    $descriptionLength = function_exists('mb_strlen') ? mb_strlen($description) : strlen($description);

    if ($title === '' || $titleLength < 2) {
        flash_set('error', 'Название должно содержать минимум 2 символа.');
        redirect('/add.php');
    }

    if ($titleLength > 120) {
        flash_set('error', 'Название должно содержать максимум 120 символов.');
        redirect('/add.php');
    }

    if ($description === '') {
        flash_set('error', 'Добавьте короткое описание.');
        redirect('/add.php');
    }

    if ($descriptionLength > 2000) {
        flash_set('error', 'Описание должно содержать максимум 2000 символов.');
        redirect('/add.php');
    }

    $tags = normalize_tags($tagsRaw);
    if ((function_exists('mb_strlen') ? mb_strlen($tags) : strlen($tags)) > 800) {
        flash_set('error', 'Слишком много тегов. Сократите список тегов.');
        redirect('/add.php');
    }

    $photoDate = null;
    if ($photoDateRaw !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $photoDateRaw);
        $dateErrors = DateTime::getLastErrors();
        $hasDateErrors = is_array($dateErrors)
            ? (($dateErrors['warning_count'] ?? 0) > 0 || ($dateErrors['error_count'] ?? 0) > 0)
            : false;

        if (!$date || $hasDateErrors || $date->format('Y-m-d') !== $photoDateRaw) {
            flash_set('error', 'Некорректная дата.');
            redirect('/add.php');
        }

        $photoDate = $photoDateRaw;
    }

    if (!is_writable($uploadDir)) {
        flash_set('error', 'Папка для медиа недоступна для записи. Проверьте права доступа сервера.');
        redirect('/add.php');
    }

    $safeFileName = null;
    $previewFileName = null;

    if ($mediaType === 'photo' && $sourceType === 'url') {
        if (!filter_var($photoUrl, FILTER_VALIDATE_URL)) {
            flash_set('error', 'Укажите корректную ссылку на изображение (http/https).');
            redirect('/add.php');
        }

        $scheme = strtolower((string) parse_url($photoUrl, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            flash_set('error', 'Разрешены только http/https ссылки.');
            redirect('/add.php');
        }

        $saved = save_remote_image($photoUrl, $uploadDir, $allowedImageMime);
        if (!$saved) {
            flash_set('error', 'Не удалось загрузить изображение по ссылке. Проверьте URL или выберите файл.');
            redirect('/add.php');
        }

        $safeFileName = (string) $saved['file_name'];
    } elseif ($mediaType === 'photo') {
        $stagedPhotoFileName = trim((string) ($_POST['photo_staged_file_name'] ?? ''));

        if ($stagedPhotoFileName !== '') {
            if (
                basename($stagedPhotoFileName) !== $stagedPhotoFileName
                || preg_match('/^[A-Za-z0-9._-]+$/', $stagedPhotoFileName) !== 1
                || !is_file($uploadDir . '/' . $stagedPhotoFileName)
            ) {
                flash_set('error', 'Подготовленный файл фото не найден. Загрузите фото снова.');
                redirect('/add.php');
            }

            $photoPath = $uploadDir . '/' . $stagedPhotoFileName;
            $stagedPhotoMime = detect_image_mime($photoPath);

            if (!$stagedPhotoMime || !isset($allowedImageMime[$stagedPhotoMime]) || @getimagesize($photoPath) === false) {
                flash_set('error', 'Подготовленный файл фото поврежден. Загрузите фото снова.');
                redirect('/add.php');
            }

            if (@filesize($photoPath) > 8 * 1024 * 1024) {
                @unlink($photoPath);
                flash_set('error', 'Максимальный размер файла — 8MB.');
                redirect('/add.php');
            }

            $safeFileName = $stagedPhotoFileName;
        } else {
            $photo = $_FILES['photo'] ?? null;

            if (!$photo) {
                flash_set('error', 'Файл не передан в форме.');
                redirect('/add.php');
            }

            $uploadError = (int) ($photo['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError !== UPLOAD_ERR_OK) {
                $uploadErrorMap = [
                    UPLOAD_ERR_INI_SIZE => 'Файл слишком большой для настроек сервера (upload_max_filesize).',
                    UPLOAD_ERR_FORM_SIZE => 'Файл превышает ограничение формы.',
                    UPLOAD_ERR_PARTIAL => 'Файл загрузился частично. Попробуйте еще раз.',
                    UPLOAD_ERR_NO_FILE => 'Выберите изображение для загрузки.',
                    UPLOAD_ERR_NO_TMP_DIR => 'На сервере не настроена временная папка для upload.',
                    UPLOAD_ERR_CANT_WRITE => 'Сервер не может записать файл на диск.',
                    UPLOAD_ERR_EXTENSION => 'Загрузка остановлена расширением PHP.',
                ];

                $message = $uploadErrorMap[$uploadError] ?? ('Ошибка загрузки файла. Код: ' . $uploadError);
                flash_set('error', $message);
                redirect('/add.php');
            }

            if (($photo['size'] ?? 0) > 8 * 1024 * 1024) {
                flash_set('error', 'Максимальный размер файла — 8MB.');
                redirect('/add.php');
            }

            $tmpPath = (string) ($photo['tmp_name'] ?? '');
            if ($tmpPath === '' || !is_file($tmpPath)) {
                flash_set('error', 'Временный файл загрузки недоступен. Попробуйте еще раз.');
                redirect('/add.php');
            }

            $mime = detect_image_mime($tmpPath);

            if (!$mime || !isset($allowedImageMime[$mime])) {
                flash_set('error', 'Разрешенные форматы: JPG, PNG, WEBP, GIF.');
                redirect('/add.php');
            }

            if (@getimagesize($tmpPath) === false) {
                flash_set('error', 'Файл не является корректным изображением.');
                redirect('/add.php');
            }

            try {
                $safeFileName = bin2hex(random_bytes(16)) . '.' . $allowedImageMime[$mime];
            } catch (Throwable $exception) {
                flash_set('error', 'Не удалось сгенерировать безопасное имя файла. Попробуйте еще раз.');
                redirect('/add.php');
            }

            $targetPath = $uploadDir . '/' . $safeFileName;

            $stored = move_uploaded_file($tmpPath, $targetPath);
            if (!$stored && is_uploaded_file($tmpPath)) {
                $stored = @rename($tmpPath, $targetPath) || @copy($tmpPath, $targetPath);
            }

            if (!$stored || !is_file($targetPath)) {
                flash_set('error', 'Не удалось сохранить файл.');
                redirect('/add.php');
            }
        }
    } else {
        $stagedVideoFileName = trim((string) ($_POST['video_staged_file_name'] ?? ''));

        if ($stagedVideoFileName !== '') {
            if (
                basename($stagedVideoFileName) !== $stagedVideoFileName
                || preg_match('/^[A-Za-z0-9._-]+$/', $stagedVideoFileName) !== 1
                || !is_file($uploadDir . '/' . $stagedVideoFileName)
            ) {
                flash_set('error', 'Подготовленный видеофайл не найден. Загрузите видео снова.');
                redirect('/add.php');
            }

            $safeFileName = $stagedVideoFileName;
        } else {
            $video = $_FILES['video'] ?? null;

            if (!$video) {
                flash_set('error', 'Файл видео не передан в форме.');
                redirect('/add.php');
            }

            $uploadError = (int) ($video['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError !== UPLOAD_ERR_OK) {
                $uploadErrorMap = [
                    UPLOAD_ERR_INI_SIZE => 'Видео слишком большое для настроек сервера (upload_max_filesize).',
                    UPLOAD_ERR_FORM_SIZE => 'Видео превышает ограничение формы.',
                    UPLOAD_ERR_PARTIAL => 'Видео загрузилось частично. Попробуйте еще раз.',
                    UPLOAD_ERR_NO_FILE => 'Выберите видео для загрузки.',
                    UPLOAD_ERR_NO_TMP_DIR => 'На сервере не настроена временная папка для upload.',
                    UPLOAD_ERR_CANT_WRITE => 'Сервер не может записать файл на диск.',
                    UPLOAD_ERR_EXTENSION => 'Загрузка остановлена расширением PHP.',
                ];

                $message = $uploadErrorMap[$uploadError] ?? ('Ошибка загрузки видео. Код: ' . $uploadError);
                flash_set('error', $message);
                redirect('/add.php');
            }

            if (($video['size'] ?? 0) > 100 * 1024 * 1024) {
                flash_set('error', 'Максимальный размер видео — 100MB.');
                redirect('/add.php');
            }

            $tmpPath = (string) ($video['tmp_name'] ?? '');
            if ($tmpPath === '' || !is_file($tmpPath)) {
                flash_set('error', 'Временный файл видео недоступен. Попробуйте еще раз.');
                redirect('/add.php');
            }

            $videoMime = detect_file_mime($tmpPath);
            $browserMime = strtolower(trim((string) ($video['type'] ?? '')));
            $originalExtension = strtolower((string) pathinfo((string) ($video['name'] ?? ''), PATHINFO_EXTENSION));
            $cleanedExtension = (string) preg_replace('/[^a-z0-9]+/', '', $originalExtension);

            $mimeIsAllowed = $videoMime && isset($allowedVideoMime[$videoMime]);
            $browserMimeAllowed = $browserMime !== '' && isset($allowedVideoMime[$browserMime]);
            $extensionAllowed = $cleanedExtension !== '' && in_array($cleanedExtension, $allowedVideoExtensions, true);

            if (!$mimeIsAllowed && !$browserMimeAllowed && !$extensionAllowed) {
                flash_set('error', 'Разрешенные форматы видео: MP4, WEBM, OGV. Для лучшей совместимости используйте MP4 (H.264/AAC).');
                redirect('/add.php');
            }

            $safeExtension = 'mp4';
            if ($mimeIsAllowed) {
                $safeExtension = (string) $allowedVideoMime[$videoMime];
            } elseif ($browserMimeAllowed) {
                $safeExtension = (string) $allowedVideoMime[$browserMime];
            } elseif ($extensionAllowed) {
                $safeExtension = $cleanedExtension;
            }

            try {
                $safeFileName = bin2hex(random_bytes(16)) . '.' . $safeExtension;
            } catch (Throwable $exception) {
                flash_set('error', 'Не удалось сгенерировать безопасное имя файла видео. Попробуйте еще раз.');
                redirect('/add.php');
            }

            $targetPath = $uploadDir . '/' . $safeFileName;
            $stored = move_uploaded_file($tmpPath, $targetPath);
            if (!$stored && is_uploaded_file($tmpPath)) {
                $stored = @rename($tmpPath, $targetPath) || @copy($tmpPath, $targetPath);
            }

            if (!$stored || !is_file($targetPath)) {
                flash_set('error', 'Не удалось сохранить видеофайл.');
                redirect('/add.php');
            }
        }

        $previewDataUrl = trim((string) ($_POST['video_preview_frame'] ?? ''));
        $previewFileName = save_video_preview_from_data_url($previewDataUrl, $uploadDir, $allowedImageMime);

        if (!$previewFileName) {
            $previewFileName = create_default_video_preview_file($uploadDir);
        }

        if (!$previewFileName) {
            flash_set('error', 'Не удалось создать превью для видео. Попробуйте снова.');
            redirect('/add.php');
        }
    }

    gallery_add($pdo, (int) $user['id'], $title, $description, (string) $safeFileName, $photoDate, $tags, $focusX, $focusY, $mediaType, $previewFileName);
    flash_set('success', $mediaType === 'video' ? 'Видео успешно добавлено 💖' : 'Фото успешно добавлено в вашу галерею 💖');
    redirect('/index.php');
}

$error = flash_get('error');
$success = flash_get('success');
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить медиа | Love Gallery</title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= urlencode((string) filemtime(__DIR__ . '/assets/style.css')) ?>">
</head>
<body class="add-page">
<div class="animated-bg-grid"></div>
<div class="bg-glow bg-glow-1"></div>
<div class="bg-glow bg-glow-2"></div>
<div class="bg-glow bg-glow-3"></div>
<div class="cute-bg" aria-hidden="true">
    <span class="cute-float">💖</span>
    <span class="cute-float">✨</span>
    <span class="cute-float">💗</span>
    <span class="cute-float">🌸</span>
    <span class="cute-float">💞</span>
    <span class="cute-float">🫶</span>
    <span class="cute-float">⭐</span>
    <span class="cute-float">🩷</span>
    <span class="cute-float">💫</span>
    <span class="cute-float">🌷</span>
</div>

<main class="page">
    <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-expanded="false" aria-controls="mobileMenu">☰</button>
    <div class="profile-menu profile-menu-mobile">
        <button type="button" class="profile-toggle" id="mobileProfileToggle" aria-label="Открыть профиль" aria-expanded="false" aria-controls="mobileProfilePanel">
            <span class="profile-icon" aria-hidden="true"></span>
        </button>
        <div class="mobile-profile-panel" id="mobileProfilePanel" hidden>
            <div class="profile-meta">
                <strong><?= esc($user['full_name']) ?></strong>
                <span><?= esc($user['email']) ?></span>
            </div>
            <?php if (strtolower((string) ($user['email'] ?? '')) === 'termenskysergiy@gmail.com'): ?>
                <div class="season-theme-picker">
                    <label>Тема сайта</label>
                    <select data-season-theme-select>
                        <option value="auto">Авто (по дате)</option>
                        <option value="off">Без темы</option>
                        <option value="march8">8 Березня</option>
                        <option value="newyear">Новий рік</option>
                        <option value="easter">Великдень</option>
                    </select>
                </div>
            <?php endif; ?>
            <a class="logout-link" href="/logout.php">Выйти</a>
        </div>
    </div>

    <nav class="mobile-menu" id="mobileMenu" aria-hidden="true">
        <a href="/index.php">Главная</a>
        <a href="/add.php">Добавить</a>
        <a href="/search.php">Поиск</a>
    </nav>
    <div class="mobile-menu-backdrop" id="mobileMenuBackdrop"></div>

    <nav class="top-nav animate-fade-up" id="top">
        <div class="top-nav-user">
            <strong><?= esc($user['full_name']) ?></strong>
            <span><?= esc($user['email']) ?></span>
        </div>
        <div class="top-nav-links">
            <a href="/index.php">Главная</a>
            <a href="/add.php">Добавить</a>
            <a href="/search.php">Поиск</a>
        </div>
        <details class="profile-menu profile-menu-desktop profile-menu-desktop-native">
            <summary class="profile-toggle profile-toggle-desktop" aria-label="Открыть профиль">
                <span class="profile-icon-desktop" aria-hidden="true"></span>
                <span class="profile-label-desktop">Профиль</span>
                <span class="profile-caret-desktop" aria-hidden="true"></span>
            </summary>
            <div class="profile-dropdown profile-dropdown-desktop">
                <div class="profile-meta">
                    <strong><?= esc($user['full_name']) ?></strong>
                    <span><?= esc($user['email']) ?></span>
                </div>
                <?php if (strtolower((string) ($user['email'] ?? '')) === 'termenskysergiy@gmail.com'): ?>
                    <div class="season-theme-picker">
                        <label>Тема сайта</label>
                        <select data-season-theme-select>
                            <option value="auto">Авто (по дате)</option>
                            <option value="off">Без темы</option>
                            <option value="march8">8 Березня</option>
                            <option value="newyear">Новий рік</option>
                            <option value="easter">Великдень</option>
                        </select>
                    </div>
                <?php endif; ?>
                <a class="logout-link" href="/logout.php">Выйти</a>
            </div>
        </details>
    </nav>

    <header class="hero animate-fade-up">
        <span class="badge">Upload Media</span>
        <h1>Добавить медиа</h1>
        <p>Выберите тип контента: фото или видео. Для видео выберите кадр прямо из ролика как превью карточки.</p>
    </header>

    <section class="panel add-media-panel animate-fade-up delay-1">
        <div class="add-media-panel-bg" aria-hidden="true"></div>
        <?php if ($error): ?>
            <div class="alert error"><?= esc($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert success"><?= esc($success) ?></div>
        <?php endif; ?>

        <form class="upload-form add-media-form" method="post" enctype="multipart/form-data" id="photoAddForm">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">

            <label class="add-media-label">Тип контента</label>
            <div class="source-mode add-media-chips">
                <label><input type="radio" name="media_type" value="photo" checked> <span>Фото</span></label>
                <label><input type="radio" name="media_type" value="video"> <span>Видео</span></label>
            </div>

            <div class="source-mode add-media-mode-head" data-photo-source-mode>
                <label class="add-media-label">Формат добавления</label>
            </div>
            <div class="source-mode add-media-chips" data-photo-source-mode>
                <label><input type="radio" name="source_type" value="upload" checked> <span>Файл</span></label>
                <label><input type="radio" name="source_type" value="url"> <span>URL</span></label>
            </div>

            <div data-source-upload data-media-photo-upload>
                <label for="photo">Изображение (файл)</label>
                <input id="photo" name="photo" type="file" accept="image/*">
                <input id="photo_staged_file_name" name="photo_staged_file_name" type="hidden">
            </div>

            <div data-source-url data-media-photo-url hidden>
                <label for="photo_url">Ссылка на изображение</label>
                <input id="photo_url" name="photo_url" type="url" placeholder="https://example.com/photo.jpg">
            </div>

            <div data-media-video-upload hidden>
                <label for="video">Видео (файл)</label>
                <input id="video" name="video" type="file" accept="video/mp4,video/webm,video/ogg">
                <input id="video_staged_file_name" name="video_staged_file_name" type="hidden">

                <div class="video-frame-picker" id="videoFramePicker" hidden>
                    <video id="videoFramePlayer" controls preload="metadata" playsinline></video>

                    <label for="video_frame_seek">Кадр из видео: <span id="videoFrameTimeValue">0.0</span>s</label>
                    <input id="video_frame_seek" type="range" min="0" max="1" step="1" value="0" disabled>

                    <button type="button" id="captureVideoFrameBtn" class="secondary-button">Взять кадр для превью</button>
                    <input id="video_preview_frame" name="video_preview_frame" type="hidden">
                    <p class="video-frame-note" id="videoFrameNote">Загрузите видео, выберите момент и нажмите кнопку.</p>
                </div>
            </div>

            <label for="title">Название момента</label>
            <input id="title" name="title" type="text" minlength="2" maxlength="120" required placeholder="Например: Прогулка вечерним городом">

            <label for="description">Описание</label>
            <textarea id="description" name="description" rows="4" required placeholder="Короткая история этого фото..."></textarea>

            <label for="tags">Теги (через запятую)</label>
            <input id="tags" name="tags" type="text" placeholder="например: море,лето,свидание">

            <label for="photo_date">Дата</label>
            <input id="photo_date" name="photo_date" type="date">

            <label>Позиция превью на карточке</label>
            <div class="card-preview-wrap">
                <div class="card-preview" id="cardPreview">
                    <img id="cardPreviewImage" alt="Превью карточки">
                </div>
            </div>

            <label for="card_focus_x">Фокус по горизонтали: <span id="focusXValue">50</span>%</label>
            <input id="card_focus_x" name="card_focus_x" type="range" min="0" max="100" value="50">

            <label for="card_focus_y">Фокус по вертикали: <span id="focusYValue">50</span>%</label>
            <input id="card_focus_y" name="card_focus_y" type="range" min="0" max="100" value="50">

            <button type="submit">Загрузить</button>
        </form>
    </section>
</main>

<footer class="love-footer animate-fade-up delay-3" aria-label="Love is footer">
    <div class="love-is-line" aria-hidden="true">
        <span class="love-is-beam"></span>
        <span class="love-is-text"><span class="love-is-heart">❤</span><span>Love is...</span></span>
    </div>
</footer>

<script src="/assets/app.js?v=<?= urlencode((string) filemtime(__DIR__ . '/assets/app.js')) ?>"></script>
</body>
</html>
