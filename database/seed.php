<?php
/**
 * Kitsune Notes - Datos de prueba (seed).
 *
 * Todos los datos de este fichero son FICTICIOS y se utilizan únicamente
 * con fines académicos: no corresponden a personas, marcas ni pedidos
 * reales. Las direcciones de correo usan el dominio reservado `.test`
 * (RFC 2606) para dejar claro que no son buzones reales. Los personajes
 * de las colecciones (Kitsune, Neko, Tokki y Gom) son diseños originales
 * del proyecto.
 */

declare(strict_types=1);

/**
 * @param \KitsuneNotes\Core\App|null $app Si se pasa, se generan también las
 *        facturas y los correos de los pedidos históricos con los mismos
 *        servicios que usa la tienda.
 */
function kitsune_seed(PDO $pdo, string $adminPassword, ?\KitsuneNotes\Core\App $app = null): void
{
    $ts = static fn (int $daysAgo = 0, int $hour = 10, int $min = 0): string =>
        (new DateTimeImmutable("-{$daysAgo} days", new DateTimeZone('Europe/Madrid')))
            ->setTime($hour, $min)
            ->format(DATE_ATOM);

    // -----------------------------------------------------------------
    // Categorías: qué es el producto
    // -----------------------------------------------------------------
    $categories = [
        ['cuadernos', 'Cuadernos y libretas', 'Papel suave para tus ideas más monas',
         'Cuadernos cosidos, libretas de bolsillo y diarios con papel grueso que aguanta pluma, gel y rotulador sin traspasar.', 'notebook', 1],
        ['escritura', 'Escritura', 'Bolis, rotuladores y plumas con carita',
         'Instrumentos de escritura importados de Japón y Corea: gel de 0,38 mm, punta pincel y estilográficas de iniciación.', 'pen', 2],
        ['washi-y-pegatinas', 'Washi tape y pegatinas', 'Decora cada página a lo grande',
         'Cintas washi reposicionables y pegatinas troqueladas para journaling, scrapbooking y agendas.', 'tape', 3],
        ['organizacion', 'Organización', 'Planners y cositas para tu escritorio',
         'Agendas, archivadores y organizadores que mantienen el escritorio en orden sin perder la gracia.', 'grid', 4],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO categories (slug, name, tagline, description, icon, sort_order)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($categories as $row) {
        $stmt->execute($row);
    }

    // -----------------------------------------------------------------
    // Colecciones (líneas de diseño): cómo es el producto
    // -----------------------------------------------------------------
    // Eje transversal a la categoría: cada colección tiene su personaje,
    // su color y su propio tono. Dos personajes japoneses y dos coreanos,
    // como la procedencia del catálogo.
    $lines = [
        ['kitsune', 'Kitsune', 'zorrito', 'きつね', 'El zorrito melocotón de la casa',
         'Nuestra mascota oficial: un zorrito color melocotón con mofletes de fresa. Diseños propios de Kitsune Notes con sabor a golosina.',
         '#FFA07A', '#FFE8DC', 1],
        ['neko', 'Neko', 'gatito', 'ねこ', 'Siestas, lunas y patitas',
         'Un gatito lavanda que se pasa el día durmiendo encima de tus apuntes. Tonos lila, lunas y estrellitas.',
         '#C9A7FF', '#F1E8FF', 2],
        ['tokki', 'Tokki', 'conejito', '토끼', 'Saltitos entre fresas',
         'Un conejito coreano rosa fresa con una oreja siempre caída. Fresas, nubes y corazones por todas partes.',
         '#FF8FB8', '#FFE3EE', 3],
        ['gom', 'Gom', 'osito', '곰', 'El osito menta más achuchable',
         'Un osito coreano color menta, redondito y con nariz de corazón. Picnics, cuadros vichy y tardes tranquilas.',
         '#8EDDC4', '#E0F7EF', 4],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO design_lines (slug, name, mascot, native_name, tagline, description, color_primary, color_soft, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($lines as $row) {
        $stmt->execute($row);
    }

    $catId  = static fn (string $slug): int => (int) array_search($slug, array_column($categories, 0), true) + 1;
    $lineId = static fn (string $slug): int => (int) array_search($slug, array_column($lines, 0), true) + 1;

    // -----------------------------------------------------------------
    // Catálogo: 12 referencias, 3 por categoría y 3 por colección
    // -----------------------------------------------------------------
    $products = [
        [
            'sku' => 'KN-CUA-001', 'slug' => 'cuaderno-neko-nyan-a5',
            'name' => 'Cuaderno Neko Nyan A5 punteado', 'category' => 'cuadernos', 'line' => 'neko',
            'brand' => 'Nyanko Bunguten', 'origin' => 'Japón',
            'summary' => 'Cuaderno cosido A5 con orejitas de gato troqueladas en la tapa y papel de 100 g/m² apto para pluma.',
            'description' => 'El gatito Neko se ha quedado dormido encima de este cuaderno y ya no hay quien lo mueva. La cubierta lavanda lleva dos orejitas troqueladas que asoman por arriba y una carita estampada en relieve. Dentro, 192 páginas punteadas de papel crema de 100 g/m² que aguantan la tinta de pluma sin traspasar, y una encuadernación cosida que se abre del todo sobre la mesa.',
            'specs' => ['Formato' => 'A5 (148 × 210 mm)', 'Páginas' => '192 (96 hojas)', 'Pauta' => 'Punteada de 5 mm', 'Gramaje' => '100 g/m²', 'Encuadernación' => 'Cosida, apertura a 180°', 'Detalle' => 'Orejitas troqueladas en la cubierta'],
            'price' => 1290, 'compare_at' => null, 'stock' => 42, 'weight' => 320, 'featured' => 1,
        ],
        [
            'sku' => 'KN-CUA-002', 'slug' => 'libreta-tokki-mochi-a6',
            'name' => 'Libreta Tokki Mochi A6', 'category' => 'cuadernos', 'line' => 'tokki',
            'brand' => 'Tokki Friends', 'origin' => 'Corea del Sur',
            'summary' => 'Libreta de bolsillo A6 blandita como un mochi, con el conejito Tokki y fresas en la tapa.',
            'description' => 'Pequeña, blandita y rosa como un mochi de fresa. La cubierta acolchada lleva a Tokki asomando entre fresas, con su oreja caída de siempre. Rayado de 6 mm en rosa muy clarito para que los apuntes no pierdan la gracia, y 96 páginas de 90 g/m² que se llevan bien con el boli de gel.',
            'specs' => ['Formato' => 'A6 (105 × 148 mm)', 'Páginas' => '96 (48 hojas)', 'Pauta' => 'Rayada de 6 mm en rosa', 'Gramaje' => '90 g/m²', 'Cubierta' => 'Acolchada, tacto suave', 'Encuadernación' => 'Grapada'],
            'price' => 850, 'compare_at' => null, 'stock' => 65, 'weight' => 140, 'featured' => 0,
        ],
        [
            'sku' => 'KN-CUA-003', 'slug' => 'diario-kitsune-kawaii-b6',
            'name' => 'Diario Kitsune Kawaii B6', 'category' => 'cuadernos', 'line' => 'kitsune',
            'brand' => 'Kitsune Notes Studio', 'origin' => 'Japón',
            'summary' => 'Diario B6 de tapa dura con el zorrito Kitsune bordado, dos cintas marcapáginas y bolsillo secreto.',
            'description' => 'El diario oficial de la casa. Tapa dura forrada en tela melocotón con el zorrito Kitsune bordado, dos cintas marcapáginas (una rosa y otra crema), goma de cierre y un bolsillo secreto en la contracubierta para guardar pegatinas. Por dentro, 80 páginas punteadas y 80 rayadas: sirve de diario y de agenda sin cambiar de libreta.',
            'specs' => ['Formato' => 'B6 (128 × 182 mm)', 'Páginas' => '160 (80 punteadas + 80 rayadas)', 'Gramaje' => '100 g/m²', 'Encuadernación' => 'Tapa dura cosida', 'Extras' => '2 marcapáginas, goma de cierre y bolsillo', 'Diseño' => 'Exclusivo Kitsune Notes'],
            'price' => 1490, 'compare_at' => 1790, 'stock' => 28, 'weight' => 260, 'featured' => 1,
        ],
        [
            'sku' => 'KN-ESC-001', 'slug' => 'boligrafos-gom-gom-038-pack3',
            'name' => 'Bolígrafos gel Gom Gom 0,38 mm (pack de 3)', 'category' => 'escritura', 'line' => 'gom',
            'brand' => 'Gomgom Studio', 'origin' => 'Corea del Sur',
            'summary' => 'Tres bolis de gel de trazo finito con un osito Gom de silicona sentado en cada capuchón.',
            'description' => 'Cada boli lleva un osito Gom de silicona sentado en el capuchón, con su nariz de corazón. Trazo de 0,38 mm, el favorito para escribir pequeñito en agendas, con tinta de gel pigmentada que seca rápido y no se corre. Colores: menta, chocolate con leche y frambuesa.',
            'specs' => ['Trazo' => '0,38 mm', 'Tinta' => 'Gel pigmentada de secado rápido', 'Colores' => 'Menta, chocolate con leche y frambuesa', 'Detalle' => 'Osito de silicona en el capuchón', 'Unidades' => '3 bolígrafos', 'Recambiable' => 'Sí, recambio estándar'],
            'price' => 990, 'compare_at' => null, 'stock' => 80, 'weight' => 45, 'featured' => 0,
        ],
        [
            'sku' => 'KN-ESC-002', 'slug' => 'rotuladores-kitsune-pastel-set6',
            'name' => 'Rotuladores Kitsune Pastel (set de 6)', 'category' => 'escritura', 'line' => 'kitsune',
            'brand' => 'Kitsune Notes Studio', 'origin' => 'Japón',
            'summary' => 'Seis rotuladores de punta pincel en tonos pastel, cada uno con una carita distinta del zorrito.',
            'description' => 'Seis rotuladores de punta pincel flexible para lettering y bullet journal. Cada capuchón lleva una carita diferente del zorrito Kitsune: contento, dormido, guiñando un ojo, sorprendido... Tinta al agua mezclable en melocotón, fresa, lila, menta, limón y cielo.',
            'specs' => ['Punta' => 'Pincel flexible de nailon', 'Tinta' => 'Al agua, mezclable', 'Colores' => 'Melocotón, fresa, lila, menta, limón y cielo', 'Unidades' => '6 rotuladores', 'Uso' => 'Lettering, bullet journal y dibujo'],
            'price' => 1650, 'compare_at' => null, 'stock' => 34, 'weight' => 180, 'featured' => 1,
        ],
        [
            'sku' => 'KN-ESC-003', 'slug' => 'pluma-neko-patita-f',
            'name' => 'Pluma estilográfica Neko Patita (plumín F)', 'category' => 'escritura', 'line' => 'neko',
            'brand' => 'Nyanko Bunguten', 'origin' => 'Japón',
            'summary' => 'Estilográfica lila con clip en forma de patita de gato y plumín fino de acero.',
            'description' => 'Para quien quiere empezar con la pluma sin renunciar a lo mono: cuerpo de resina lila con purpurina muy fina, clip en forma de patita de gato con sus almohadillas en relieve y plumín F de acero, suave y tolerante con el ángulo de escritura. Incluye un cartucho de tinta violeta y un convertidor para usar tinta de bote.',
            'specs' => ['Plumín' => 'F (fino), acero inoxidable', 'Carga' => 'Cartucho estándar o convertidor (incluido)', 'Material' => 'Resina lila con purpurina', 'Clip' => 'Patita de gato en relieve', 'Incluye' => '1 cartucho violeta + convertidor'],
            'price' => 2800, 'compare_at' => null, 'stock' => 15, 'weight' => 95, 'featured' => 0,
        ],
        [
            'sku' => 'KN-WAS-001', 'slug' => 'washi-tokki-fresa-set5',
            'name' => 'Washi tape Tokki Fresa (set de 5 rollos)', 'category' => 'washi-y-pegatinas', 'line' => 'tokki',
            'brand' => 'Tokki Friends', 'origin' => 'Corea del Sur',
            'summary' => 'Cinco rollos de washi con conejitos, fresas, nubes y corazones en rosas de golosina.',
            'description' => 'El set más dulce de la tienda: cinco rollos de cinta washi con Tokki saltando entre fresas, nubes esponjosas, corazones y cuadros de picnic, todo en rosas de golosina. Se rasga con los dedos, se despega sin romper el papel y se puede escribir encima.',
            'specs' => ['Medidas' => '15 mm × 7 m por rollo', 'Unidades' => '5 rollos', 'Material' => 'Papel washi', 'Propiedades' => 'Reposicionable y escribible', 'Estampados' => 'Conejitos, fresas, nubes, corazones y vichy'],
            'price' => 1120, 'compare_at' => null, 'stock' => 58, 'weight' => 120, 'featured' => 1,
        ],
        [
            'sku' => 'KN-WAS-002', 'slug' => 'washi-gom-picnic-set3',
            'name' => 'Washi tape Gom Picnic (set de 3 rollos)', 'category' => 'washi-y-pegatinas', 'line' => 'gom',
            'brand' => 'Gomgom Studio', 'origin' => 'Corea del Sur',
            'summary' => 'Tres rollos de washi con ositos de picnic, cuadros vichy menta y sándwiches con carita.',
            'description' => 'Gom se ha ido de picnic y se ha llevado tres rollos de washi: ositos con su cesta, cuadros vichy en menta y sándwiches con carita. Ancho de 10 mm, perfecto para marcar márgenes y separar secciones sin tapar lo que escribes.',
            'specs' => ['Medidas' => '10 mm × 8 m por rollo', 'Unidades' => '3 rollos', 'Material' => 'Papel washi mate', 'Colores' => 'Menta, crema y fresa', 'Propiedades' => 'Reposicionable y escribible'],
            'price' => 780, 'compare_at' => null, 'stock' => 71, 'weight' => 80, 'featured' => 0,
        ],
        [
            'sku' => 'KN-WAS-003', 'slug' => 'pegatinas-kitsune-mood-120',
            'name' => 'Pegatinas Kitsune Mood (120 uds.)', 'category' => 'washi-y-pegatinas', 'line' => 'kitsune',
            'brand' => 'Kitsune Notes Studio', 'origin' => 'Corea del Sur',
            'summary' => '120 pegatinas troqueladas del zorrito Kitsune en todos sus estados de ánimo.',
            'description' => 'Seis láminas con 120 pegatinas de diseño propio: el zorrito Kitsune contento, enfurruñado, dormido, enamorado o con hambre, más corazones, estrellitas, etiquetas de hábitos y banderitas de fecha. Acabado mate para poder escribir encima.',
            'specs' => ['Unidades' => '120 pegatinas en 6 láminas', 'Acabado' => 'Mate, escribible', 'Tamaño' => 'Entre 8 y 35 mm', 'Adhesivo' => 'Permanente de baja migración', 'Diseño' => 'Exclusivo Kitsune Notes'],
            'price' => 590, 'compare_at' => null, 'stock' => 95, 'weight' => 60, 'featured' => 0,
        ],
        [
            'sku' => 'KN-ORG-001', 'slug' => 'planner-neko-siesta-2026',
            'name' => 'Planner semanal Neko Siesta 2026', 'category' => 'organizacion', 'line' => 'neko',
            'brand' => 'Nyanko Bunguten', 'origin' => 'Japón',
            'summary' => 'Agenda A5 de 12 meses con vista semanal y mensual, y un gatito dormido en cada semana.',
            'description' => 'Planificador de enero a diciembre de 2026 con vista mensual y semanal apaisada, páginas de objetivos por trimestre y seguimiento de hábitos. Cada semana Neko duerme la siesta en una esquina distinta (y a veces encima de tus planes). Espiral oculta para que no se enganche en la mochila.',
            'specs' => ['Formato' => 'A5 (148 × 210 mm)', 'Periodo' => 'Enero – diciembre 2026', 'Vistas' => 'Mensual + semanal apaisada', 'Gramaje' => '120 g/m²', 'Encuadernación' => 'Espiral oculta', 'Extras' => 'Objetivos trimestrales y hábitos'],
            'price' => 1890, 'compare_at' => null, 'stock' => 23, 'weight' => 420, 'featured' => 1,
        ],
        [
            'sku' => 'KN-ORG-002', 'slug' => 'organizador-gom-casita',
            'name' => 'Organizador de escritorio Gom Casita', 'category' => 'organizacion', 'line' => 'gom',
            'brand' => 'Gomgom Studio', 'origin' => 'Corea del Sur',
            'summary' => 'Organizador con forma de casita del osito Gom, con tres compartimentos y cajoncito.',
            'description' => 'La casita de Gom para tu mesa: tejado con orejitas de oso, ventana redonda por la que asoma el osito y tres compartimentos para bolis, rotuladores y notas adhesivas. Incluye un cajoncito para clips y gomas. Fabricado en fibra de bambú prensada con acabado menta mate.',
            'specs' => ['Medidas' => '220 × 120 × 160 mm', 'Material' => 'Fibra de bambú prensada', 'Compartimentos' => '3 + cajoncito', 'Acabado' => 'Menta mate', 'Base' => 'Fieltro antideslizante'],
            'price' => 2450, 'compare_at' => null, 'stock' => 18, 'weight' => 680, 'featured' => 0,
        ],
        [
            'sku' => 'KN-ORG-003', 'slug' => 'archivador-tokki-nube-a4',
            'name' => 'Archivador acordeón Tokki Nube A4', 'category' => 'organizacion', 'line' => 'tokki',
            'brand' => 'Tokki Friends', 'origin' => 'Corea del Sur',
            'summary' => 'Archivador de fuelle A4 con 13 compartimentos y el conejito Tokki dormido sobre una nube.',
            'description' => 'Para que apuntes, facturas y dibujos vivan en las nubes: archivador acordeón de polipropileno reciclado rosa con Tokki durmiendo sobre una nube en la tapa. Trece compartimentos con pestaña y una hoja de pegatinas-etiqueta incluida. Cierre elástico con pompón.',
            'specs' => ['Formato' => 'A4', 'Compartimentos' => '13 con pestaña', 'Material' => 'Polipropileno reciclado', 'Cierre' => 'Elástico con pompón', 'Incluye' => 'Hoja de etiquetas adhesivas'],
            'price' => 1540, 'compare_at' => null, 'stock' => 31, 'weight' => 390, 'featured' => 0,
        ],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO products
            (sku, slug, name, category_id, design_line_id, brand, origin, summary, description,
             specs_json, price_cents, compare_at_cents, tax_rate, stock, weight_grams,
             image_path, is_active, is_featured, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.21, ?, ?, ?, 1, ?, ?, NULL)'
    );

    foreach ($products as $i => $p) {
        $stmt->execute([
            $p['sku'], $p['slug'], $p['name'], $catId($p['category']), $lineId($p['line']),
            $p['brand'], $p['origin'], $p['summary'], $p['description'],
            json_encode($p['specs'], JSON_UNESCAPED_UNICODE),
            $p['price'], $p['compare_at'], $p['stock'], $p['weight'],
            'assets/img/products/' . $p['slug'] . '.svg', $p['featured'],
            $ts(60 - $i, 9, 30),
        ]);
    }

    // -----------------------------------------------------------------
    // Traducción al inglés del catálogo
    // -----------------------------------------------------------------
    // Los datos anteriores están en español, que es el idioma original. Su
    // versión en inglés vive en database/translations_en.php y se carga en
    // las columnas «_en» (solo rellena campos vacíos).
    \KitsuneNotes\Support\CatalogTranslations::apply($pdo);

    // -----------------------------------------------------------------
    // Condiciones comerciales simuladas
    // -----------------------------------------------------------------
    $stmt = $pdo->prepare(
        'INSERT INTO coupons (code, type, value, min_items_total_cents, description, valid_until, is_active)
         VALUES (?, ?, ?, ?, ?, ?, 1)'
    );
    $stmt->execute(['KITSUNE10', 'percent', 10, 0, '10 % de descuento sobre el importe de los artículos.', '2026-12-31T23:59:59+01:00']);
    $stmt->execute(['FRESITA5', 'fixed', 500, 2500, '5 € de descuento en pedidos de más de 25 € en artículos.', '2026-12-31T23:59:59+01:00']);

    // -----------------------------------------------------------------
    // Clientes de prueba (ficticios, dominio reservado .test)
    // -----------------------------------------------------------------
    $customers = [
        ['ana.demo@kitsunenotes.test', 'Ana Demo Ruiz', '+34 600 000 001', 'Calle de Prueba 12, 3.º B', '30001', 'Murcia', 'Murcia'],
        ['bruno.demo@kitsunenotes.test', 'Bruno Demo Serra', '+34 600 000 002', 'Avenida Ficticia 45', '46001', 'Valencia', 'Valencia'],
        ['clara.demo@kitsunenotes.test', 'Clara Demo Vidal', '+34 600 000 003', 'Plaza Imaginaria 7, 1.º A', '28004', 'Madrid', 'Madrid'],
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO customers (email, full_name, phone, address_line, postal_code, city, province, country, is_demo, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, \'ES\', 1, ?)'
    );
    foreach ($customers as $i => $c) {
        $stmt->execute([...$c, $ts(45 - $i * 5, 12, 0)]);
    }

    // -----------------------------------------------------------------
    // Usuario interno del back-office
    // -----------------------------------------------------------------
    $pdo->prepare(
        'INSERT INTO staff_users (email, full_name, password_hash, role, created_at)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([
        'admin@kitsunenotes.test',
        'Operador de demostración',
        password_hash($adminPassword, PASSWORD_DEFAULT),
        'administrador',
        $ts(60, 8, 0),
    ]);

    // -----------------------------------------------------------------
    // Pedidos históricos de demostración
    // -----------------------------------------------------------------
    // Tres pedidos en estados distintos para que el back-office tenga
    // contenido desde la primera ejecución y la defensa pueda mostrar el
    // ciclo de vida completo.
    $demoOrders = [
        [
            'reference' => 'KN-2026-000001', 'customer_id' => 1, 'status' => 'enviado',
            'days' => 9, 'coupon' => 'KITSUNE10', 'shipping_method' => 'estandar', 'gift_wrap' => 0,
            'lines' => [[1, 1], [4, 2]],
            'payment' => ['autorizado', 'tarjeta', '4242'],
            'history' => [
                ['creado', 'pagado_simulado', 'sistema', 'Pago simulado autorizado.'],
                ['pagado_simulado', 'pendiente_preparacion', 'admin@kitsunenotes.test', 'Pedido aceptado por el almacén.'],
                ['pendiente_preparacion', 'enviado', 'admin@kitsunenotes.test', 'Entregado al transportista (envío simulado).'],
            ],
        ],
        [
            'reference' => 'KN-2026-000002', 'customer_id' => 2, 'status' => 'pendiente_preparacion',
            'days' => 3, 'coupon' => null, 'shipping_method' => 'express', 'gift_wrap' => 1,
            'lines' => [[10, 1], [5, 1], [9, 2]],
            'payment' => ['autorizado', 'tarjeta', '1111'],
            'history' => [
                ['creado', 'pagado_simulado', 'sistema', 'Pago simulado autorizado.'],
                ['pagado_simulado', 'pendiente_preparacion', 'admin@kitsunenotes.test', 'En cola de preparación.'],
            ],
        ],
        [
            'reference' => 'KN-2026-000003', 'customer_id' => 3, 'status' => 'incidencia',
            'days' => 1, 'coupon' => null, 'shipping_method' => 'estandar', 'gift_wrap' => 0,
            'lines' => [[3, 1], [7, 1]],
            'payment' => ['autorizado', 'tarjeta', '4242'],
            'history' => [
                ['creado', 'pagado_simulado', 'sistema', 'Pago simulado autorizado.'],
                ['pagado_simulado', 'incidencia', 'admin@kitsunenotes.test', 'La clienta comunica que falta un artículo en el envío.'],
            ],
        ],
    ];

    $orderStmt = $pdo->prepare(
        'INSERT INTO orders
            (reference, customer_id, status, currency, items_total_cents, discount_cents,
             shipping_cents, giftwrap_cents, taxable_base_cents, tax_cents, total_cents, coupon_code,
             shipping_method, shipping_name, shipping_address, shipping_postal_code, shipping_city,
             shipping_province, shipping_country, gift_wrap, customer_notes, session_id, created_at, updated_at,
             total_base_cents)
         VALUES (?, ?, ?, \'EUR\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'ES\', ?, \'\', ?, ?, ?, ?)'
    );
    $lineStmt = $pdo->prepare(
        'INSERT INTO order_lines
            (order_id, product_id, sku, name, design_line, image_path, unit_price_cents, quantity, tax_rate, line_total_cents)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0.21, ?)'
    );
    $payStmt = $pdo->prepare(
        'INSERT INTO payments
            (order_id, reference, provider, method, status, amount_cents, currency, card_brand,
             card_last4, authorization_code, decline_reason, response_json, processed_at)
         VALUES (?, ?, \'simulador-interno\', ?, ?, ?, \'EUR\', ?, ?, ?, \'\', ?, ?)'
    );
    $histStmt = $pdo->prepare(
        'INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, note, created_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $seeded = [];

    foreach ($demoOrders as $index => $o) {
        $customer = $customers[$o['customer_id'] - 1];

        // Cálculo del desglose con las mismas reglas que PricingService.
        $itemsTotal = 0;
        $lineRows   = [];
        foreach ($o['lines'] as [$productIndex, $qty]) {
            $p          = $products[$productIndex - 1];
            $lineTotal  = $p['price'] * $qty;
            $itemsTotal += $lineTotal;
            $lineRows[] = [$productIndex, $p, $qty, $lineTotal];
        }

        $discount = $o['coupon'] === 'KITSUNE10' ? (int) round($itemsTotal * 0.10) : 0;

        $afterDiscount = $itemsTotal - $discount;
        $shipping = $o['shipping_method'] === 'express' ? 695 : ($afterDiscount >= 3500 ? 0 : 395);
        $giftwrap = $o['gift_wrap'] ? 150 : 0;
        $total    = $afterDiscount + $shipping + $giftwrap;
        $base     = (int) round($total / 1.21);
        $tax      = $total - $base;

        $createdAt = $ts($o['days'], 11, 15 + $index * 7);

        $orderStmt->execute([
            $o['reference'], $o['customer_id'], $o['status'], $itemsTotal, $discount,
            $shipping, $giftwrap, $base, $tax, $total, $o['coupon'],
            $o['shipping_method'], $customer[1], $customer[3], $customer[4], $customer[5],
            $customer[6], $o['gift_wrap'], 'seed-' . $o['reference'], $createdAt, $createdAt,
            $total, // pedidos de demostración en euros: el contravalor es el propio total
        ]);
        $orderId = (int) $pdo->lastInsertId();

        foreach ($lineRows as [$productIndex, $p, $qty, $lineTotal]) {
            $lineStmt->execute([
                $orderId, $productIndex, $p['sku'], $p['name'],
                $lines[$lineId($p['line']) - 1][1],
                'assets/img/products/' . $p['slug'] . '.svg',
                $p['price'], $qty, $lineTotal,
            ]);
        }

        [$payStatus, $payMethod, $last4] = $o['payment'];
        $payStmt->execute([
            $orderId,
            'PAY-' . strtoupper(substr(md5($o['reference']), 0, 10)),
            $payMethod, $payStatus, $total, 'VISA', $last4,
            'AUTH' . strtoupper(substr(md5($o['reference'] . 'auth'), 0, 6)),
            json_encode([
                'simulated' => true,
                'engine'    => 'PaymentSimulator v1',
                'note'      => 'Transacción ficticia generada por el seed de datos de prueba.',
            ], JSON_UNESCAPED_UNICODE),
            $createdAt,
        ]);

        $historyTime = new DateTimeImmutable($createdAt);
        $shippedAt   = null;
        $histStmt->execute([$orderId, null, 'creado', 'sistema', 'Pedido generado desde el checkout.', $createdAt]);
        foreach ($o['history'] as $step => [$from, $to, $by, $note]) {
            $at = $historyTime->modify('+' . (($step + 1) * 6) . ' hours');
            $histStmt->execute([$orderId, $from, $to, $by, $note, $at->format(DATE_ATOM)]);

            if ($to === 'enviado') {
                $shippedAt = $at;
            }
        }

        $seeded[] = ['order_id' => $orderId, 'created_at' => $createdAt, 'shipped_at' => $shippedAt];
    }

    // Incidencia asociada al tercer pedido
    $pdo->prepare(
        'INSERT INTO support_tickets
            (reference, order_reference, customer_name, customer_email, type, subject, message, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        'INC-2026-000001', 'KN-2026-000003', 'Clara Demo Vidal', 'clara.demo@kitsunenotes.test',
        'incidencia_envio', 'Falta un artículo en el pedido',
        'He recibido el diario de Kitsune pero no el set de washi tape de Tokki. La caja llegaba abierta por un lateral.',
        'abierta', $ts(1, 17, 40),
    ]);

    // -----------------------------------------------------------------
    // Facturas y correos de los pedidos históricos
    // -----------------------------------------------------------------
    // Se generan con los mismos servicios que usa la tienda en producción,
    // de modo que las facturas y el buzón de pruebas tienen contenido desde
    // la primera ejecución. Los eventos se silencian: son datos históricos
    // de demostración, no actividad real que deba contar en las métricas.
    // Y se apaga el envío real: aunque el equipo tenga SMTP configurado,
    // estos correos de ejemplo no deben salir nunca de la aplicación.
    if ($app === null) {
        return;
    }

    $app->events()->mute();
    $app->mailer()->disableDelivery();

    $readAfter = static fn (DateTimeImmutable $sent): string => $sent->modify('+2 hours')->format(DATE_ATOM);

    foreach ($seeded as $row) {
        $order    = $app->orders()->findById($row['order_id']);
        $issuedAt = (new DateTimeImmutable($row['created_at']))->modify('+3 minutes');
        $invoice  = $app->invoices()->issue($order, $issuedAt);

        $mail = $app->notifier()->orderConfirmed($order, $invoice, $issuedAt);
        $app->mailRepository()->markRead((int) $mail['id'], $readAfter($issuedAt));

        if ($row['shipped_at'] !== null) {
            $mail = $app->notifier()->orderShipped($order, $row['shipped_at']);
            $app->mailRepository()->markRead((int) $mail['id'], $readAfter($row['shipped_at']));
        }
    }

    $ticket = $app->support()->findByReference('INC-2026-000001');
    $order  = $app->orders()->findByReference('KN-2026-000003');

    if ($ticket !== null) {
        $sentAt = new DateTimeImmutable($ticket['created_at']);
        $mail   = $app->notifier()->ticketReceived($ticket, $order, $sentAt);
        $app->mailRepository()->markRead((int) $mail['id'], $readAfter($sentAt));
    }

    $app->events()->mute(false);
}
