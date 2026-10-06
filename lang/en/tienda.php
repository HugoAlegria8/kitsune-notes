<?php
/**
 * Catálogo de traducción al inglés · escaparate y páginas informativas.
 *
 * Formato: 'texto en español' => 'traducción' (véase la cabecera de comun.php).
 *
 * Este fichero reúne los textos de las páginas en las que se mira y se elige:
 * la portada, el catálogo (listado, categorías y colecciones), la ficha y la
 * tarjeta de producto, y las páginas informativas («Colecciones», «Sobre
 * Kitsune Notes», «Envíos y devoluciones», «Aviso académico» y la página de
 * error). Incluye también las descripciones del catálogo de eventos
 * (EventRecorder::CATALOG), que el aviso académico traduce al pintarlas.
 * Los textos que estas páginas comparten con el resto de la tienda (cabecera,
 * pie, resumen económico, títulos que ponen los controladores…) están en
 * comun.php y aquí no se repiten.
 *
 * Inglés británico: basket, delivery, catalogue, colour, organiser, authorised…
 *
 * Comprobación: «php tools/comprobar_traducciones.php».
 */

declare(strict_types=1);

return [
    // --- views/catalog/home.php ---------------------------------------------
    'Recién llegado de Japón y Corea' => 'Just in from Japan and Korea',
    'Papelería que te hace <mark>sonreír</mark>' => 'Stationery that makes you <mark>smile</mark>',
    'Cuadernos con orejitas, bolis con ositos, washi tape de fresas y agendas con gatitos dormilones. Cuatro personajes, cuatro colecciones y mucho, mucho kawaii para tu escritorio.'
        => 'Notebooks with little ears, pens with tiny bears, strawberry washi tape and planners with sleepy kittens. Four characters, four collections and lots and lots of kawaii for your desk.',
    'Ver el catálogo' => 'Browse the catalogue',
    'Conocer a los personajes' => 'Meet the characters',
    'Elige tu personaje' => 'Choose your character',
    'Cada colección es una línea de diseño con su mascota, su color y su estilo.'
        => 'Each collection is a design line with its own mascot, colour and style.',
    'Ver colecciones' => 'View collections',
    '{n} producto del {mascota}' => '{n} product from the {mascota}',
    '{n} productos del {mascota}' => '{n} products from the {mascota}',
    'Los favoritos de la casa' => 'House favourites',
    'Lo que más se lleva esta temporada.' => 'This season\'s must-haves.',
    'Ver todo' => 'View all',
    'Compra por categoría' => 'Shop by category',
    'Y si ya sabes lo que buscas, directo al grano.' => 'And if you already know what you want, go straight to it.',
    // Aquí «referencias» son artículos del catálogo, no referencias de pedido.
    '{n} referencia' => '{n} product',
    '{n} referencias' => '{n} products',
    'Condiciones de compra' => 'Terms of purchase',
    'Envío gratis desde {importe}' => 'Free delivery from {importe}',
    'Envío estándar simulado en 3-5 días laborables.' => 'Simulated standard delivery in 3-5 working days.',
    'Pago simulado seguro' => 'Secure simulated payment',
    'Pasarela de pruebas: nunca guardamos números de tarjeta.' => 'Test gateway: we never store card numbers.',
    'Para regalar, por {importe} más.' => 'Wrapped and ready to give, for an extra {importe}.',
    'Te ayudamos' => 'Help when you need it',
    'Incidencias con seguimiento por referencia.' => 'Issues tracked by reference number.',

    // --- views/catalog/index.php --------------------------------------------
    // «Migas de pan» e «Inicio» se usan también en la ficha de producto.
    'Migas de pan' => 'Breadcrumb',
    'Inicio' => 'Home',
    'Filtrar' => 'Filter',
    'Categoría' => 'Category',
    'Todas' => 'All',
    'Colección' => 'Collection',
    'Quitar filtros' => 'Clear filters',
    '{nombre}, el {mascota} de la colección' => '{nombre} the {mascota}, the collection mascot',
    '{n} cosita mona' => '{n} cute little thing',
    '{n} cositas monas' => '{n} cute little things',
    '{n} cosita mona para «{busqueda}»' => '{n} cute little thing for “{busqueda}”',
    '{n} cositas monas para «{busqueda}»' => '{n} cute little things for “{busqueda}”',
    'Ordenar por' => 'Sort by',
    'Recomendado' => 'Recommended',
    'Precio: de menor a mayor' => 'Price: low to high',
    'Precio: de mayor a menor' => 'Price: high to low',
    'Nombre (A-Z)' => 'Name (A-Z)',
    'Novedades' => 'Newest',
    'No hemos encontrado nada' => 'We couldn\'t find anything',
    'Prueba con otra búsqueda o quita algún filtro.' => 'Try a different search or remove a filter.',
    'Ver todo el catálogo' => 'View the full catalogue',

    // --- views/catalog/product.php ------------------------------------------
    'Ilustración del producto {nombre}' => 'Illustration of {nombre}',
    'Hecho en {origen}' => 'Made in {origen}',
    // «IVA incluido» y «Añadir al carrito» se usan también en la tarjeta de producto.
    'IVA incluido' => 'VAT included',
    'Desglose: {base} de base imponible + {iva} de IVA ({porcentaje} %).'
        => 'Breakdown: {base} net amount + {iva} VAT ({porcentaje}%).',
    'En stock y listo para salir volando' => 'In stock and ready to fly out the door',
    '¡Solo queda {n} unidad!' => 'Only {n} unit left!',
    '¡Solo quedan {n} unidades!' => 'Only {n} units left!',
    'Agotado por ahora' => 'Sold out for now',
    'Cantidad' => 'Quantity',
    'Añadir al carrito' => 'Add to basket',
    'Ver más de esta colección' => 'See more from this collection',
    'Envío estándar {importe}' => 'Standard delivery {importe}',
    'Gratis a partir de {importe} (simulado).' => 'Free on orders from {importe} (simulated).',
    'Peso {gramos} g' => 'Weight {gramos} g',
    'Embalaje de cartón reciclado.' => 'Recycled cardboard packaging.',
    // «Descripción» es también una columna de la tabla de tarjetas de prueba.
    'Descripción' => 'Description',
    'Ficha técnica' => 'Specifications',
    'Características técnicas de {nombre}' => 'Technical specifications for {nombre}',
    'Hace buena pareja con…' => 'Pairs nicely with…',
    'Más cositas de la misma colección o de la misma categoría.'
        => 'More little things from the same collection or the same category.',

    // --- views/partials/producto-tarjeta.php --------------------------------
    '¡Oferta!' => 'Sale!',
    '¡Quedan poquitos!' => 'Only a few left!',
    'Agotado' => 'Sold out',

    // --- views/page/colecciones.php -----------------------------------------
    'Conoce a los personajes' => 'Meet the characters',
    'El catálogo se organiza en dos ejes. La <strong>categoría</strong> dice qué es el producto: un cuaderno, un boli, una cinta washi o un organizador. La <strong>colección</strong> dice cómo es: cada una es una línea de diseño con su personaje, su color y su manera de ver el escritorio. Dos personajes vienen de Japón y dos de Corea, igual que el catálogo.'
        => 'The catalogue is organised in two ways. The <strong>category</strong> tells you what the product is: a notebook, a pen, a roll of washi tape or an organiser. The <strong>collection</strong> tells you what it looks like: each one is a design line with its own character, its own colour and its own way of looking at your desk. Two characters come from Japan and two from Korea, just like the catalogue.',
    '{nombre}, el {mascota}' => '{nombre} the {mascota}',
    'Ver su {n} producto' => 'See {n} product',
    'Ver sus {n} productos' => 'See all {n} products',
    'Los cuatro personajes son <strong>diseños originales</strong> de este proyecto, dibujados como ilustración vectorial; no reproducen ningún personaje comercial existente.'
        => 'All four characters are <strong>original designs</strong> made for this project and drawn as vector illustrations; they do not reproduce any existing commercial character.',

    // --- views/page/sobre.php -----------------------------------------------
    'Kitsune, el zorrito mascota de la tienda' => 'Kitsune the little fox, the shop mascot',
    'Una papelería pequeñita con muchas ganas de llenar tu escritorio de cosas monas.'
        => 'A tiny stationery shop that can\'t wait to fill your desk with cute things.',
    'Kitsune Notes importa material de escritorio y organización de Japón y Corea del Sur y lo reúne en cuatro colecciones con personaje propio. Buscamos papel que aguante la pluma, bolis que escriban finito y washi que se despegue sin romper la hoja… y que además te saquen una sonrisa cada vez que abres la agenda.'
        => 'Kitsune Notes imports desk and organisation supplies from Japan and South Korea and gathers them into four collections, each with its own character. We look for paper that can take a fountain pen, pens that write a fine line and washi tape that peels off without tearing the page… and that also make you smile every time you open your planner.',
    'De dónde viene el nombre' => 'Where the name comes from',
    'En el folclore japonés, el <em>kitsune</em> es un zorro listo y longevo. Nos pareció la mascota perfecta para una papelería: curioso, con memoria larga y con cara de tener siempre un cuaderno a mano.'
        => 'In Japanese folklore, the <em>kitsune</em> is a clever, long-lived fox. We thought it was the perfect mascot for a stationery shop: curious, with a long memory and the look of someone who always has a notebook to hand.',
    'Nuestras colecciones' => 'Our collections',
    // El lema llega en minúsculas porque va detrás de los dos puntos.
    '<a href="{url}">{nombre}</a>, el {mascota}: {lema}.' => '<a href="{url}">{nombre}</a> the {mascota}: {lema}.',
    'Kitsune Notes es una <strong>empresa ficticia</strong> creada como caso de estudio para la asignatura Soluciones Informáticas para la Empresa. <a href="{url}">Leer el aviso académico completo</a>.'
        => 'Kitsune Notes is a <strong>fictitious company</strong> created as a case study for the course Soluciones Informáticas para la Empresa. <a href="{url}">Read the full academic notice</a>.',

    // --- views/page/envios.php ----------------------------------------------
    'Envíos, devoluciones y condiciones' => 'Delivery, returns and terms',
    'Todas las condiciones de esta página son <strong>simuladas</strong>: describen las reglas de negocio implementadas en el prototipo, no un servicio real.'
        => 'All the terms on this page are <strong>simulated</strong>: they describe the business rules built into the prototype, not a real service.',
    'Métodos de envío' => 'Delivery methods',
    'Método' => 'Method',
    'Plazo' => 'Delivery time',
    'Gratis desde' => 'Free from',
    'Impuestos' => 'Taxes',
    'Todos los precios del catálogo se muestran con el <strong>IVA español del 21 %</strong> incluido, como exige la normativa de protección al consumidor en venta a particulares. En el resumen del pedido desglosamos la base imponible y la cuota de IVA, y ese desglose se guarda junto al pedido para que la factura siga siendo reproducible aunque el tipo impositivo cambie en el futuro.'
        => 'All catalogue prices are shown with <strong>Spanish VAT at 21%</strong> included, as consumer protection rules require for sales to individuals. In the order summary we break down the net amount and the VAT amount, and that breakdown is stored with the order so that the invoice can still be reproduced even if the tax rate changes in the future.',
    'Servicios adicionales' => 'Additional services',
    'Envoltorio furoshiki de regalo por {importe} por pedido, seleccionable en el paso de datos de envío.'
        => 'Furoshiki gift wrap for {importe} per order, which you can add at the delivery details step.',
    'Códigos de descuento activos' => 'Active discount codes',
    'Código' => 'Code',
    'Descuento' => 'Discount',
    'Condición' => 'Condition',
    // Porcentaje de un cupón: en inglés va sin espacio.
    '{n} %' => '{n}%',
    'Devoluciones' => 'Returns',
    'Condición simulada: 30 días naturales desde la entrega para devolver artículos sin usar. En el prototipo, una devolución se solicita desde el <a href="{url}">formulario de soporte</a> y genera una incidencia vinculada al pedido.'
        => 'Simulated condition: 30 calendar days from delivery to return unused items. In the prototype, a return is requested through the <a href="{url}">support form</a> and creates an issue linked to the order.',

    // --- views/page/aviso-academico.php -------------------------------------
    'Aviso: esto es un prototipo académico' => 'Notice: this is an academic prototype',
    'Kitsune Notes no es una tienda real.' => 'Kitsune Notes is not a real shop.',
    'Es un prototipo desarrollado para la asignatura <em>Soluciones Informáticas para la Empresa</em> del Grado en Ingeniería Informática (UCAM). No existe actividad comercial, no se cobra dinero y no se envía ningún producto.'
        => 'It is a prototype developed for the course <em>Soluciones Informáticas para la Empresa</em>, part of the Computer Engineering degree (UCAM). There is no commercial activity, no money is charged and no product is sent.',
    'Qué es ficticio' => 'What is fictitious',
    '<strong>La empresa y las marcas.</strong> Kitsune Notes y los fabricantes que aparecen en las fichas son nombres inventados.'
        => '<strong>The company and the brands.</strong> Kitsune Notes and the manufacturers shown on the product pages are made-up names.',
    '<strong>Los productos y los precios.</strong> El catálogo es realista pero no corresponde a artículos en venta.'
        => '<strong>The products and the prices.</strong> The catalogue is realistic, but it does not correspond to items that are actually for sale.',
    '<strong>Los pagos.</strong> Un simulador interno decide el resultado a partir de un listado cerrado de tarjetas de prueba. No hay conexión con ninguna pasarela real.'
        => '<strong>The payments.</strong> An internal simulator decides the outcome from a fixed list of test cards. There is no connection to any real payment gateway.',
    '<strong>Los envíos.</strong> Ningún pedido se prepara ni se entrega; los estados se cambian manualmente desde el back-office para demostrar el ciclo de vida.'
        => '<strong>The deliveries.</strong> No order is prepared or delivered; the statuses are changed by hand from the back office to demonstrate the life cycle.',
    '<strong>Las facturas y los correos.</strong> La factura lleva el sello «de prueba» y un NIF inventado. Los correos se guardan en un buzón de pruebas que solo consulta el personal del back-office; por defecto no sale ninguno de la aplicación, y si el equipo activa el envío real solo se envía a las direcciones que ha autorizado expresamente.'
        => '<strong>The invoices and the emails.</strong> The invoice carries a “test” stamp and a made-up tax ID (NIF). Emails are kept in a test mailbox that only back office staff can read; by default none of them leaves the application, and if the team switches on real sending they only go to the addresses it has expressly authorised.',
    '<strong>Los clientes.</strong> Las cuentas de ejemplo usan el dominio reservado <code>.test</code>, que no puede recibir correo.'
        => '<strong>The customers.</strong> The sample accounts use the reserved <code>.test</code> domain, which cannot receive email.',
    'Cómo tratamos los datos' => 'How we handle data',
    'No pedimos ni queremos datos personales reales: los formularios están pensados para rellenarse con datos de prueba.'
        => 'We neither ask for nor want real personal data: the forms are meant to be filled in with test data.',
    'Del número de tarjeta introducido <strong>solo se guardan los cuatro últimos dígitos</strong>. El número completo y el CVV nunca se escriben en la base de datos, en los eventos ni en los ficheros de registro.'
        => 'Of the card number you enter, <strong>only the last four digits are stored</strong>. The full number and the CVV are never written to the database, the events or the log files.',
    'En los eventos de negocio la dirección IP se guarda <strong>seudonimizada</strong> mediante una función resumen con sal, nunca en claro.'
        => 'In business events the IP address is stored <strong>pseudonymised</strong> using a salted hash function, never in plain text.',
    'No se utilizan cookies de terceros ni herramientas de analítica externas: la instrumentación es propia y se queda en el servidor.'
        => 'No third-party cookies or external analytics tools are used: the instrumentation is our own and stays on the server.',
    // Cookies («Cookies» y «Cookie» se escriben igual en inglés)
    'Cookies' => 'Cookies',
    'La tienda solo usa <strong>cookies propias y técnicas</strong>, necesarias para que funcione. No hay cookies de publicidad, de analítica ni de terceros; por eso el aviso de la portada es informativo y no pide elegir nada.'
        => 'The shop only uses <strong>first-party technical cookies</strong>, the ones it needs in order to work. There are no advertising, analytics or third-party cookies; that is why the notice on the home page is for information only and does not ask you to choose anything.',
    'Cookie' => 'Cookie',
    'Para qué sirve' => 'What it is for',
    'Duración' => 'Duration',
    'Mantiene tu sesión: el carrito, los mensajes entre pantallas y el código que protege los formularios. La cookie solo contiene un identificador aleatorio; los datos se guardan en el servidor.'
        => 'Keeps your session going: the basket, the messages between screens and the code that protects the forms. The cookie only contains a random identifier; the data is kept on the server.',
    'Hasta que cierres el navegador' => 'Until you close your browser',
    'Recuerda que has cerrado el aviso de cookies de la portada.' => 'Remembers that you have closed the cookie notice on the home page.',
    '{n} día' => '{n} day',
    '{n} días' => '{n} days',
    'Recuerda el idioma que has elegido con los botones ES/EN. Solo se guarda si cambias de idioma: es una preferencia que pides tú, así que no necesita consentimiento.'
        => 'Remembers the language you chose with the ES/EN buttons. It is only set if you change language: it is a preference you ask for yourself, so it does not need consent.',
    'Puedes borrarlas cuando quieras desde los ajustes de tu navegador. La tienda seguirá funcionando, pero el carrito empezará de cero, el aviso de la portada volverá a aparecer y el idioma volverá a ser el español.'
        => 'You can delete them whenever you like from your browser settings. The shop will keep working, but the basket will start from scratch, the notice on the home page will appear again and the language will go back to Spanish.',
    // Tarjetas de prueba (sus descripciones están en comun.php)
    'Tarjetas de prueba' => 'Test cards',
    'Si quieres recorrer el flujo de compra, usa una de estas tarjetas ficticias:'
        => 'If you want to walk through the checkout flow, use one of these fictitious cards:',
    'Número' => 'Number',
    'Resultado' => 'Result',
    // Eventos
    'Eventos que registra la tienda' => 'Events the shop records',
    'Cada acción relevante del proceso de compra emite un evento de negocio. Estos eventos son la materia prima con la que otros sistemas de la organización podrán trabajar más adelante.'
        => 'Every relevant action in the purchase process emits a business event. These events are the raw material that other systems in the organisation will be able to work with later on.',
    'Evento' => 'Event',
    'Cuándo se emite' => 'When it is emitted',
    'Uso de imágenes y personajes' => 'Use of images and characters',
    'Las ilustraciones de producto y los cuatro personajes de las colecciones (Kitsune, Neko, Tokki y Gom) son vectores originales generados por un script del propio proyecto (<code>tools/generar_ilustraciones.py</code>). No se utilizan fotografías, logotipos ni personajes de marcas reales.'
        => 'The product illustrations and the four collection characters (Kitsune, Neko, Tokki and Gom) are original vector artwork generated by a script that is part of the project itself (<code>tools/generar_ilustraciones.py</code>). No photographs, logos or characters from real brands are used.',

    // Catálogo de eventos: las claves son, letra por letra, las descripciones de
    // EventRecorder::CATALOG (src/Service/EventRecorder.php). Si se cambia una
    // allí, hay que cambiar aquí la clave.
    'Visualización de la ficha de un producto.' => 'A product page is viewed.',
    'Se añade un producto al carrito.' => 'A product is added to the basket.',
    'Se elimina un producto del carrito.' => 'A product is removed from the basket.',
    'El cliente inicia el proceso de checkout.' => 'The customer starts the checkout process.',
    'Se genera un pedido con referencia única.' => 'An order is created with a unique reference.',
    'Resultado del cobro simulado (autorizado o rechazado).' => 'Outcome of the simulated charge (authorised or declined).',
    'Solicitud de soporte o contacto postventa.' => 'Support request or after-sales contact.',
    'Incidencia registrada sobre un pedido existente.' => 'An issue is logged against an existing order.',
    'Cambio de estado de un pedido desde el back-office.' => 'An order status is changed from the back office.',
    'Alta de un producto nuevo en el catálogo.' => 'A new product is added to the catalogue.',
    'Modificación de un producto, con los campos cambiados.' => 'A product is edited, with the fields that changed.',
    'Un producto se retira del catálogo sin borrarse.' => 'A product is withdrawn from the catalogue without being deleted.',
    'Un producto retirado vuelve a publicarse.' => 'A withdrawn product is published again.',
    'Borrado definitivo de un producto sin ventas.' => 'A product with no sales is permanently deleted.',
    'Se expide la factura de un pedido pagado (numeración correlativa).'
        => 'The invoice for a paid order is issued (sequential numbering).',
    'El cliente consulta su factura, desde su pedido o desde el enlace del correo.'
        => 'The customer views their invoice, from their order or from the link in the email.',
    'Correo transaccional emitido al cliente: siempre queda en el buzón de pruebas y, si el envío real está activado y la dirección autorizada, también se entrega por SMTP (campo «entrega»).'
        => 'Transactional email issued to the customer: it is always kept in the test mailbox and, if real sending is switched on and the address is authorised, it is also delivered by SMTP (“entrega” field).',

    // --- views/page/error.php -----------------------------------------------
    'Volver a la portada' => 'Back to the home page',
];
