<?php

require_once __DIR__ . '/../src/bootstrap.php';

if ($auth->user()) {
    redirect('/index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Сессия формы устарела. Попробуйте еще раз.';
    } else {
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($auth->login($email, $password)) {
            flash_set('success', 'Рады видеть вас снова 💕');
            redirect('/index.php');
        }

        $error = 'Неверный email или пароль.';
    }
}

$success = flash_get('success');
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход | Love Gallery</title>
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
        <h1>Вход</h1>
        <p class="auth-muted">Войдите, чтобы просматривать и добавлять ваши фото.</p>

        <?php if ($success): ?>
            <div class="alert success"><?= esc($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error"><?= esc($error) ?></div>
        <?php endif; ?>

        <form method="post" class="upload-form">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">

            <label for="email">Email</label>
            <input id="email" name="email" type="email" required autocomplete="email">

            <label for="password">Пароль</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">

            <button type="submit">Войти</button>
        </form>

        <p class="auth-foot">Нет аккаунта? <a href="/register.php">Создать аккаунт</a></p>
    </section>
</main>
</body>
</html>
