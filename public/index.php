<?php

require_once __DIR__ . '/../src/bootstrap.php';

$user = $auth->user();
if (!$user) {
    redirect('/login.php');
}

$uploadDir = media_storage_dir($config);
$photos = gallery_list($pdo, (int) $user['id']);
$visiblePhotos = [];
$visibleVideos = [];
foreach ($photos as $photoRow) {
    $fileName = (string) ($photoRow['file_name'] ?? '');
    $mediaType = (string) ($photoRow['media_type'] ?? 'photo');

    if (
        $fileName === ''
        || basename($fileName) !== $fileName
        || preg_match('/^[A-Za-z0-9._-]+$/', $fileName) !== 1
        || !media_file_exists($config, $fileName)
    ) {
        continue;
    }

    if ($mediaType === 'video') {
        $previewFileName = (string) ($photoRow['preview_file_name'] ?? '');
        if (
            $previewFileName === ''
            || basename($previewFileName) !== $previewFileName
            || preg_match('/^[A-Za-z0-9._-]+$/', $previewFileName) !== 1
            || !media_file_exists($config, $previewFileName)
        ) {
            continue;
        }

        $visibleVideos[] = $photoRow;
        continue;
    }

    if (
        $mediaType === 'photo'
    ) {
        $visiblePhotos[] = $photoRow;
    }
}

$error = flash_get('error');
$success = flash_get('success');

function build_slider_tracks(array $items): array
{
    $topSlider = $items;
    $bottomSlider = $items;

    if (count($topSlider) > 1) {
        shuffle($topSlider);
        $attempt = 0;

        do {
            $bottomSlider = $items;
            shuffle($bottomSlider);
            $attempt++;
        } while ($bottomSlider === $topSlider && $attempt < 5);

        $offset = random_int(1, count($bottomSlider) - 1);
        for ($index = 0; $index < $offset; $index++) {
            $moved = array_shift($bottomSlider);
            if ($moved !== null) {
                $bottomSlider[] = $moved;
            }
        }
    }

    if ($topSlider !== []) {
        $topSlider = array_merge($topSlider, $topSlider);
    }

    if ($bottomSlider !== []) {
        $bottomSlider = array_merge($bottomSlider, $bottomSlider);
    }

    return [$topSlider, $bottomSlider];
}

[$photoTopSlider, $photoBottomSlider] = build_slider_tracks($visiblePhotos);
[$videoTopSlider, $videoBottomSlider] = build_slider_tracks($visibleVideos);

