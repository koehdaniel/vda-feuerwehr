<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('STORAGE_DIR', APP_ROOT . '/storage');
define('TMP_DIR', STORAGE_DIR . '/tmp');
define('CERT_DIR', STORAGE_DIR . '/bescheinigungen');

date_default_timezone_set('Europe/Berlin');

if (!is_file(APP_ROOT . '/config/config.php')) {
    http_response_code(500);
    exit('config/config.php fehlt – bitte aus config/config.sample.php anlegen.');
}

require APP_ROOT . '/vendor/autoload.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/pdf.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/einsatz.php';

if (PHP_SAPI !== 'cli') {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('vda_session');
    session_start();
}
