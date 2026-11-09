<?php

declare(strict_types=1);

/**
 * Arranque de Kitsune Gestión (back-office de la Tarea 2).
 *
 * Es una aplicación independiente de la tienda: tiene su propia base de
 * datos, sus propios usuarios y su propia API. Comparte con la tienda solo
 * el estilo de código (PHP nativo, tres capas y sin dependencias externas).
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'KitsuneGestion\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

KitsuneGestion\Core\Env::load(dirname(__DIR__) . '/.env');

date_default_timezone_set('Europe/Madrid');
