<?php

require_once __DIR__ . '/../src/bootstrap.php';

$user = $auth->user();
if (!$user) {
    redirect('/login.php');
}

$uploadDir = __DIR__ . '/uploads';
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

$fileName = (string) ($photo['file_name'] ?? '');
$mediaType = (string) ($photo['media_type'] ?? 'photo');
$isValidName = $fileName !== ''
    && basename($fileName) === $fileName
    && preg_match('/^[A-Za-z0-9._-]+$/', $fileName) === 1;

if (!$isValidName || !is_file($uploadDir . '/' . $fileName)) {
    flash_set('error', 'Файл этого фото недоступен.');
    redirect('/index.php');
}

$imageUrl = 'uploads/' . rawurlencode($fileName);
$videoPosterUrl = null;
if ($mediaType === 'video') {
    $previewFileName = (string) ($photo['preview_file_name'] ?? '');
    if (
        $previewFileName !== ''
        && basename($previewFileName) === $previewFileName
        && preg_match('/^[A-Za-z0-9._-]+$/', $previewFileName) === 1
        && is_file($uploadDir . '/' . $previewFileName)
    ) {
        $videoPosterUrl = 'uploads/' . rawurlencode($previewFileName);
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
                <a class="logout-link" href="/logout.php">Выйти</a>
            </div>
        </details>
    </nav>

    <section class="photo-view animate-fade-up">
        <a class="back-link" href="/index.php">← Назад на главную</a>
        <div class="photo-view-media">
            <?php if ($mediaType === 'video'): ?>
                <video controls preload="metadata" playsinline<?= $videoPosterUrl ? ' poster="' . esc($videoPosterUrl) . '"' : '' ?> style="object-position: <?= $focusX ?>% <?= $focusY ?>%;">
                    <source src="<?= esc($imageUrl) ?>">
                    Ваш браузер не поддерживает воспроизведение видео.
                </video>
            <?php else: ?>
                <img src="<?= esc($imageUrl) ?>" alt="<?= esc($photo['title']) ?>" style="object-position: <?= $focusX ?>% <?= $focusY ?>%;">
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
        </div>
    </section>
</main>

<script src="/assets/app.js?v=<?= urlencode((string) filemtime(__DIR__ . '/assets/app.js')) ?>"></script>
</body>
</html>
