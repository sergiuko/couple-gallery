<?php

$config = require __DIR__ . '/config.php';
$debugMode = (bool) ($config['app']['debug'] ?? false);

if ($debugMode) {
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
} else {
	error_reporting(E_ALL);
	ini_set('display_errors', '0');
}

$renderFatalPage = static function (string $title, string $message, int $statusCode, bool $showDetails = false, ?string $details = null): void {
	if (!headers_sent()) {
		http_response_code($statusCode);
		header('Content-Type: text/html; charset=UTF-8');
	}

	$titleSafe = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$messageSafe = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$detailsBlock = '';

	if ($showDetails && $details) {
		$detailsSafe = htmlspecialchars($details, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		$detailsBlock = '<pre style="white-space:pre-wrap;background:#111827;color:#e5e7eb;padding:12px;border-radius:10px;overflow:auto;">' . $detailsSafe . '</pre>';
	}

	echo '<!doctype html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $titleSafe . '</title></head><body style="margin:0;font-family:Segoe UI,Arial,sans-serif;background:#0b1020;color:#f4f6ff;display:grid;place-items:center;min-height:100vh;padding:20px;"><div style="max-width:720px;width:100%;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.16);border-radius:14px;padding:18px;"><h1 style="margin:0 0 10px;font-size:26px;">' . $titleSafe . '</h1><p style="margin:0 0 14px;color:#c8cee8;">' . $messageSafe . '</p>' . $detailsBlock . '<p style="margin:14px 0 0;"><a href="/index.php" style="color:#ffd3ea;">Вернуться на главную</a></p></div></body></html>';
};

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
	if (!(error_reporting() & $severity)) {
		return false;
	}

	throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception) use ($debugMode, $renderFatalPage): void {
	error_log('[CoupleGallery] ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());

	$details = $exception->getMessage() . "\n" . $exception->getFile() . ':' . $exception->getLine() . "\n\n" . $exception->getTraceAsString();
	$renderFatalPage(
		'Ошибка приложения',
		$debugMode
			? 'Возникла ошибка выполнения. Подробности ниже.'
			: 'Произошла внутренняя ошибка. Попробуйте обновить страницу через несколько секунд.',
		500,
		$debugMode,
		$details
	);
	exit;
});

register_shutdown_function(static function () use ($debugMode, $renderFatalPage): void {
	$lastError = error_get_last();
	if (!is_array($lastError)) {
		return;
	}

	$fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
	if (!in_array((int) ($lastError['type'] ?? 0), $fatalTypes, true)) {
		return;
	}

	error_log('[CoupleGallery] Fatal: ' . (string) ($lastError['message'] ?? 'unknown') . ' in ' . (string) ($lastError['file'] ?? 'unknown') . ':' . (int) ($lastError['line'] ?? 0));

	$details = (string) ($lastError['message'] ?? '') . "\n" . (string) ($lastError['file'] ?? '') . ':' . (int) ($lastError['line'] ?? 0);
	$renderFatalPage(
		'Критическая ошибка',
		$debugMode
			? 'Фатальная ошибка PHP. Подробности ниже.'
			: 'Сервер временно недоступен. Пожалуйста, попробуйте снова чуть позже.',
		500,
		$debugMode,
		$details
	);
});

$httpsValue = (string) ($_SERVER['HTTPS'] ?? '');
$forwardedProto = (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
$secure = (
	($httpsValue !== '' && strtolower($httpsValue) !== 'off')
	|| strtolower($forwardedProto) === 'https'
);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

session_name($config['app']['session_name']);
session_set_cookie_params([
	'lifetime' => 0,
	'path' => '/',
	'domain' => '',
	'secure' => $secure,
	'httponly' => true,
	'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/gallery.php';
require_once __DIR__ . '/Auth.php';

$pdo = db_connect($config['db']);
$auth = new Auth($pdo);
