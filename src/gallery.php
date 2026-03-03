<?php

function gallery_lower(string $value): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value);
    }

    return strtolower($value);
}

function gallery_list(PDO $pdo, int $userId): array
{
    $stmt = $pdo->query('SELECT id, title, description, file_name, media_type, preview_file_name, photo_date, tags, card_focus_x, card_focus_y, created_at FROM photos ORDER BY id DESC');
    return $stmt->fetchAll() ?: [];
}

function gallery_get(PDO $pdo, int $userId, int $photoId): ?array
{
    $stmt = $pdo->prepare('SELECT id, title, description, file_name, media_type, preview_file_name, photo_date, tags, card_focus_x, card_focus_y, created_at FROM photos WHERE id = :id LIMIT 1');
    $stmt->execute([
        ':id' => $photoId,
    ]);

    $photo = $stmt->fetch();

    return $photo ?: null;
}

function gallery_search(PDO $pdo, int $userId, string $titleQuery, string $tagQuery, ?string $dateFrom, ?string $dateTo): array
{
    $sql = 'SELECT id, title, description, file_name, media_type, preview_file_name, photo_date, tags, card_focus_x, card_focus_y, created_at FROM photos WHERE 1=1';
    $params = [];

    $titleQuery = trim($titleQuery);
    if ($titleQuery !== '') {
        $sql .= ' AND LOWER(title) LIKE :title_like';
        $params[':title_like'] = '%' . gallery_lower($titleQuery) . '%';
    }

    $tagQuery = trim($tagQuery);
    if ($tagQuery !== '') {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $sql .= " AND FIND_IN_SET(:tag_query, REPLACE(LOWER(COALESCE(tags, '')), ' ', '')) > 0";
            $params[':tag_query'] = gallery_lower($tagQuery);
        } else {
            $sql .= " AND instr(',' || replace(lower(COALESCE(tags, '')), ' ', '') || ',', ',' || :tag_query || ',') > 0";
            $params[':tag_query'] = gallery_lower($tagQuery);
        }
    }

    if ($dateFrom) {
        $sql .= ' AND COALESCE(photo_date, substr(created_at, 1, 10)) >= :date_from';
        $params[':date_from'] = $dateFrom;
    }

    if ($dateTo) {
        $sql .= ' AND COALESCE(photo_date, substr(created_at, 1, 10)) <= :date_to';
        $params[':date_to'] = $dateTo;
    }

    $sql .= ' ORDER BY id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll() ?: [];
}

function gallery_add(PDO $pdo, int $userId, string $title, string $description, string $fileName, ?string $photoDate, string $tags, int $cardFocusX, int $cardFocusY, string $mediaType = 'photo', ?string $previewFileName = null): void
{
    $stmt = $pdo->prepare('INSERT INTO photos (user_id, title, description, file_name, media_type, preview_file_name, photo_date, tags, card_focus_x, card_focus_y) VALUES (:user_id, :title, :description, :file_name, :media_type, :preview_file_name, :photo_date, :tags, :card_focus_x, :card_focus_y)');
    $stmt->execute([
        ':user_id' => $userId,
        ':title' => $title,
        ':description' => $description,
        ':file_name' => $fileName,
        ':media_type' => $mediaType,
        ':preview_file_name' => $previewFileName,
        ':photo_date' => $photoDate,
        ':tags' => $tags,
        ':card_focus_x' => $cardFocusX,
        ':card_focus_y' => $cardFocusY,
    ]);
}

function gallery_delete(PDO $pdo, int $userId, int $photoId): bool
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $sql = 'DELETE FROM photos WHERE user_id = :user_id AND id = :id';

    if ($driver === 'mysql') {
        $sql .= ' LIMIT 1';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':id' => $photoId,
    ]);

    return $stmt->rowCount() > 0;
}
