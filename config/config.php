<?php
/**
 * Configuración de la aplicación.
 *
 * Ningún valor sensible está escrito en el código: todo lo que podría
 * cambiar entre entornos se lee de variables de entorno (fichero .env,
 * no versionado). Los valores por defecto que aparecen aquí son valores
 * de DEMOSTRACIÓN para el prototipo académico y deben sustituirse en
 * cualquier despliegue que no sea de pruebas.
 */

declare(strict_types=1);

use KitsuneNotes\Core\Env;

return [
    'app' => [
        'name'     => Env::get('KN_APP_NAME', 'Kitsune Notes'),
        'env'      => Env::get('KN_APP_ENV', 'local'),
        'debug'    => Env::get('KN_APP_DEBUG', '1') === '1',
        'timezone' => 'Europe/Madrid',
        'locale'   => 'es-ES',
        // URL pública de la tienda, usada para los enlaces absolutos de los
        // correos (p. ej. https://midominio.es/tienda). Si está vacía se deduce
        // de la petición o, en línea de comandos, de http://localhost:8000.
        'url'      => rtrim((string) Env::get('KN_APP_URL', ''), '/'),
        // Aviso obligatorio: la aplicación es un prototipo académico.
        'academic_notice' => 'Prototipo académico sin actividad comercial real. '
            . 'Los productos, precios, pagos y pedidos son ficticios.',
    ],

    'database' => [
        // 'sqlite' (por defecto) o 'mysql'. Ambos se acceden mediante PDO,
        // de modo que el resto de la aplicación no cambia.
        'driver' => Env::get('KN_DB_DRIVER', 'sqlite'),
        'sqlite' => [
            'path' => Env::get('KN_DB_PATH', dirname(__DIR__) . '/storage/database/kitsune.sqlite'),
        ],
        'mysql'  => [
            'host'     => Env::get('KN_DB_HOST', '127.0.0.1'),
            'port'     => Env::get('KN_DB_PORT', '3306'),
            'database' => Env::get('KN_DB_NAME', 'kitsune_notes'),
            'username' => Env::get('KN_DB_USER', ''),
            'password' => Env::get('KN_DB_PASS', ''),
            'charset'  => 'utf8mb4',
        ],
    ],

    'security' => [
        // Contraseña del back-office de demostración. Se puede (y se debe)
        // sobrescribir con KN_ADMIN_PASSWORD en el fichero .env.
        'admin_password' => Env::get('KN_ADMIN_PASSWORD', 'kitsune-demo-2026'),
        // Sal usada para seudonimizar la IP en los eventos: nunca se
        // almacena la dirección IP en claro.
        'event_salt'     => Env::get('KN_EVENT_SALT', 'sal-de-demostracion-cambiar-en-produccion'),
        // Token para el endpoint interno de exportación de eventos.
        'api_token'      => Env::get('KN_API_TOKEN', 'token-demo-tarea2'),
    ],

    // Cookies. La tienda solo usa cookies técnicas (la de sesión, kitsune_session,
    // la que recuerda que se ha leído el aviso de la portada y la que recuerda el
    // idioma cuando el visitante lo cambia), así que el aviso es informativo: no
    // hay nada que aceptar o rechazar. Si algún día se añade analítica o
    // publicidad, habría que sustituirlo por un banner de consentimiento.
    'privacy' => [
        'cookie_notice_name' => 'kitsune_aviso_cookies',
        'cookie_notice_days' => 180,
    ],

    // Idiomas de la tienda. El español es el original: sus textos son la clave
    // de traducción y no necesita catálogo. Cada idioma fija también la moneda
    // en la que se compra. El back-office y el API solo existen en español.
    'i18n' => [
        'default'     => 'es',
        // Cookie técnica que recuerda el idioma elegido con los botones ES/EN.
        'cookie_name' => 'kitsune_idioma',
        'cookie_days' => 180,
        'locales'     => [
            'es' => ['label' => 'Español', 'short' => 'ES', 'html' => 'es',    'currency' => 'EUR'],
            'en' => ['label' => 'English', 'short' => 'EN', 'html' => 'en-GB', 'currency' => 'GBP'],
        ],
    ],

    'commerce' => [
        // Moneda base: en ella están los precios del catálogo, los gastos de
        // envío y los cupones. Los pedidos en otra moneda guardan además su
        // contravalor en esta, para poder sumar ventas de monedas distintas.
        'currency'  => 'EUR',
        // Otras monedas de venta, con su tipo de cambio respecto a la base
        // (unidades de esa moneda por cada euro). Es un tipo FIJO de
        // demostración, no un tipo oficial: se cambia con KN_FX_EUR_GBP y el
        // que se aplica a cada pedido queda guardado en el propio pedido.
        'currencies' => [
            'GBP' => ['rate' => Env::get('KN_FX_EUR_GBP', '0.85')],
        ],
        'tax_rate'  => 0.21,               // IVA general español
        'shipping'  => [
            'estandar' => [
                'label'          => 'Envío estándar',
                'description'    => 'Entrega en 3-5 días laborables (simulado)',
                'price_cents'    => 395,
                'free_from_cents'=> 3500,  // envío gratis a partir de 35 €
            ],
            'express' => [
                'label'           => 'Envío exprés',
                'description'     => 'Entrega en 24-48 h (simulado)',
                'price_cents'     => 695,
                'free_from_cents' => null,
            ],
        ],
        'giftwrap_cents' => 150,           // envoltorio furoshiki opcional
        'max_units_per_line' => 10,
    ],

    // Datos del emisor que figuran en las facturas. Son FICTICIOS: el NIF
    // B00000000 no corresponde a ninguna entidad y el domicilio es inventado.
    // Al emitir una factura se congela una copia, de modo que cambiar estos
    // valores no altera las facturas ya expedidas.
    'company' => [
        'name'        => Env::get('KN_COMPANY_NAME', 'Kitsune Notes Studio S.L.'),
        'tax_id'      => Env::get('KN_COMPANY_TAX_ID', 'B00000000'),
        'address'     => Env::get('KN_COMPANY_ADDRESS', 'Calle del Ejemplo 1, 1.º A'),
        'postal_code' => Env::get('KN_COMPANY_POSTAL_CODE', '30001'),
        'city'        => Env::get('KN_COMPANY_CITY', 'Murcia'),
        'province'    => Env::get('KN_COMPANY_PROVINCE', 'Murcia'),
        'country'     => 'España',
        // Dirección de contacto ficticia que sale en la factura. No se enlaza con
        // KN_MAIL_FROM a propósito: así la cuenta de correo real que use el equipo
        // para el envío no aparece nunca en las facturas que ve cualquier cliente.
        'email'       => Env::get('KN_COMPANY_EMAIL', 'pedidos@kitsunenotes.test'),
        'fictional'   => 'Empresa ficticia: el NIF y el domicilio no corresponden a ninguna entidad real.',
    ],

    // Correo transaccional. Por defecto (KN_MAIL_TRANSPORT=buzon) ningún mensaje
    // sale de la aplicación: se guarda en el buzón de pruebas del back-office.
    // Con KN_MAIL_TRANSPORT=smtp se entrega además por la cuenta de correo del
    // equipo, pero SOLO a las direcciones de KN_MAIL_ALLOWED_TO. Las
    // credenciales se escriben únicamente en el .env local, que no se versiona.
    'mail' => [
        'from_email' => Env::get('KN_MAIL_FROM', 'pedidos@kitsunenotes.test'),
        'from_name'  => Env::get('KN_MAIL_FROM_NAME', 'Kitsune Notes'),
        'transport'  => Env::get('KN_MAIL_TRANSPORT', 'buzon'),
        // Direcciones («a@b.es») o dominios («@b.es») a los que se permite enviar
        // correo real, separados por comas. Vacío = no se envía a nadie.
        'allowed_to' => array_values(array_filter(
            preg_split('/[\s,;]+/', (string) Env::get('KN_MAIL_ALLOWED_TO', '')) ?: []
        )),
        'smtp' => [
            'host'       => Env::get('KN_SMTP_HOST', ''),
            'port'       => (int) Env::get('KN_SMTP_PORT', '587'),
            // tls = STARTTLS (puerto 587) · ssl = TLS implícito (puerto 465) · none = sin cifrar (solo localhost)
            'encryption' => Env::get('KN_SMTP_ENCRYPTION', 'tls'),
            'username'   => Env::get('KN_SMTP_USER', ''),
            // Google muestra las contraseñas de aplicación en cuatro grupos de
            // cuatro caracteres separados por espacios: se aceptan con o sin ellos.
            'password'   => (static function (): string {
                $password = (string) Env::get('KN_SMTP_PASS', '');

                return preg_match('/^[a-z0-9]{4}( [a-z0-9]{4}){3}$/', $password) === 1
                    ? str_replace(' ', '', $password)
                    : $password;
            })(),
            'timeout'    => (int) Env::get('KN_SMTP_TIMEOUT', '10'),
            // Paquete de certificados de confianza (cacert.pem). Solo hace falta
            // si PHP no los encuentra, algo habitual en Windows. Ver README.
            'cafile'     => Env::get('KN_SMTP_CAFILE', ''),
            // Nombre con el que la tienda se presenta al servidor (EHLO).
            'ehlo'       => (static function (): string {
                $host = (string) parse_url((string) Env::get('KN_APP_URL', ''), PHP_URL_HOST);
                $host = (string) preg_replace('/[^A-Za-z0-9.-]/', '', $host);

                return $host !== '' ? $host : 'localhost';
            })(),
        ],
    ],

    'invoicing' => [
        'prefix'      => 'F',
        // Clave con la que se firman los enlaces a la factura incluidos en los
        // correos, para que se abran sin pedir el correo al cliente. Cámbiala
        // por una cadena aleatoria propia antes de desplegar.
        'link_secret' => Env::get('KN_LINK_SECRET', 'secreto-de-demostracion-cambiar-en-produccion'),
    ],

    'events' => [
        // 1.1: se añaden a la carga útil «idioma», «moneda» y los contravalores en
        // euros de los importes. Es un cambio compatible: no se quita ni se renombra nada.
        'schema_version' => '1.1',
        'source'         => 'kitsune-notes.web',
        // Los eventos se escriben simultáneamente en base de datos y en un
        // fichero JSON Lines por día, listo para ser ingerido por otro sistema.
        'log_path'       => Env::get('KN_EVENT_LOG_PATH', dirname(__DIR__) . '/storage/events'),
        'log_enabled'    => Env::get('KN_EVENT_LOG_ENABLED', '1') === '1',
    ],
];
