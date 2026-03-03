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
