<?php
/**
 * Instalador: crea el esquema y carga los datos de prueba.
 *
 * Uso:
 *   php bin/install.php           Crea la base de datos si no existe.
 *   php bin/install.php --fresh   Borra la base de datos y la recrea.
 *
 * Solo se ejecuta desde la línea de comandos.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse desde la línea de comandos.\n");
}

/** @var \KitsuneNotes\Core\App $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

require dirname(__DIR__) . '/database/seed.php';

$fresh   = in_array('--fresh', $argv, true);
$driver  = (string) $app->config('database.driver');
$root    = $app->rootDir();

echo "Kitsune Notes · instalador\n";
echo "--------------------------------------------\n";
echo "Motor de base de datos: {$driver}\n";

if ($driver === 'sqlite') {
    $path = (string) $app->config('database.sqlite.path');

    if ($fresh) {
        foreach ([$path, $path . '-wal', $path . '-shm'] as $file) {
            if (is_file($file)) {
                unlink($file);
                echo "Eliminado: " . basename($file) . "\n";
            }
        }
    } elseif (is_file($path)) {
        echo "\nYa existe una base de datos en:\n  {$path}\n";
        echo "Ejecuta «php bin/install.php --fresh» si quieres recrearla desde cero.\n";
        exit(0);
    }

    echo "Fichero de base de datos: {$path}\n";
}

$pdo = $app->pdo();

// --- Esquema ---------------------------------------------------------
$schemaFile = $driver === 'mysql'
    ? $root . '/database/schema.mysql.sql'
    : $root . '/database/schema.sql';

$schema = file_get_contents($schemaFile);

if ($schema === false) {
    exit("No se ha podido leer el esquema: {$schemaFile}\n");
}

if ($driver === 'mysql' && $fresh) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['events', 'mail_outbox', 'invoices', 'support_tickets', 'order_status_history', 'payments', 'order_lines',
              'orders', 'staff_users', 'customers', 'coupons', 'products', 'design_lines', 'categories'] as $table) {
        $pdo->exec("DROP TABLE IF EXISTS {$table}");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// Se eliminan los comentarios antes de trocear por «;», de modo que un
// bloque de comentarios previo a una sentencia no la anule.
$sinComentarios = implode("\n", array_filter(
    array_map('rtrim', explode("\n", $schema)),
    static fn (string $line): bool => !str_starts_with(ltrim($line), '--')
));

foreach (array_filter(array_map('trim', explode(';', $sinComentarios))) as $statement) {
    $pdo->exec($statement);
}

echo "Esquema creado correctamente.\n";

// --- Datos de prueba -------------------------------------------------
$adminPassword = (string) $app->config('security.admin_password');

kitsune_seed($pdo, $adminPassword, $app);

$products  = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$orders    = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$customers = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$invoices  = (int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
$emails    = (int) $pdo->query('SELECT COUNT(*) FROM mail_outbox')->fetchColumn();

echo "Datos de prueba cargados:\n";
echo "  · {$products} productos\n";
echo "  · {$customers} clientes de demostración\n";
echo "  · {$orders} pedidos históricos de ejemplo\n";
echo "  · {$invoices} facturas y {$emails} correos en el buzón de pruebas\n";
echo "--------------------------------------------\n";
echo "Back-office: admin@kitsunenotes.test / {$adminPassword}\n";
echo "Arranca el servidor con:  php -S localhost:8000 -t public\n";
