<?php
/**
 * Arranque de la aplicación.
 *
 * Registra el autocargador PSR-4 propio (sin Composer), carga las
 * variables de entorno y construye el contenedor de servicios.
 */

declare(strict_types=1);

use KitsuneNotes\Core\App;
use KitsuneNotes\Core\Env;

define('KITSUNE_ROOT', dirname(__DIR__));

// ---------------------------------------------------------------------
// Autocargador PSR-4: KitsuneNotes\Foo\Bar -> src/Foo/Bar.php
// ---------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    $prefix = 'KitsuneNotes\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file     = KITSUNE_ROOT . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

// ---------------------------------------------------------------------
// Entorno y configuración
// ---------------------------------------------------------------------
Env::load(KITSUNE_ROOT . '/.env');

/** @var array<string, mixed> $config */
$config = require KITSUNE_ROOT . '/config/config.php';

date_default_timezone_set((string) ($config['app']['timezone'] ?? 'Europe/Madrid'));
mb_internal_encoding('UTF-8');
setlocale(LC_ALL, 'es_ES.UTF-8', 'es_ES', 'Spanish_Spain');

$debug = (bool) ($config['app']['debug'] ?? false);
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

if (!is_dir(KITSUNE_ROOT . '/storage/logs')) {
    @mkdir(KITSUNE_ROOT . '/storage/logs', 0775, true);
}

ini_set('error_log', KITSUNE_ROOT . '/storage/logs/php-error.log');

return new App($config, KITSUNE_ROOT);
