<?php

declare(strict_types=1);

/**
 * Instalador de Kitsune Gestión.
 *
 *   php bin/instalar.php           crea el .env (si falta) con claves aleatorias y la base de datos
 *   php bin/instalar.php --fresh   borra la base de datos y la crea de nuevo (vacía)
 *
 * El back-office empieza vacío a propósito: sus datos llegan por la
 * integración, no se cargan a mano.
 */

$raiz = dirname(__DIR__);

if (!is_file($raiz . '/.env')) {
    $plantilla = (string) file_get_contents($raiz . '/.env.example');
    $plantilla = preg_replace('/^KG_CLAVE_N8N=$/m', 'KG_CLAVE_N8N=' . bin2hex(random_bytes(24)), $plantilla);
    $plantilla = preg_replace('/^KG_CLAVE_AGENTE=$/m', 'KG_CLAVE_AGENTE=' . bin2hex(random_bytes(24)), (string) $plantilla);
    $plantilla = preg_replace('/^KG_N8N_SECRETO=$/m', 'KG_N8N_SECRETO=' . bin2hex(random_bytes(24)), (string) $plantilla);
    file_put_contents($raiz . '/.env', $plantilla);
    echo "· Creado .env con claves aleatorias (no lo subas a GitHub).\n";
}

require $raiz . '/src/bootstrap.php';

use KitsuneGestion\Core\App;
use KitsuneGestion\Core\Env;

$ruta = Env::get('KG_DB_PATH', $raiz . '/storage/gestion.sqlite');

if (in_array('--fresh', $argv, true) && is_file((string) $ruta)) {
    foreach ([$ruta, $ruta . '-wal', $ruta . '-shm'] as $fichero) {
        if (is_file((string) $fichero)) {
            unlink((string) $fichero);
        }
    }

    echo "· Base de datos anterior borrada.\n";
}

$app = new App($raiz);
$app->db();

echo "· Base de datos lista en " . $ruta . "\n";
echo "· Usuario del equipo: " . Env::get('KG_ADMIN_EMAIL', 'gestion@kitsunenotes.test') . " (contraseña: la de KG_ADMIN_PASSWORD)\n";
echo "· Copia KG_CLAVE_N8N y KG_CLAVE_AGENTE del .env en las credenciales de n8n (ver integracion/README.md).\n";
