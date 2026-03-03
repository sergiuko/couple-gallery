<?php

require_once __DIR__ . '/../src/bootstrap.php';

$user = $auth->user();
if (!$user) {
    redirect('/login.php');
}

$uploadDir = media_storage_dir($config);
$titleQuery = trim((string) ($_GET['q'] ?? ''));
$titleQuery = preg_replace('/\?q=.*/u', '', $titleQuery) ?? $titleQuery;
$tagQuery = trim((string) ($_GET['tag'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));

$parseDate = static function (string $date): ?string {
    if ($date === '') {
        return null;
    }

    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    $errors = DateTime::getLastErrors();
    $hasErrors = is_array($errors)
        ? (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)
        : false;

    if (!$parsed || $hasErrors || $parsed->format('Y-m-d') !== $date) {
        return null;
    }

    return $date;
};

$fromDate = $parseDate($dateFrom);
$toDate = $parseDate($dateTo);

if ($dateFrom !== '' && $fromDate === null) {
    flash_set('error', 'Неверная дата "от".');
    redirect('/search.php');
}

if ($dateTo !== '' && $toDate === null) {
    flash_set('error', 'Неверная дата "до".');
    redirect('/search.php');
}

if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
    flash_set('error', 'Дата "от" не может быть больше даты "до".');
    redirect('/search.php');
}

$photos = gallery_list($pdo, (int) $user['id']);
$error = flash_get('error');

$displayDate = static function (array $photo): string {
    $photoDate = trim((string) ($photo['photo_date'] ?? ''));
    if ($photoDate !== '') {
        return $photoDate;
    }

    return substr((string) ($photo['created_at'] ?? ''), 0, 10);
};

$getTags = static function (array $photo): array {
    $raw = trim((string) ($photo['tags'] ?? ''));
    if ($raw === '') {
        return [];
    }

    $parts = explode(',', $raw);
    $tags = [];

    foreach ($parts as $part) {
        $tag = trim($part);
        if ($tag !== '') {
            $tags[] = $tag;
        }
    }

    return $tags;
};

$normalize = static function (string $value): string {
    $value = trim($value);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value);
    }

    return strtolower($value);
};

$isTextMatch = static function (string $text, string $query) use ($normalize): bool {
    $query = $normalize($query);
    if ($query === '') {
        return true;
    }

    $text = $normalize($text);
    $tokens = preg_split('/\s+/', $query) ?: [];

    foreach ($tokens as $token) {
        if ($token === '') {
            continue;
        }

        $hasToken = function_exists('mb_strpos')
            ? (mb_strpos($text, $token) !== false)
            : (strpos($text, $token) !== false);

        if (!$hasToken) {
            return false;
        }
    }

    return true;
};

$isTagMatch = static function (array $tags, string $query) use ($normalize): bool {
    $query = $normalize($query);
    if ($query === '') {
        return true;
    }

    $queryTokens = preg_split('/[\s,;]+/u', $query) ?: [];
    $queryTokens = array_values(array_filter($queryTokens, static fn(string $token): bool => $token !== ''));

    if ($queryTokens === []) {
        return true;
    }

    $normalizedTags = array_map(static fn(string $tag) => $normalize($tag), $tags);

    foreach ($queryTokens as $token) {
        foreach ($normalizedTags as $tagValue) {
            $hasToken = function_exists('mb_strpos')
                ? (mb_strpos($tagValue, $token) !== false)
                : (strpos($tagValue, $token) !== false);

            if ($hasToken) {
                return true;
            }
        }
    }

    return false;
};

$allPhotos = [];
$initialPhotos = [];

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

    $cardFileName = $fileName;
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

        $cardFileName = $previewFileName;
    }

    $mapped = [
        'id' => (int) ($photoRow['id'] ?? 0),
        'title' => (string) ($photoRow['title'] ?? ''),
        'description' => (string) ($photoRow['description'] ?? ''),
        'imageUrl' => media_public_file_url($config, $cardFileName),
        'mediaType' => $mediaType,
        'displayDate' => $displayDate($photoRow),
        'tags' => $getTags($photoRow),
        'focusX' => max(0, min(100, (int) ($photoRow['card_focus_x'] ?? 50))),
        'focusY' => max(0, min(100, (int) ($photoRow['card_focus_y'] ?? 50))),
    ];

    $allPhotos[] = $mapped;

    $searchText = $mapped['title'] . ' ' . $mapped['description'] . ' ' . implode(' ', $mapped['tags']);
    $titleOk = $isTextMatch($searchText, $titleQuery);
    $tagOk = $isTagMatch($mapped['tags'], $tagQuery);

    $dateOk = true;
    if ($fromDate !== null && $mapped['displayDate'] < $fromDate) {
        $dateOk = false;
    }

    if ($toDate !== null && $mapped['displayDate'] > $toDate) {
        $dateOk = false;
    }

    if ($titleOk && $tagOk && $dateOk) {
        $initialPhotos[] = $mapped;
    }
}