function photo_display_date(array $photo): string
{
    $photoDate = trim((string) ($photo['photo_date'] ?? ''));
    if ($photoDate !== '') {
        return $photoDate;
    }

    $createdAt = trim((string) ($photo['created_at'] ?? ''));
    if ($createdAt !== '') {
        return substr($createdAt, 0, 10);
    }

    return '';
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Love Gallery</title>
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

    <header class="hero hero-home animate-fade-up">
        <span class="badge">Our Love Space</span>
        <h1>Галерея</h1>
        <p>Автоматический слайдер карточек 4:3. Наведите или нажмите на карточку, чтобы перейти к странице конкретного файла.</p>
    </header>

    <?php if ($error): ?>
        <div class="alert error"><?= esc($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?= esc($success) ?></div>
    <?php endif; ?>

    <section class="gallery-section slider-section animate-fade-up delay-1" id="slider-section">
        <div class="section-head">
            <h2>Карусель воспоминаний</h2>
            <span><?= count($visiblePhotos) ?> фото</span>
        </div>

        <?php if (!$visiblePhotos): ?>
            <div class="empty-state">Пока пусто. Добавьте первое фото на странице <a href="/add.php">добавления</a> ✨</div>
        <?php else: ?>
            <div class="slider-wrap">
                <div class="slider-track slider-track-left" data-slider-track>
                    <?php foreach ($photoTopSlider as $photo): ?>
                        <?php $imageUrl = media_public_file_url($config, (string) $photo['file_name']); ?>
                        <?php $photoDate = photo_display_date($photo); ?>
                        <?php $focusX = max(0, min(100, (int) ($photo['card_focus_x'] ?? 50))); ?>
                        <?php $focusY = max(0, min(100, (int) ($photo['card_focus_y'] ?? 50))); ?>
                        <a class="slider-card" href="/photo.php?id=<?= (int) $photo['id'] ?>">
                            <img src="<?= esc($imageUrl) ?>" alt="<?= esc($photo['title']) ?>" loading="lazy" style="object-position: <?= $focusX ?>% <?= $focusY ?>%;">
                            <span class="slider-overlay">
                                <strong><?= esc($photo['title']) ?></strong>
                                <em><?= esc($photoDate) ?></em>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="slider-track slider-track-right" data-slider-track>
                    <?php foreach ($photoBottomSlider as $photo): ?>
                        <?php $imageUrl = media_public_file_url($config, (string) $photo['file_name']); ?>
                        <?php $photoDate = photo_display_date($photo); ?>
                        <?php $focusX = max(0, min(100, (int) ($photo['card_focus_x'] ?? 50))); ?>
                        <?php $focusY = max(0, min(100, (int) ($photo['card_focus_y'] ?? 50))); ?>
                        <a class="slider-card" href="/photo.php?id=<?= (int) $photo['id'] ?>">
                            <img src="<?= esc($imageUrl) ?>" alt="<?= esc($photo['title']) ?>" loading="lazy" style="object-position: <?= $focusX ?>% <?= $focusY ?>%;">
                            <span class="slider-overlay">
                                <strong><?= esc($photo['title']) ?></strong>
                                <em><?= esc($photoDate) ?></em>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <section class="gallery-section slider-section animate-fade-up delay-2" id="video-slider-section">
        <div class="section-head">
            <h2>Карусель просмотров</h2>
            <span><?= count($visibleVideos) ?> видео</span>
        </div>

        <?php if (!$visibleVideos): ?>
            <div class="empty-state">Пока нет видео. Добавьте первое на странице <a href="/add.php">добавления</a> 🎬</div>
        <?php else: ?>
            <div class="slider-wrap">
                <div class="slider-track slider-track-left" data-slider-track>
                    <?php foreach ($videoTopSlider as $video): ?>
                        <?php $previewUrl = media_public_file_url($config, (string) $video['preview_file_name']); ?>
                        <?php $videoDate = photo_display_date($video); ?>
                        <?php $focusX = max(0, min(100, (int) ($video['card_focus_x'] ?? 50))); ?>
                        <?php $focusY = max(0, min(100, (int) ($video['card_focus_y'] ?? 50))); ?>
                        <a class="slider-card" href="/photo.php?id=<?= (int) $video['id'] ?>">
                            <img src="<?= esc($previewUrl) ?>" alt="<?= esc($video['title']) ?>" loading="lazy" style="object-position: <?= $focusX ?>% <?= $focusY ?>%;">
                            <span class="slider-overlay">
                                <strong><?= esc($video['title']) ?></strong>
                                <em><?= esc($videoDate) ?></em>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="slider-track slider-track-right" data-slider-track>
                    <?php foreach ($videoBottomSlider as $video): ?>
                        <?php $previewUrl = media_public_file_url($config, (string) $video['preview_file_name']); ?>
                        <?php $videoDate = photo_display_date($video); ?>
                        <?php $focusX = max(0, min(100, (int) ($video['card_focus_x'] ?? 50))); ?>
                        <?php $focusY = max(0, min(100, (int) ($video['card_focus_y'] ?? 50))); ?>
                        <a class="slider-card" href="/photo.php?id=<?= (int) $video['id'] ?>">
                            <img src="<?= esc($previewUrl) ?>" alt="<?= esc($video['title']) ?>" loading="lazy" style="object-position: <?= $focusX ?>% <?= $focusY ?>%;">
                            <span class="slider-overlay">
                                <strong><?= esc($video['title']) ?></strong>
                                <em><?= esc($videoDate) ?></em>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>

<script src="/assets/app.js?v=<?= urlencode((string) filemtime(__DIR__ . '/assets/app.js')) ?>"></script>
</body>
</html>
