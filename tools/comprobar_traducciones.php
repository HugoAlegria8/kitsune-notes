<?php
/**
 * Kitsune Notes · comprobación de las traducciones.
 *
 * Uso:
 *   php tools/comprobar_traducciones.php            Resumen y errores.
 *   php tools/comprobar_traducciones.php --detalle  Añade los avisos (claves sin usar, etc.).
 *
 * Qué comprueba, para cada idioma que no es el original:
 *   1. Que todo texto que el código pasa a t(), th(), tn() o al traductor
 *      tiene su traducción en lang/<idioma>/*.php.
 *   2. Que los textos que no están escritos en el punto de la llamada
 *      (los que salen de la configuración, de los estados del pedido, del
 *      validador…) también la tienen.
 *   3. Que la traducción conserva los mismos marcadores {entre llaves}.
 *   4. Que dos ficheros del catálogo no traducen el mismo texto de dos
 *      maneras distintas.
 *
 * Termina con código 1 si hay algún error, para poder usarlo antes de
 * subir cambios al repositorio.
 *
 * Solo lee ficheros: no toca la base de datos ni necesita el servidor.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse desde la línea de comandos.\n");
}

use KitsuneNotes\Core\Validator;
use KitsuneNotes\Repository\OrderRepository;
use KitsuneNotes\Repository\SupportRepository;
use KitsuneNotes\Service\EventRecorder;
use KitsuneNotes\Service\PaymentSimulator;

/** @var \KitsuneNotes\Core\App $app */
$app  = require dirname(__DIR__) . '/src/bootstrap.php';
$root = $app->rootDir();

$detalle    = in_array('--detalle', $argv, true);
$translator = $app->translator();

// ---------------------------------------------------------------------
// 1. Textos escritos en el código: t('…'), th('…'), tn('…', '…'), …
// ---------------------------------------------------------------------

/** Ficheros de plantillas que solo existen en español (back-office y herramientas del equipo). */
$soloEspanol = static function (string $relative): bool {
    return str_starts_with($relative, 'views/admin/')
        || $relative === 'views/layout/admin.php'
        || str_starts_with($relative, 'views/mail/prueba-smtp');
};

$files = [$root . '/public/index.php'];

foreach (['views', 'src'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);

/** Valor de un literal de cadena de PHP tal como lo entrega el analizador léxico. */
$literal = static function (string $token): string {
    $body = substr($token, 1, -1);

    return $token[0] === "'"
        ? str_replace(["\\\\", "\\'"], ["\\", "'"], $body)
        : stripcslashes($body);
};

/** @var array<string, list<string>> $used texto => lugares donde se usa */
$used         = [];
/** @var list<string> $concatenated llamadas cuyo texto está partido con «.» */
$concatenated = [];
$dynamicCalls = 0;

foreach ($files as $path) {
    $relative = ltrim(str_replace($root, '', $path), '/');

    if ($soloEspanol($relative)) {
        continue;
    }

    // Se descartan espacios y comentarios para mirar los tokens seguidos.
    $tokens = array_values(array_filter(
        token_get_all((string) file_get_contents($path)),
        static fn ($token): bool => !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
    ));

    $text = static fn (int $i): string => isset($tokens[$i]) ? (is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i]) : '';
    $line = static function (int $i) use ($tokens): int {
        for ($j = $i; $j >= 0; $j--) {
            if (is_array($tokens[$j])) {
                return (int) $tokens[$j][2];
            }
        }

        return 0;
    };

    foreach ($tokens as $i => $token) {
        if ($text($i + 1) !== '(') {
            continue;
        }

        $name      = $text($i);
        $arguments = 0;

        if (is_array($token) && $token[0] === T_STRING && $text($i - 1) === '->') {
            if (in_array($name, ['t', 'th'], true)) {
                $arguments = 1;
            } elseif ($name === 'tn') {
                $arguments = 2;
            } elseif (in_array($name, ['get', 'choice'], true)) {
                // Solo cuentan las llamadas al traductor: $translator->get(),
                // $this->translator->get(), $app->translator()->get()…
                $receiver = [$text($i - 2), $text($i - 3), $text($i - 4)];

                if (in_array('translator', $receiver, true) || in_array('$translator', $receiver, true)) {
                    $arguments = $name === 'choice' ? 2 : 1;
                }
            }
        } elseif (is_array($token) && $token[0] === T_VARIABLE && in_array($name, ['$translate', '$tr'], true)) {
            $arguments = 1;
        }

        if ($arguments === 0) {
            continue;
        }

        $position = $i + 2;

        for ($argument = 1; $argument <= $arguments; $argument++) {
            $candidate = $tokens[$position] ?? null;

            if (!is_array($candidate) || $candidate[0] !== T_CONSTANT_ENCAPSED_STRING) {
                $dynamicCalls++;
                break;
            }

            $after = $text($position + 1);

            if ($after === '.') {
                $concatenated[] = sprintf('%s:%d', $relative, $line($position));
                break;
            }

            $used[$literal($candidate[1])][] = sprintf('%s:%d', $relative, $line($position));

            if ($after !== ',') {
                break;
            }

            $position += 2;
        }
    }
}

