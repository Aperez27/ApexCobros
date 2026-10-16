<?php
/**
 * Arranque común de la aplicación.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

// Autoload PSR-4 propio: App\Core\Database -> app/core/Database.php
spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $parts = explode('\\', substr($class, 4));
    $file  = array_pop($parts);
    $dir   = strtolower(implode('/', $parts));
    $path  = APP_PATH . '/' . ($dir !== '' ? $dir . '/' : '') . $file . '.php';
    if (is_file($path)) {
        require $path;
    }
});

App\Core\Env::load(BASE_PATH . '/.env');

$config = require APP_PATH . '/config/config.php';
App\Core\Config::set($config);

date_default_timezone_set($config['app']['timezone']);

if ($config['app']['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');

require APP_PATH . '/helpers/functions.php';

if (PHP_SAPI !== 'cli') {
    App\Core\Security::sendHeaders();
    if (session_status() === PHP_SESSION_NONE) {
        App\Core\Security::startSession();
    }
}
