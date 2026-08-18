<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';

$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Missing config/config.php - copy config/config.example.php and edit it.');
}

/** @var array $config */
$config = require $configFile;

date_default_timezone_set($config['app']['timezone']);

require APP_ROOT . '/src/db.php';
require APP_ROOT . '/src/helpers.php';
require APP_ROOT . '/src/auth.php';
require APP_ROOT . '/src/league.php';

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('hogline');
    session_start();
}

db_init($config['db']);