// ---------------------------------------------------------------------
// 2. Textos que no están escritos en el punto de la llamada
// ---------------------------------------------------------------------

/** @var array<string, string> $dynamic texto => de dónde sale */
$dynamic = [];

foreach ((array) $app->config('commerce.shipping', []) as $key => $method) {
    $dynamic[(string) $method['label']]       = "config: commerce.shipping.{$key}.label";
    $dynamic[(string) $method['description']] = "config: commerce.shipping.{$key}.description";
}

$dynamic[(string) $app->config('app.academic_notice')] = 'config: app.academic_notice';
$dynamic[(string) $app->config('company.country')]     = 'config: company.country';
$dynamic[(string) $app->config('company.fictional')]   = 'config: company.fictional';

foreach (Validator::MESSAGES as $rule => $message) {
    $dynamic[$message] = "Validator::MESSAGES[{$rule}]";
}

foreach (SupportRepository::TYPES as $type => $label) {
    $dynamic[$label] = "SupportRepository::TYPES[{$type}]";
}

foreach (OrderRepository::STATUSES as $status) {
    $dynamic[$app->view()->statusBadge($status)['label']] = "estado del pedido «{$status}»";
}

// La página «Aviso académico» publica el catálogo de eventos traducido.
foreach (EventRecorder::CATALOG as $name => $description) {
    $dynamic[$description] = "EventRecorder::CATALOG[{$name}]";
}

foreach (PaymentSimulator::testCards() as $card) {
    $dynamic[(string) $card['description']] = 'PaymentSimulator::testCards()';
}

foreach ((new ReflectionClass(PaymentSimulator::class))->getConstant('TEST_CARDS') as [, $reason]) {
    if ($reason !== '') {
        $dynamic[(string) $reason] = 'PaymentSimulator: motivo de rechazo';
    }
}

// Notas de la cronología del pedido: se guardan en español y se traducen al
// mostrarlas al cliente (View::historyNote). Las cuatro últimas son las de
// los pedidos de demostración.
foreach ([
    'Pedido generado desde el checkout.',
    'Pago simulado autorizado con código {codigo}.',
    'Incidencia {referencia} comunicada por el cliente.',
    'El cliente cambió el carrito o la moneda antes de pagar: se sustituye por un pedido nuevo.',
    'Pago simulado autorizado.',
    'Pedido aceptado por el almacén.',
    'Entregado al transportista (envío simulado).',
    'En cola de preparación.',
    'La clienta comunica que falta un artículo en el envío.',
] as $note) {
    $dynamic[$note] = 'nota de la cronología del pedido';
}

unset($dynamic['']);

// ---------------------------------------------------------------------
// 3. Comprobación de cada idioma
// ---------------------------------------------------------------------

$placeholders = static function (string $text): array {
    preg_match_all('/\{([a-z_]+)\}/', $text, $matches);
    $found = array_unique($matches[1]);
    sort($found);

    return $found;
};

