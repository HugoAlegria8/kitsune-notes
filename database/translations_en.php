<?php
/**
 * Kitsune Notes - Traducción al inglés de los datos maestros de prueba.
 *
 * Contiene la versión en inglés (británico, como la moneda de esa versión
 * de la tienda) de las categorías, las colecciones y los 12 productos de
 * database/seed.php. Se identifica cada fila por su clave estable: el slug
 * en categorías y colecciones, y el SKU en productos.
 *
 * Lo usa KitsuneNotes\Support\CatalogTranslations en tres momentos:
 *   - el instalador, después de cargar los datos de prueba;
 *   - la actualización automática de una base de datos anterior a los
 *     idiomas (SchemaUpgrade), justo después de crear las columnas «_en»;
 *   - «php bin/install.php --traducciones», para volver a aplicarlas.
 *
 * Solo rellena campos VACÍOS: nunca pisa una traducción escrita desde el
 * back-office. Los productos que el equipo dé de alta después no están
 * aquí: su traducción se escribe en la ficha del producto.
 *
 * Como el resto de los datos de prueba, todo es FICTICIO.
 */

declare(strict_types=1);

return [
    // -----------------------------------------------------------------
    // Categorías (clave: slug)
    // -----------------------------------------------------------------
    'categories' => [
        'cuadernos' => [
            'name_en'        => 'Notebooks & journals',
            'tagline_en'     => 'Soft paper for your cutest ideas',
            'description_en' => 'Stitched notebooks, pocket jotters and journals with thick paper that takes fountain pen, gel and marker ink without bleeding through.',
        ],
        'escritura' => [
            'name_en'        => 'Writing',
            'tagline_en'     => 'Pens, markers and fountain pens with little faces',
            'description_en' => 'Writing instruments imported from Japan and Korea: 0.38 mm gel pens, brush tips and beginner-friendly fountain pens.',
        ],
        'washi-y-pegatinas' => [
            'name_en'        => 'Washi tape & stickers',
            'tagline_en'     => 'Decorate every page in style',
            'description_en' => 'Repositionable washi tapes and die-cut stickers for journalling, scrapbooking and planners.',
        ],
        'organizacion' => [
            'name_en'        => 'Organisation',
            'tagline_en'     => 'Planners and little things for your desk',
            'description_en' => 'Planners, files and organisers that keep your desk tidy without losing the fun.',
        ],
    ],

    // -----------------------------------------------------------------
    // Colecciones (clave: slug). El nombre es un nombre propio y no se traduce.
    // -----------------------------------------------------------------
    'design_lines' => [
        'kitsune' => [
            'mascot_en'      => 'little fox',
            'tagline_en'     => "The house's little peach fox",
            'description_en' => "Our official mascot: a peach-coloured little fox with strawberry cheeks. Kitsune Notes' own designs, sweet as candy.",
        ],
        'neko' => [
            'mascot_en'      => 'kitten',
            'tagline_en'     => 'Naps, moons and little paws',
            'description_en' => 'A lavender kitten who spends the whole day asleep on top of your notes. Lilac tones, moons and tiny stars.',
        ],
        'tokki' => [
            'mascot_en'      => 'bunny',
            'tagline_en'     => 'Hopping among the strawberries',
            'description_en' => 'A strawberry-pink Korean bunny with one ear that always flops down. Strawberries, clouds and hearts everywhere.',
        ],
        'gom' => [
            'mascot_en'      => 'little bear',
            'tagline_en'     => 'The most huggable mint bear',
            'description_en' => 'A mint-coloured Korean bear, round and with a heart-shaped nose. Picnics, gingham and quiet afternoons.',
        ],
    ],

    // -----------------------------------------------------------------
    // Productos (clave: SKU). «specs» sustituye a la ficha técnica completa.
    // -----------------------------------------------------------------
    'products' => [
        'KN-CUA-001' => [
            'name_en'        => 'Neko Nyan A5 dotted notebook',
            'origin_en'      => 'Japan',
            'summary_en'     => 'Stitched A5 notebook with die-cut cat ears on the cover and 100 gsm paper that takes fountain pen ink.',
            'description_en' => 'Neko the kitten has fallen asleep on this notebook and nothing will move him. The lavender cover has two little die-cut ears peeking over the top and an embossed kitty face. Inside, 192 dotted pages of 100 gsm cream paper that take fountain pen ink without bleeding through, and a stitched binding that opens completely flat on your desk.',
            'specs'          => ['Format' => 'A5 (148 × 210 mm)', 'Pages' => '192 (96 sheets)', 'Ruling' => '5 mm dot grid', 'Paper weight' => '100 gsm', 'Binding' => 'Stitched, opens 180°', 'Detail' => 'Die-cut ears on the cover'],
        ],
        'KN-CUA-002' => [
            'name_en'        => 'Tokki Mochi A6 pocket notebook',
            'origin_en'      => 'South Korea',
            'summary_en'     => 'A6 pocket notebook as squishy as a mochi, with Tokki the bunny and strawberries on the cover.',
            'description_en' => 'Small, squishy and pink, just like a strawberry mochi. The padded cover shows Tokki peeking out from among the strawberries, one ear flopping down as always. 6 mm lines in the palest pink so your notes stay cute, and 96 pages of 90 gsm paper that get along nicely with gel pens.',
            'specs'          => ['Format' => 'A6 (105 × 148 mm)', 'Pages' => '96 (48 sheets)', 'Ruling' => '6 mm lined, in pink', 'Paper weight' => '90 gsm', 'Cover' => 'Padded, soft-touch', 'Binding' => 'Stapled'],
        ],
        'KN-CUA-003' => [
            'name_en'        => 'Kitsune Kawaii B6 journal',
            'origin_en'      => 'Japan',
            'summary_en'     => 'Hardback B6 journal with an embroidered Kitsune fox, two ribbon markers and a secret pocket.',
            'description_en' => 'The official house journal. A hard cover wrapped in peach fabric with Kitsune the little fox embroidered on it, two ribbon markers (one pink, one cream), an elastic closure and a secret pocket inside the back cover for your stickers. Inside you get 80 dotted pages and 80 lined ones, so it works as a diary and a planner without switching notebooks.',
            'specs'          => ['Format' => 'B6 (128 × 182 mm)', 'Pages' => '160 (80 dotted + 80 lined)', 'Paper weight' => '100 gsm', 'Binding' => 'Stitched hardback', 'Extras' => '2 ribbon markers, elastic closure and pocket', 'Design' => 'Kitsune Notes exclusive'],
        ],
        'KN-ESC-001' => [
            'name_en'        => 'Gom Gom 0.38 mm gel pens (pack of 3)',
            'origin_en'      => 'South Korea',
            'summary_en'     => 'Three fine-line gel pens with a silicone Gom bear sitting on each cap.',
            'description_en' => "Each pen has a little silicone Gom bear sitting on the cap, heart-shaped nose and all. The 0.38 mm line is a favourite for tiny handwriting in planners, with pigmented gel ink that dries fast and doesn't smudge. Colours: mint, milk chocolate and raspberry.",
            'specs'          => ['Line width' => '0.38 mm', 'Ink' => 'Quick-drying pigmented gel', 'Colours' => 'Mint, milk chocolate and raspberry', 'Detail' => 'Silicone bear on the cap', 'Quantity' => '3 pens', 'Refillable' => 'Yes, standard refill'],
        ],
        'KN-ESC-002' => [
            'name_en'        => 'Kitsune Pastel brush pens (set of 6)',
            'origin_en'      => 'Japan',
            'summary_en'     => 'Six pastel brush pens, each with a different little fox face on the cap.',
            'description_en' => 'Six flexible brush-tip pens for lettering and bullet journalling. Each cap has a different Kitsune face: happy, asleep, winking, surprised… Blendable water-based ink in peach, strawberry, lilac, mint, lemon and sky blue.',
            'specs'          => ['Tip' => 'Flexible nylon brush', 'Ink' => 'Water-based, blendable', 'Colours' => 'Peach, strawberry, lilac, mint, lemon and sky blue', 'Quantity' => '6 pens', 'Use' => 'Lettering, bullet journalling and drawing'],
        ],
        'KN-ESC-003' => [
            'name_en'        => 'Neko Paw fountain pen (fine nib)',
            'origin_en'      => 'Japan',
            'summary_en'     => 'Lilac fountain pen with a cat-paw clip and a fine steel nib.',
            'description_en' => "For anyone who wants to start with fountain pens without giving up on cute: a lilac resin body with very fine glitter, a clip shaped like a cat's paw with raised toe beans, and a fine steel nib that is smooth and forgiving about your writing angle. Comes with one violet ink cartridge and a converter for bottled ink.",
            'specs'          => ['Nib' => 'F (fine), stainless steel', 'Filling' => 'Standard cartridge or converter (included)', 'Material' => 'Lilac resin with glitter', 'Clip' => 'Embossed cat paw', 'Includes' => '1 violet cartridge + converter'],
        ],
        'KN-WAS-001' => [
            'name_en'        => 'Tokki Strawberry washi tape (set of 5 rolls)',
            'origin_en'      => 'South Korea',
            'summary_en'     => 'Five rolls of washi tape with bunnies, strawberries, clouds and hearts in candy pinks.',
            'description_en' => 'The sweetest set in the shop: five rolls of washi tape with Tokki hopping among strawberries, fluffy clouds, hearts and picnic checks, all in candy pinks. Tear it with your fingers, peel it off without ripping the paper and write on top of it.',
            'specs'          => ['Size' => '15 mm × 7 m per roll', 'Quantity' => '5 rolls', 'Material' => 'Washi paper', 'Properties' => 'Repositionable and writable', 'Patterns' => 'Bunnies, strawberries, clouds, hearts and gingham'],
        ],
        'KN-WAS-002' => [
            'name_en'        => 'Gom Picnic washi tape (set of 3 rolls)',
            'origin_en'      => 'South Korea',
            'summary_en'     => 'Three rolls of washi tape with picnic bears, mint gingham and sandwiches with little faces.',
            'description_en' => 'Gom has gone on a picnic and taken three rolls of washi tape along: bears with their baskets, mint gingham and sandwiches with little faces. At 10 mm wide it is perfect for marking margins and dividing sections without covering what you write.',
            'specs'          => ['Size' => '10 mm × 8 m per roll', 'Quantity' => '3 rolls', 'Material' => 'Matt washi paper', 'Colours' => 'Mint, cream and strawberry', 'Properties' => 'Repositionable and writable'],
        ],
        'KN-WAS-003' => [
            'name_en'        => 'Kitsune Mood stickers (120 pcs)',
            'origin_en'      => 'South Korea',
            'summary_en'     => '120 die-cut stickers of Kitsune the little fox in every mood.',
            'description_en' => 'Six sheets with 120 stickers of our own design: Kitsune happy, grumpy, asleep, in love or hungry, plus hearts, little stars, habit labels and date flags. Matt finish so you can write on them.',
            'specs'          => ['Quantity' => '120 stickers on 6 sheets', 'Finish' => 'Matt, writable', 'Size' => 'Between 8 and 35 mm', 'Adhesive' => 'Permanent, low-migration', 'Design' => 'Kitsune Notes exclusive'],
        ],
        'KN-ORG-001' => [
            'name_en'        => 'Neko Nap 2026 weekly planner',
            'origin_en'      => 'Japan',
            'summary_en'     => '12-month A5 planner with weekly and monthly views, and a sleeping kitten in every week.',
            'description_en' => "A January-to-December 2026 planner with monthly and landscape weekly views, quarterly goal pages and habit tracking. Every week Neko takes a nap in a different corner (and sometimes right on top of your plans). Hidden spiral binding so it doesn't snag in your bag.",
            'specs'          => ['Format' => 'A5 (148 × 210 mm)', 'Period' => 'January – December 2026', 'Views' => 'Monthly + landscape weekly', 'Paper weight' => '120 gsm', 'Binding' => 'Hidden spiral', 'Extras' => 'Quarterly goals and habits'],
        ],
        'KN-ORG-002' => [
            'name_en'        => 'Gom Little House desk organiser',
            'origin_en'      => 'South Korea',
            'summary_en'     => "Desk organiser shaped like Gom the bear's little house, with three compartments and a tiny drawer.",
            'description_en' => "Gom's little house for your desk: a roof with bear ears, a round window with the bear peeking out, and three compartments for pens, markers and sticky notes. It also has a tiny drawer for paper clips and erasers. Made from pressed bamboo fibre with a matt mint finish.",
            'specs'          => ['Size' => '220 × 120 × 160 mm', 'Material' => 'Pressed bamboo fibre', 'Compartments' => '3 + small drawer', 'Finish' => 'Matt mint', 'Base' => 'Non-slip felt'],
        ],
        'KN-ORG-003' => [
            'name_en'        => 'Tokki Cloud A4 expanding file',
            'origin_en'      => 'South Korea',
            'summary_en'     => 'A4 expanding file with 13 pockets and Tokki the bunny asleep on a cloud.',
            'description_en' => 'So your notes, bills and drawings can live in the clouds: an expanding file in pink recycled polypropylene with Tokki asleep on a cloud on the cover. Thirteen tabbed pockets and a sheet of label stickers included. Elastic closure with a pompom.',
            'specs'          => ['Format' => 'A4', 'Compartments' => '13, tabbed', 'Material' => 'Recycled polypropylene', 'Closure' => 'Elastic with pompom', 'Includes' => 'Sheet of adhesive labels'],
        ],
    ],
];
