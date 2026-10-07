<?php
/**
 * Catálogo de traducción al inglés · textos comunes.
 *
 * Formato: 'texto en español' => 'traducción'. El texto en español es
 * exactamente el que aparece en el código dentro de t('…'), th('…') o
 * tn('…'): si se cambia allí, hay que cambiar aquí la clave. Los marcadores
 * {entre llaves} se copian tal cual; cada idioma los coloca donde quiera.
 *
 * Este fichero reúne lo que comparten todas las páginas: cabecera y pie,
 * validación de formularios, estados del pedido, resumen económico y los
 * textos que salen de la configuración o de los servicios. El resto está en
 * tienda.php, compra.php y documentos.php.
 *
 * Inglés británico (la versión en inglés vende en libras): basket,
 * delivery, postcode, catalogue, organiser, colour…
 *
 * Comprobación: «php tools/comprobar_traducciones.php».
 */

declare(strict_types=1);

return [
    // --- Cabecera, pie y avisos generales --------------------------------
    'Kitsune Notes · papelería kawaii japonesa y coreana. Prototipo académico sin actividad comercial real.'
        => 'Kitsune Notes · kawaii stationery from Japan and Korea. Academic prototype with no real commercial activity.',
    'Saltar al contenido principal' => 'Skip to main content',
    'Prototipo académico · sin actividad comercial real: productos, precios, pagos y pedidos son ficticios.'
        => 'Academic prototype · no real commercial activity: products, prices, payments and orders are fictitious.',
    'Prototipo académico sin actividad comercial real. Los productos, precios, pagos y pedidos son ficticios.'
        => 'Academic prototype with no real commercial activity. Products, prices, payments and orders are fictitious.',
    'Más información' => 'More information',
    'papelería kawaii de Japón y Corea' => 'kawaii stationery from Japan and Korea',
    'Papelería kawaii de Japón y Corea' => 'Kawaii stationery from Japan and Korea',
    'Buscar productos' => 'Search products',
    'Busca cuadernos, washi, Neko…' => 'Search notebooks, washi, Neko…',
    'Buscar' => 'Search',
    'Mi pedido' => 'My order',
    'Carrito' => 'Basket',
    'artículos en el carrito' => 'items in the basket',
    'Navegación principal' => 'Main navigation',
    'Todo el catálogo' => 'Full catalogue',
    'Colecciones' => 'Collections',
    'Soporte' => 'Support',
    'Papelería kawaii importada de Japón y Corea: cuadernos, escritura, washi tape y organización, con cuatro personajes que lo llenan todo de mofletes.'
        => 'Kawaii stationery imported from Japan and Korea: notebooks, writing, washi tape and organisation, with four characters who fill everything with rosy cheeks.',
    'Catálogo' => 'Catalogue',
    'Ayuda' => 'Help',
    'Consultar un pedido' => 'Track an order',
    'Soporte e incidencias' => 'Support and issues',
    'Envíos y devoluciones' => 'Delivery and returns',
    'Sobre Kitsune Notes' => 'About Kitsune Notes',
    'Aviso académico' => 'Academic notice',
    'Acceso interno' => 'Staff login',
    '© {anio} Kitsune Notes · Empresa ficticia creada para la asignatura <em>Soluciones Informáticas para la Empresa</em> (UCAM).'
        => '© {anio} Kitsune Notes · Fictitious company created for the course <em>Soluciones Informáticas para la Empresa</em> (UCAM).',

    // --- Aviso de cookies -------------------------------------------------
    'Aviso de cookies' => 'Cookie notice',
    'Usamos cookies, pero solo las necesarias.' => 'We use cookies, but only the necessary ones.',
    'Una de sesión para guardar tu carrito y proteger los formularios y otras dos para recordar este aviso y tu idioma. No hay cookies de publicidad ni de análisis.'
        => 'A session cookie for your basket and forms, plus two more to remember this notice and your language. No advertising or analytics cookies.',
    'Entendido' => 'Got it',

    // --- Páginas de error -------------------------------------------------
    'Página no encontrada' => 'Page not found',
    'La página que buscas no existe.' => 'The page you are looking for does not exist.',
    'La dirección {ruta} no existe en la tienda.' => 'The address {ruta} does not exist in the shop.',
    'Error del servidor' => 'Server error',
    'Se ha producido un error inesperado. Vuelve a intentarlo en unos minutos.'
        => 'Something went wrong. Please try again in a few minutes.',

    // --- Validación de formularios ---------------------------------------
    'El campo «{campo}» es obligatorio.' => 'The “{campo}” field is required.',
    'Introduce una dirección de correo válida en «{campo}».' => 'Please enter a valid email address in “{campo}”.',
    '«{campo}» debe tener al menos {n} caracteres.' => '“{campo}” must be at least {n} characters long.',
    '«{campo}» no puede superar los {n} caracteres.' => '“{campo}” cannot be longer than {n} characters.',
    '«{campo}» solo admite dígitos.' => '“{campo}” only accepts digits.',
    '«{campo}» debe tener exactamente {n} caracteres.' => '“{campo}” must be exactly {n} characters long.',
    'El código postal debe tener 5 dígitos.' => 'The postcode must have 5 digits.',
    'El teléfono no tiene un formato válido.' => 'The phone number is not in a valid format.',
    'El valor seleccionado en «{campo}» no es válido.' => 'The option selected in “{campo}” is not valid.',
    '«{campo}» debe ser un número entero.' => '“{campo}” must be a whole number.',
    'El número de tarjeta no es válido (no supera la comprobación de Luhn).'
        => 'The card number is not valid (it fails the Luhn check).',
    'La fecha de caducidad debe tener el formato MM/AA y no estar vencida.'
        => 'The expiry date must be in MM/YY format and must not have passed.',
    'Debes aceptar «{campo}» para continuar.' => 'You must accept “{campo}” to continue.',
    '«{campo}» debe ser un importe en euros, por ejemplo 12,90.' => '“{campo}” must be an amount in euros, for example 12.90.',
    '«{campo}» debe estar entre {min} y {max}.' => '“{campo}” must be between {min} and {max}.',
    '«{campo}» solo admite letras, números y guiones (por ejemplo KN-CUA-004).'
        => '“{campo}” only accepts letters, numbers and hyphens (for example KN-CUA-004).',
    '«{campo}» solo admite minúsculas sin tildes, números y guiones.'
        => '“{campo}” only accepts unaccented lowercase letters, numbers and hyphens.',
    'El campo «{campo}» no es válido.' => 'The “{campo}” field is not valid.',

    // Nombres de los campos (se usan en los formularios y en sus mensajes)
    'Nombre y apellidos' => 'Full name',
    'Nombre' => 'Name',
    'Correo electrónico' => 'Email address',
    'Teléfono' => 'Phone number',
    'Dirección' => 'Address',
    'Código postal' => 'Postcode',
    'Población' => 'Town or city',
    'Provincia' => 'Province',
    'Método de envío' => 'Delivery method',
    'Notas para la entrega' => 'Delivery notes',
    'las condiciones del prototipo' => 'the prototype conditions',
    'Titular de la tarjeta' => 'Cardholder name',
    'Número de tarjeta' => 'Card number',
    'Caducidad' => 'Expiry date',
    'CVV' => 'CVV',
    'Referencia del pedido' => 'Order reference',
    'Tipo de solicitud' => 'Type of request',
    'Asunto' => 'Subject',
    'Mensaje' => 'Message',

    // --- Estados del pedido y cronología ----------------------------------
    'Creado' => 'Created',
    'Pagado (simulado)' => 'Paid (simulated)',
    'Pendiente de preparación' => 'Being prepared',
    'Enviado' => 'Dispatched',
    'Entregado' => 'Delivered',
    'Cancelado' => 'Cancelled',
    'Con incidencia' => 'Issue reported',
    // Notas que escribe el sistema (o los datos de demostración) en la cronología
    'Pedido generado desde el checkout.' => 'Order created at checkout.',
    'Pago simulado autorizado.' => 'Simulated payment authorised.',
    'Pago simulado autorizado con código {codigo}.' => 'Simulated payment authorised with code {codigo}.',
    'Incidencia {referencia} comunicada por el cliente.' => 'Issue {referencia} reported by the customer.',
    'Pedido aceptado por el almacén.' => 'Order accepted by the warehouse.',
    'Entregado al transportista (envío simulado).' => 'Handed over to the courier (simulated delivery).',
    'En cola de preparación.' => 'Queued for preparation.',
    'La clienta comunica que falta un artículo en el envío.' => 'The customer reports that an item is missing from the parcel.',
    'El cliente cambió el carrito o la moneda antes de pagar: se sustituye por un pedido nuevo.'
        => 'The customer changed the basket or the currency before paying: replaced by a new order.',

    // --- Envío: textos que salen de la configuración ------------------------
    'Envío' => 'Delivery',
    'Envío estándar' => 'Standard delivery',
    'Entrega en 3-5 días laborables (simulado)' => 'Delivered in 3-5 working days (simulated)',
    'Envío exprés' => 'Express delivery',
    'Entrega en 24-48 h (simulado)' => 'Delivered in 24-48 hours (simulated)',
    'estándar' => 'standard',
    'exprés' => 'express',

    // --- Resumen económico (carrito, checkout y pago) -----------------------
    'Tu cuenta' => 'Order summary',
    'Resumen del pedido' => 'Order summary',
    '¡Te faltan {importe} para el envío gratis!' => 'Spend {importe} more for free delivery!',
    '{n} artículo' => '{n} item',
    '{n} artículos' => '{n} items',
    'Descuento {codigo}' => 'Discount {codigo}',
    'Gratis' => 'Free',
    'Envoltorio furoshiki' => 'Furoshiki gift wrap',
    'Base imponible' => 'Net amount',
    'IVA ({porcentaje} %)' => 'VAT ({porcentaje}%)',
    'Total' => 'Total',

    // --- Cupones ----------------------------------------------------------
    'El código de descuento no existe o ha caducado.' => 'That discount code does not exist or has expired.',
    'El código {codigo} requiere un importe mínimo de {importe} en artículos.'
        => 'Code {codigo} requires a minimum of {importe} in items.',
    '{descuento} de descuento en pedidos de más de {minimo} en artículos.' => '{descuento} off orders over {minimo} in items.',
    '{descuento} de descuento sobre el importe de los artículos.' => '{descuento} off the items total.',

    // --- Portada y catálogo: títulos y avisos de los controladores ------------
    'Esa categoría no existe en el catálogo.' => 'That category does not exist in the catalogue.',
    'Esa colección no existe.' => 'That collection does not exist.',
    'Colección {nombre}' => '{nombre} collection',
    'Ese producto ya no está disponible.' => 'That product is no longer available.',
    'Resultados para «{busqueda}»' => 'Results for “{busqueda}”',
    'Aviso: prototipo académico' => 'Notice: academic prototype',
    'Envíos y devoluciones (simulado)' => 'Delivery and returns (simulated)',

    // --- Carrito: avisos ---------------------------------------------------
    'Tu carrito' => 'Your basket',
    'La sesión ha caducado. Vuelve a intentarlo.' => 'Your session has expired. Please try again.',
    'El producto seleccionado no está disponible.' => 'The selected product is not available.',
    'No queda stock disponible de «{producto}».' => '“{producto}” is out of stock.',
    'Hemos ajustado la cantidad al máximo disponible de «{producto}».'
        => 'We have adjusted the quantity to the maximum available for “{producto}”.',
    '«{producto}» se ha añadido a tu carrito.' => '“{producto}” has been added to your basket.',
    'Carrito actualizado.' => 'Basket updated.',
    '«{producto}» se ha eliminado del carrito.' => '“{producto}” has been removed from your basket.',
    'Se ha retirado el código de descuento.' => 'The discount code has been removed.',
    'Introduce un código de descuento.' => 'Please enter a discount code.',
    'Código {codigo} aplicado correctamente.' => 'Code {codigo} applied.',

    // --- Checkout y pago: títulos y avisos ----------------------------------
    'Tu carrito está vacío: añade algún producto antes de continuar.' => 'Your basket is empty: add a product before continuing.',
    'Datos de envío' => 'Delivery details',
    'La sesión ha caducado. Revisa los datos y vuelve a enviarlos.'
        => 'Your session has expired. Please check your details and submit them again.',
    'Revisa los campos marcados para poder continuar.' => 'Please check the highlighted fields to continue.',
    'Necesitamos tus datos de envío antes de pasar al pago.' => 'We need your delivery details before moving on to payment.',
    'Pago simulado' => 'Simulated payment',
    'La sesión ha caducado. Vuelve a introducir los datos de pago.'
        => 'Your session has expired. Please enter your payment details again.',
    'Los datos de la tarjeta de prueba no son válidos.' => 'The test card details are not valid.',
    'No se ha podido generar el pedido. Vuelve a intentarlo en unos minutos.'
        => 'We could not create your order. Please try again in a few minutes.',
    'El pago simulado ha sido rechazado: {motivo} El pedido {referencia} queda pendiente de pago; puedes reintentarlo.'
        => 'The simulated payment was declined: {motivo} Order {referencia} is awaiting payment; you can try again.',
    // Motivos de rechazo y tarjetas de prueba del simulador de pago
    'Fondos insuficientes (simulado).' => 'Insufficient funds (simulated).',
    'Tarjeta caducada (simulado).' => 'Card expired (simulated).',
    'Código de seguridad incorrecto (simulado).' => 'Incorrect security code (simulated).',
    'Pago autorizado (VISA de prueba)' => 'Payment authorised (test VISA)',
    'Pago autorizado (Mastercard de prueba)' => 'Payment authorised (test Mastercard)',
    'Rechazo por fondos insuficientes' => 'Declined: insufficient funds',
    'Rechazo por tarjeta caducada' => 'Declined: card expired',

    // Paso de datos de envío
    'Progreso de la compra' => 'Checkout progress',
    'Confirmación' => 'Confirmation',
    'Necesitamos estos datos para generar el pedido. Recuerda que se trata de un <strong>prototipo académico</strong>: usa datos ficticios, no introduzcas información personal real.'
        => 'We need these details to create the order. Remember this is an <strong>academic prototype</strong>: use made-up details and do not enter real personal information.',
    'Revisa el formulario' => 'Please check the form',
    'Contacto' => 'Contact',
    'Lo usarás para consultar el pedido después.' => 'You will use it to track your order later.',
    'Teléfono de contacto' => 'Contact phone number',
    'Dirección de entrega' => 'Delivery address',
    'Calle, número, piso' => 'Street, number, flat',
    'Portal, horario preferente, punto de recogida…' => 'Entrance, preferred time, pick-up point…',
    'Gratis desde {importe}' => 'Free from {importe}',
    'Añadir envoltorio furoshiki de regalo (+{importe})' => 'Add furoshiki gift wrap (+{importe})',
    'Entiendo que <strong>Kitsune Notes es un prototipo académico</strong>, que el pago es simulado y que no se producirá ningún cobro ni ningún envío real.'
        => 'I understand that <strong>Kitsune Notes is an academic prototype</strong>, that payment is simulated and that no real charge or delivery will take place.',
    'Ir al pago simulado' => 'Go to simulated payment',
    'El total se actualiza al cambiar el método de envío o el envoltorio.'
        => 'The total updates when you change the delivery method or the gift wrap.',
    '{n} línea en el pedido' => '{n} line in the order',
    '{n} líneas en el pedido' => '{n} lines in the order',

    // --- Pedidos y factura: títulos y avisos ---------------------------------
    'No encontramos ningún pedido con la referencia {referencia}.' => 'We could not find an order with the reference {referencia}.',
    'Para ver este pedido, confirma el correo electrónico con el que se realizó.'
        => 'To view this order, please confirm the email address it was placed with.',
    'Consulta tu pedido' => 'Track your order',
    'Pedido {referencia}' => 'Order {referencia}',
    'Para ver la factura, confirma el correo electrónico con el que se hizo el pedido.'
        => 'To view the invoice, please confirm the email address the order was placed with.',
    'El pedido {referencia} todavía no tiene factura: se expide cuando el pago queda confirmado.'
        => 'Order {referencia} has no invoice yet: it is issued once payment is confirmed.',
    'Factura {numero}' => 'Invoice {numero}',
    'Volver al pedido' => 'Back to the order',
    'No hay ningún pedido que coincida con esos datos.' => 'No order matches those details.',

    // --- Soporte -----------------------------------------------------------
    'Soporte y postventa' => 'Support and after-sales',
    'La sesión ha caducado. Vuelve a enviar el formulario.' => 'Your session has expired. Please submit the form again.',
    'Solicitud registrada' => 'Request received',
    'Incidencia con el envío' => 'Problem with the delivery',
    'Producto dañado o incorrecto' => 'Damaged or wrong product',
    'Solicitud de devolución' => 'Return request',
    'Consulta sobre un pedido' => 'Question about an order',
    'Otra consulta' => 'Other enquiry',

    // --- Factura: textos que se congelan en el documento ----------------------
    'España' => 'Spain',
    'Empresa ficticia: el NIF y el domicilio no corresponden a ninguna entidad real.'
        => 'Fictitious company: the tax ID and the address do not belong to any real entity.',
    'Factura de prueba emitida por un prototipo académico: no tiene validez fiscal y no se ha cobrado ningún importe.'
        => 'Test invoice issued by an academic prototype: it has no tax validity and no amount has been charged.',

    // --- Correos: asuntos y entradillas ---------------------------------------
    'Pedido {referencia} confirmado · Factura {factura}' => 'Order {referencia} confirmed · Invoice {factura}',
    'Gracias por tu compra. Aquí tienes tu factura {factura}.' => 'Thank you for your order. Here is your invoice {factura}.',
    'Tu pedido {referencia} ya va en camino' => 'Your order {referencia} is on its way',
    'Tu paquete de Kitsune Notes ha salido del almacén.' => 'Your Kitsune Notes parcel has left the warehouse.',
    'en 24-48 horas' => 'within 24-48 hours',
    'en 3-5 días laborables' => 'within 3-5 working days',
    'Hemos recibido tu solicitud {referencia}' => 'We have received your request {referencia}',
    'Tu solicitud está registrada; el equipo la revisará lo antes posible.'
        => 'Your request has been logged; the team will look into it as soon as possible.',

    // --- Textos cortos que comparten varias páginas (tienda, compra, factura, correos) ---
    'Aplicar' => 'Apply',
    'Ref. {sku}' => 'Ref. {sku}',
    'Precio' => 'Price',
    'autorizado' => 'authorised',
    'rechazado' => 'declined',
    'Ir al catálogo' => 'Go to the catalogue',
    'Artículos' => 'Items',
    'Uds.' => 'Qty',
    'Importe' => 'Amount',
];
