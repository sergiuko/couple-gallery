<?php

require_once __DIR__ . '/../src/bootstrap.php';

if ($auth->user()) {
    redirect('/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Сессия формы устарела. Попробуйте еще раз.';
    } else {
        $fullName = (string) ($_POST['full_name'] ?? '');
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        $result = $auth->register($fullName, $email, $password);
        if ($result['ok']) {
            flash_set('success', 'Регистрация успешна. Теперь войдите в свой аккаунт.');
            redirect('/login.php');
        }

        $errors = $result['errors'];
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация | Love Gallery</title>
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

<main class="auth-page">
    <section class="auth-card animate-fade-up">
        <span class="badge">Love Gallery</span>
        <h1>Регистрация</h1>
        <p class="auth-muted">Создайте ваше общее пространство для фото.</p>

        <?php foreach ($errors as $error): ?>
            <div class="alert error"><?= esc($error) ?></div>
        <?php endforeach; ?>

        <form method="post" class="upload-form">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">

            <label for="full_name">Имя</label>
            <input id="full_name" name="full_name" type="text" required minlength="2" maxlength="120" autocomplete="name">

            <label for="email">Email</label>
            <input id="email" name="email" type="email" required autocomplete="email">

            <label for="password">Пароль</label>
            <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">

            <button type="submit">Создать аккаунт</button>
        </form>

        <p class="auth-foot">Уже есть аккаунт? <a href="/login.php">Войти</a></p>
    </section>
</main>
</body>
</html>
