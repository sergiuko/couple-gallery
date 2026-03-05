<?php

require_once __DIR__ . '/../src/bootstrap.php';

$user = $auth->user();
if (!$user) {
    redirect('/login.php');
}

$uploadDir = media_storage_dir($config);
$photoId = (int) ($_GET['id'] ?? 0);

if ($photoId <= 0) {
    flash_set('error', 'Фото не найдено.');
    redirect('/index.php');
}

$photo = gallery_get($pdo, (int) $user['id'], $photoId);
if (!$photo) {
    flash_set('error', 'Фото не найдено.');
    redirect('/index.php');
}

if (is_post()) {
    $token = $_POST['csrf_token'] ?? null;
    $action = (string) ($_POST['action'] ?? '');

    if (!csrf_validate($token)) {
        flash_set('error', 'Неверный токен формы. Попробуйте еще раз.');
        redirect('/photo.php?id=' . $photoId);
    }

    if ($action !== 'delete_media') {
        flash_set('error', 'Неизвестное действие.');
        redirect('/photo.php?id=' . $photoId);
    }

    $fileNameToDelete = (string) ($photo['file_name'] ?? '');
    $previewToDelete = (string) ($photo['preview_file_name'] ?? '');

    try {
        $deleted = gallery_delete($pdo, (int) $user['id'], $photoId);
    } catch (Throwable $exception) {
        flash_set('error', 'Не удалось удалить медиа. Попробуйте снова.');
        redirect('/photo.php?id=' . $photoId);
    }

    if (!$deleted) {
        flash_set('error', 'Не удалось удалить медиа.');
        redirect('/photo.php?id=' . $photoId);
    }

    $safeDelete = static function (string $fileName) use ($uploadDir): void {
        if (
            $fileName === ''
            || basename($fileName) !== $fileName
            || preg_match('/^[A-Za-z0-9._-]+$/', $fileName) !== 1
        ) {
            return;
        }

        $path = $uploadDir . '/' . $fileName;
        if (is_file($path)) {
            @unlink($path);
        }
    };

    $safeDelete($fileNameToDelete);
    if ($previewToDelete !== $fileNameToDelete) {
        $safeDelete($previewToDelete);
    }

    flash_set('success', 'Медиа удалено.');
    redirect('/index.php');
}

$fileName = (string) ($photo['file_name'] ?? '');
$mediaType = (string) ($photo['media_type'] ?? 'photo');
$isValidName = $fileName !== ''
    && basename($fileName) === $fileName
    && preg_match('/^[A-Za-z0-9._-]+$/', $fileName) === 1;

if (!$isValidName || !media_file_exists($config, $fileName)) {
    flash_set('error', 'Файл этого фото недоступен.');
    redirect('/index.php');
}

$imageUrl = media_public_file_url($config, $fileName);
$videoPosterUrl = null;
$videoSourceType = null;
if ($mediaType === 'video') {
    $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
    $typeMap = [
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'ogv' => 'video/ogg',
    ];
    if (isset($typeMap[$extension])) {
        $videoSourceType = $typeMap[$extension];
    }

    $previewFileName = (string) ($photo['preview_file_name'] ?? '');
    if (
        $previewFileName !== ''
        && basename($previewFileName) === $previewFileName
        && preg_match('/^[A-Za-z0-9._-]+$/', $previewFileName) === 1
        && media_file_exists($config, $previewFileName)
    ) {
        $videoPosterUrl = media_public_file_url($config, $previewFileName);
    }
}
$photoDate = trim((string) ($photo['photo_date'] ?? ''));
if ($photoDate === '') {
    $photoDate = substr((string) ($photo['created_at'] ?? ''), 0, 10);
}

$focusX = max(0, min(100, (int) ($photo['card_focus_x'] ?? 50)));
$focusY = max(0, min(100, (int) ($photo['card_focus_y'] ?? 50)));
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($photo['title']) ?> | Love Gallery</title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= urlencode((string) filemtime(__DIR__ . '/assets/style.css')) ?>">
</head>
<body>
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

    <section class="photo-view animate-fade-up">
        <a class="back-link" href="/index.php">← Назад на главную</a>
        <div class="photo-view-media">
            <?php if ($mediaType === 'video'): ?>
                <video controls preload="auto" playsinline data-preload-full="1"<?= $videoPosterUrl ? ' poster="' . esc($videoPosterUrl) . '"' : '' ?>>
                    <source src="<?= esc($imageUrl) ?>"<?= $videoSourceType ? ' type="' . esc($videoSourceType) . '"' : '' ?>>
                    Ваш браузер не поддерживает воспроизведение видео.
                </video>
            <?php else: ?>
                <img src="<?= esc($imageUrl) ?>" alt="<?= esc($photo['title']) ?>">
            <?php endif; ?>
        </div>
        <div class="photo-view-content">
            <h1><?= esc($photo['title']) ?></h1>
            <p><?= nl2br(esc((string) $photo['description'])) ?></p>
            <?php if (!empty($photo['tags'])): ?>
                <div class="tag-list"><?php foreach (explode(',', (string) $photo['tags']) as $tagItem): ?><?php $tagItem = trim($tagItem); ?><?php if ($tagItem !== ''): ?><span class="tag-chip">#<?= esc($tagItem) ?></span><?php endif; ?><?php endforeach; ?></div>
            <?php endif; ?>
            <?php if ($photoDate !== ''): ?>
                <time><?= esc($photoDate) ?></time>
            <?php endif; ?>
            <form method="post" class="photo-delete-form" onsubmit="return confirm('Удалить это медиа безвозвратно?');">
                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_media">
                <button type="submit" class="danger-button">Удалить <?= $mediaType === 'video' ? 'видео' : 'фото' ?></button>
            </form>
        </div>
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