$photosJson = json_encode(
    $allPhotos,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
);
if (!is_string($photosJson)) {
    $photosJson = '[]';
}

function render_search_cards(array $photos): void
{
    if (!$photos): ?>
        <div class="empty-state">Ничего не найдено по текущим параметрам.</div>
    <?php else: ?>
        <div class="gallery-grid">
            <?php foreach ($photos as $photo): ?>
                <article class="photo-card">
                    <a class="card-image-wrap" href="/photo.php?id=<?= (int) $photo['id'] ?>">
                        <img src="<?= esc((string) $photo['imageUrl']) ?>" alt="<?= esc((string) $photo['title']) ?>" loading="lazy" style="object-position: <?= (int) $photo['focusX'] ?>% <?= (int) $photo['focusY'] ?>%;">
                    </a>
                    <div class="card-content">
                        <h3><?= esc((string) $photo['title']) ?></h3>
                        <p><?= esc((string) $photo['description']) ?></p>
                        <?php if (!empty($photo['tags']) && is_array($photo['tags'])): ?>
                            <div class="tag-list">
                                <?php foreach ($photo['tags'] as $tag): ?>
                                    <span class="tag-chip">#<?= esc((string) $tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <p class="media-type-label"><?= (string) ($photo['mediaType'] ?? 'photo') === 'video' ? 'Видео' : 'Фото' ?></p>
                        <time><?= esc((string) $photo['displayDate']) ?></time>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif;
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поиск медиа | Love Gallery</title>
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

<main class="page search-page">
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

    <header class="hero animate-fade-up">
        <span class="badge">Search</span>
        <h1>Поиск медиа</h1>
        <p>Обновление результатов происходит в реальном времени при вводе или удалении текста.</p>
    </header>

    <?php if ($error): ?>
        <div class="alert error"><?= esc($error) ?></div>
    <?php endif; ?>

    <form method="get" id="liveSearchForm" class="panel search-unified animate-fade-up delay-1">
        <div class="search-top-row">
            <input id="q" name="q" type="text" value="<?= esc($titleQuery) ?>" placeholder="Введите название медиа...">
            <button type="button" class="filter-icon-btn" id="toggleFiltersBtn" aria-expanded="false" aria-controls="searchFiltersDropdown" title="Фильтры">⚙</button>
        </div>

        <p class="search-hint" id="searchLiveHint">Фильтры применяются мгновенно после изменений.</p>

        <div class="search-filters-dropdown is-collapsed" id="searchFiltersDropdown">
            <label for="tag">Тег</label>
            <input id="tag" name="tag" type="text" value="<?= esc($tagQuery) ?>" placeholder="наприклад: море">

            <label for="date_from">Дата от</label>
            <input id="date_from" name="date_from" type="date" value="<?= esc($dateFrom) ?>">

            <label for="date_to">Дата до</label>
            <input id="date_to" name="date_to" type="date" value="<?= esc($dateTo) ?>">

            <div class="filter-actions">
                <button type="button" id="clearFiltersBtn">Сбросить фильтры</button>
            </div>
        </div>
    </form>

    <section class="gallery-section search-results-section animate-fade-up delay-2">
        <div class="section-head">
            <h2>Результаты</h2>
            <span id="searchResultsCount"><?= count($initialPhotos) ?> элементов</span>
        </div>

        <div id="searchResultsContainer">
            <?php render_search_cards($initialPhotos); ?>
        </div>
    </section>
</main>

<script id="searchData" type="application/json"><?= $photosJson ?></script>
<script src="/assets/app.js?v=<?= urlencode((string) filemtime(__DIR__ . '/assets/app.js')) ?>"></script>
</body>
</html>
