<?php
/**
 * Catálogo de traducción al inglés · documentos.
 *
 * Cubre la factura (en pantalla y dentro del correo) y los correos
 * transaccionales: confirmación del pedido con factura, aviso de envío y
 * acuse de recibo de soporte, cada uno en HTML y en texto plano.
 *
 * Mismo formato que comun.php: 'texto en español' => 'traducción'. Lo que
 * estas plantillas comparten con el resto de la tienda («Factura {numero}»,
 * «Base imponible», «Gratis», «Descuento {codigo}», «Envoltorio furoshiki»,
 * «Dirección de entrega»…) está en comun.php y no se repite aquí.
 *
 * Cada texto aparece una sola vez, bajo la primera plantilla que lo usa:
 * varios se comparten entre la factura en pantalla y la del correo, o entre
 * la versión HTML y la de texto plano de un mismo correo.
 *
 * En las frases con enlaces, {url} y {estilo} son atributos HTML que pone la
 * plantilla (los correos llevan el CSS en línea): se copian tal cual.
 *
 * Comprobación: «php tools/comprobar_traducciones.php».
 */

declare(strict_types=1);

return [
    // --- views/invoice/ver.php ---
    'Imprimir o guardar como PDF' => 'Print or save as PDF',
    'Para obtener el PDF, pulsa «Imprimir o guardar como PDF» y elige <em>Guardar como PDF</em> como impresora. La página está preparada para salir en una hoja A4 sin menús ni botones.'
        => 'To get the PDF, press “Print or save as PDF” and choose <em>Save as PDF</em> as the printer. The page is set up to print on an A4 sheet, without menus or buttons.',

    // --- views/partials/factura-documento.php ---
    'Factura de prueba · sin validez fiscal' => 'Test invoice · not valid for tax purposes',
    'NIF {nif}' => 'Tax ID (NIF): {nif}',
    'Factura' => 'Invoice',
    'Fecha de expedición' => 'Issue date',
    'Fecha de la operación' => 'Transaction date',
    'Pedido' => 'Order',
    'Emisor' => 'Seller',
    'Cliente' => 'Customer',
    'Conceptos facturados en la factura {numero}' => 'Items billed on invoice {numero}',
    'Concepto' => 'Description',
    'Precio (IVA incl.)' => 'Price (incl. VAT)',
    'Desglose de IVA' => 'VAT breakdown',
    // En la factura el tipo va sin paréntesis («IVA 21 %»); el resumen de la compra usa «IVA ({porcentaje} %)».
    'IVA {porcentaje} %' => 'VAT ({porcentaje}%)',
    'Total factura' => 'Invoice total',
    'Importes' => 'Amounts',
    'Total (IVA incluido)' => 'Total (VAT included)',
    // Solo en facturas que no están en euros: tipo de cambio y contravalor en euros
    'Tipo de cambio aplicado: {tipo} (tipo fijo de demostración).' => 'Exchange rate applied: {tipo} (fixed demonstration rate).',
    'Contravalor en euros: cuota de IVA {iva}, total {total}.' => 'Equivalent in euros: VAT amount {iva}, total {total}.',
    'Pago simulado con {marca} •••• {ultimos} · referencia {referencia} · autorización {autorizacion} · {fecha}'
        => 'Simulated payment with {marca} •••• {ultimos} · reference {referencia} · authorisation {autorizacion} · {fecha}',

    // --- views/mail/layout.php ---
    'Prototipo académico sin actividad comercial real · pedido, pago y factura ficticios'
        => 'Academic prototype with no real commercial activity · fictitious order, payment and invoice',
    'Prototipo académico · correo de prueba: no se ha entregado a ningún buzón real'
        => 'Academic prototype · test email: it has not been delivered to any real mailbox',
    'Recibes este mensaje porque se ha realizado una operación de prueba con esta dirección.'
        => 'You are receiving this message because a test transaction was made using this email address.',

    // --- views/mail/_factura.php ---
    'Líneas de la factura {numero}' => 'Items on invoice {numero}',
    'Pago simulado con {marca} &bull;&bull;&bull;&bull; {ultimos} (referencia {referencia}, autorización {autorizacion}).'
        => 'Simulated payment with {marca} &bull;&bull;&bull;&bull; {ultimos} (reference {referencia}, authorisation {autorizacion}).',

    // --- views/mail/_factura-texto.php ---
    // (Los dos espacios tras el número son de la plantilla: se conservan.)
    'FACTURA {numero}  (de prueba, sin validez fiscal)' => 'INVOICE {numero}  (test, not valid for tax purposes)',
    'Fecha de expedición: {fecha}' => 'Issue date: {fecha}',
    'Fecha de la operación: {fecha}' => 'Transaction date: {fecha}',
    'Pedido: {referencia}' => 'Order: {referencia}',
    'EMISOR' => 'SELLER',
    'CLIENTE' => 'CUSTOMER',
    'CONCEPTOS' => 'ITEMS',
    'Artículos: {importe}' => 'Items: {importe}',
    'Descuento {codigo}: -{importe}' => 'Discount {codigo}: -{importe}',
    'Envoltorio furoshiki: {importe}' => 'Furoshiki gift wrap: {importe}',
    'TOTAL (IVA incluido): {importe}' => 'TOTAL (VAT included): {importe}',
    'Desglose de IVA: base imponible {base} + IVA {porcentaje} % {cuota}'
        => 'VAT breakdown: net amount {base} + VAT at {porcentaje}% {cuota}',
    'Pago simulado con {marca} ****{ultimos} (referencia {referencia}, autorización {autorizacion}).'
        => 'Simulated payment with {marca} ****{ultimos} (reference {referencia}, authorisation {autorizacion}).',

    // --- views/mail/pedido-confirmado.php ---
    '¡Gracias por tu compra, {nombre}!' => 'Thank you for your order, {nombre}!',
    'Hemos recibido tu pedido <strong>{referencia}</strong> y el pago simulado se ha autorizado. Aquí abajo tienes tu factura <strong>{factura}</strong>; también puedes abrirla en línea para imprimirla o guardarla como PDF.'
        => 'We have received your order <strong>{referencia}</strong> and the simulated payment has been authorised. Your invoice <strong>{factura}</strong> is just below; you can also open it online to print it or save it as a PDF.',
    'Ver e imprimir la factura' => 'View and print your invoice',
    '¿Quieres seguir el pedido? <a href="{url}" style="{estilo}">Consulta su estado</a> con la referencia y este correo electrónico.'
        => 'Want to track your order? <a href="{url}" style="{estilo}">Check its status</a> using the reference and this email address.',
    'Con cariño,' => 'With love,',
    'Kitsune, Neko, Tokki y Gom' => 'Kitsune, Neko, Tokki and Gom',

    // --- views/mail/pedido-confirmado-texto.php ---
    'Hemos recibido tu pedido {referencia} y el pago simulado se ha autorizado.'
        => 'We have received your order {referencia} and the simulated payment has been authorised.',
    'Tu factura es la {factura} y la tienes copiada más abajo.' => 'Your invoice is {factura} and you will find a copy of it below.',
    'Ver e imprimir la factura:' => 'View and print your invoice:',
    'Consultar el estado del pedido (referencia + este correo):' => 'Check your order status (reference + this email address):',
    // Pie de los tres correos en texto plano, según se entreguen de verdad o no
    'Prototipo académico sin actividad comercial real: pedido, pago y factura ficticios.'
        => 'Academic prototype with no real commercial activity: the order, payment and invoice are fictitious.',
    'Prototipo académico: correo de prueba, no entregado a ningún buzón real.'
        => 'Academic prototype: test email, not delivered to any real mailbox.',

    // --- views/mail/pedido-enviado.php ---
    '¡Tu pedido va en camino, {nombre}!' => 'Your order is on its way, {nombre}!',
    // {plazo} llega traducido («within 24-48 hours») y {metodo} también («express»)
    'El pedido <strong>{referencia}</strong> acaba de salir del almacén. Llegará <strong>{plazo}</strong> (envío {metodo}, simulado).'
        => 'Order <strong>{referencia}</strong> has just left the warehouse. It will arrive <strong>{plazo}</strong> ({metodo} delivery, simulated).',
    'Seguimiento (simulado)' => 'Tracking (simulated)',
    'Transportista: {transportista}' => 'Courier: {transportista}',
    'Código:' => 'Code:',
    'Qué lleva la caja' => 'What\'s in the box',
    'Ver el estado del pedido' => 'View your order status',
    'Tu factura {factura} sigue disponible: <a href="{url}" style="{estilo}">verla o imprimirla</a>.'
        => 'Your invoice {factura} is still available: <a href="{url}" style="{estilo}">view or print it</a>.',

    // --- views/mail/pedido-enviado-texto.php ---
    'El pedido {referencia} acaba de salir del almacén.' => 'Order {referencia} has just left the warehouse.',
    'Llegará {plazo} (envío {metodo}, simulado).' => 'It will arrive {plazo} ({metodo} delivery, simulated).',
    'Seguimiento (simulado): {transportista} · {codigo}' => 'Tracking (simulated): {transportista} · {codigo}',
    'Qué lleva la caja:' => 'What\'s in the box:',
    'Dirección de entrega:' => 'Delivery address:',
    'Ver el estado del pedido:' => 'View your order status:',
    'Tu factura {factura} sigue disponible:' => 'Your invoice {factura} is still available:',

    // --- views/mail/soporte-recibido.php ---
    '¡Recibido, {nombre}! Gom se pone con ello' => 'Got it, {nombre}! Gom is on the case',
    'Hemos registrado tu solicitud con la referencia <strong style="{estilo}">{referencia}</strong>.'
        => 'We have logged your request under the reference <strong style="{estilo}">{referencia}</strong>.',
    'El equipo la revisará lo antes posible; guarda esta referencia para el seguimiento.'
        => 'The team will look into it as soon as possible; keep this reference handy for any follow-up.',
    'Tu solicitud' => 'Your request',
    'Tipo:' => 'Type:',
    'Asunto:' => 'Subject:',
    'Pedido:' => 'Order:',
    'Mensaje:' => 'Message:',
    'Puedes consultar el estado del pedido <a href="{url}" style="{estilo}">{referencia}</a> cuando quieras.'
        => 'You can check the status of order <a href="{url}" style="{estilo}">{referencia}</a> whenever you like.',

    // --- views/mail/soporte-recibido-texto.php ---
    '¡Recibido, {nombre}! Gom se pone con ello.' => 'Got it, {nombre}! Gom is on the case.',
    'Hemos registrado tu solicitud con la referencia {referencia}.' => 'We have logged your request under the reference {referencia}.',
    'Tipo: {tipo}' => 'Type: {tipo}',
    'Asunto: {asunto}' => 'Subject: {asunto}',
    'Consultar el estado del pedido:' => 'Check your order status:',
];
