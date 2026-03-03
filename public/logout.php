<?php

require_once __DIR__ . '/../src/bootstrap.php';

if (!is_post()) {
	if ($auth->user()) {
		$auth->logout();
		flash_set('success', 'Вы вышли из аккаунта.');
		redirect('/login.php');
	}

	redirect('/login.php');
}

if (!csrf_validate($_POST['csrf_token'] ?? null)) {
	if ($auth->user()) {
		$auth->logout();
		flash_set('success', 'Вы вышли из аккаунта.');
		redirect('/login.php');
	}

	flash_set('error', 'Неверный запрос на выход из аккаунта.');
	redirect('/index.php');
}

$auth->logout();
flash_set('success', 'Вы вышли из аккаунта.');
redirect('/login.php');