$errors = 0;

printf("Kitsune Notes · comprobación de traducciones\n");
printf("--------------------------------------------\n");
printf("Textos en el código: %d distintos (%d llamadas con texto calculado, que no se pueden revisar aquí)\n", count($used), $dynamicCalls);
printf("Textos de configuración, estados y servicios: %d\n", count($dynamic));

if ($concatenated !== []) {
    $errors += count($concatenated);
    printf("\n[ERROR] Textos partidos con «.»: escribe la frase en un solo literal para que sirva de clave.\n");

    foreach ($concatenated as $place) {
        printf("  - %s\n", $place);
    }
}

foreach (array_keys($translator->locales()) as $locale) {
    if ($locale === $translator->defaultLocale()) {
        continue;
    }

    printf("\nIdioma «%s»\n", $locale);

    // Cada fichero por separado, para detectar traducciones contradictorias.
    $catalog   = [];
    $origin    = [];
    $conflicts = [];
    $fragments = glob($root . '/lang/' . $locale . '/*.php') ?: [];
    sort($fragments);

    foreach ($fragments as $fragment) {
        $entries = require $fragment;

        foreach ((array) $entries as $key => $value) {
            $key = (string) $key;

            if (isset($catalog[$key]) && $catalog[$key] !== $value) {
                $conflicts[] = sprintf('«%s»: %s y %s', $key, $origin[$key], basename($fragment));
            }

            $catalog[$key] = (string) $value;
            $origin[$key]  = basename($fragment);
        }
    }

    printf("  Catálogo: %d entradas en %d ficheros\n", count($catalog), count($fragments));

    $missing = [];

    foreach ($used as $key => $places) {
        if (!array_key_exists($key, $catalog)) {
            $missing[$key] = implode(', ', array_slice($places, 0, 2));
        }
    }

    foreach ($dynamic as $key => $source) {
        if (!array_key_exists($key, $catalog)) {
            $missing[$key] = $source;
        }
    }

    $broken = [];

    foreach ($catalog as $key => $value) {
        if ($placeholders($key) !== $placeholders($value)) {
            $broken[] = sprintf('«%s» (%s)', $key, $origin[$key]);
        }
    }

    foreach ([
        'Sin traducción'                          => $missing,
        'Marcadores {…} distintos en la traducción' => $broken,
        'Traducido de dos maneras distintas'      => $conflicts,
    ] as $title => $items) {
        if ($items === []) {
            continue;
        }

        $errors += count($items);
        printf("\n  [ERROR] %s: %d\n", $title, count($items));

        foreach ($items as $key => $item) {
            printf("    - %s\n", is_string($key) ? "«{$key}»  ← {$item}" : $item);
        }
    }

    if ($missing === [] && $broken === [] && $conflicts === []) {
        printf("  Correcto: no falta ninguna traducción.\n");
    }

    // Avisos: no son errores, pero conviene mirarlos de vez en cuando.
    $unused = array_values(array_filter(
        array_keys($catalog),
        static fn (string $key): bool => !isset($used[$key]) && !isset($dynamic[$key])
    ));
    $same = array_values(array_filter(
        array_keys($catalog),
        static fn (string $key): bool => $catalog[$key] === $key && preg_match('/\p{L}{4,}/u', $key) === 1
    ));

    printf("  Avisos: %d entradas que el código no usa y %d iguales al original", count($unused), count($same));
    printf($detalle ? ":\n" : " (ver con --detalle).\n");

    if ($detalle) {
        foreach ($unused as $key) {
            printf("    · sin usar: «%s» (%s)\n", $key, $origin[$key]);
        }

        foreach ($same as $key) {
            printf("    · igual al original: «%s» (%s)\n", $key, $origin[$key]);
        }
    }
}

printf("\nRESULTADO: %s\n", $errors === 0 ? 'todo correcto' : "{$errors} error(es)");

exit($errors === 0 ? 0 : 1);
