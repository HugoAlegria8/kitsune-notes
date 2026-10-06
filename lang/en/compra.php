<?php
/**
 * Catálogo de traducción al inglés · proceso de compra y postventa.
 *
 * Formato: 'texto en español' => 'traducción' (véase la cabecera de comun.php).
 *
 * Este fichero reúne los textos de las páginas por las que pasa quien compra:
 * el carrito, el pago simulado, la consulta y el detalle del pedido (que es
 * también la página de confirmación) y el formulario de soporte con su acuse.
 * Los textos que estas páginas comparten con el resto de la tienda (nombres de
 * los campos, resumen económico, estados del pedido…) están en comun.php y
 * aquí no se repiten.
 *
 * Inglés británico: basket, delivery, authorised, catalogue…
 *
 * Comprobación: «php tools/comprobar_traducciones.php».
 */

declare(strict_types=1);

return [
    // --- views/cart/index.php ----------------------------------------------
    'Tu carrito está vacío' => 'Your basket is empty',
    'Tokki está triste. Anímalo con un cuaderno o un poco de washi tape.'
        => 'Tokki is sad. Cheer him up with a notebook or a bit of washi tape.',
    'Artículos del carrito' => 'Items in your basket',
    '{precio} / unidad' => '{precio} each',
    'Cantidad de {producto}' => 'Quantity of {producto}',
    'Actualizar' => 'Update',
    // El nombre del producto solo lo oyen los lectores de pantalla.
    'Quitar<span class="solo-lectores"> {producto}</span>' => 'Remove<span class="solo-lectores"> {producto}</span>',
    'Seguir mirando cositas' => 'Keep browsing cute little things',
    'Código de descuento' => 'Discount code',
    'CÓDIGO' => 'CODE',
    'Quitar' => 'Remove',
    'Continuar con el pedido' => 'Continue to checkout',
    'El proceso de compra es <strong>simulado</strong>: no se cobra nada ni se piden datos de pago reales.'
        => 'The checkout is <strong>simulated</strong>: you will not be charged and we do not ask for real payment details.',
    'Códigos de prueba disponibles' => 'Test codes available',

    // --- views/checkout/pago.php --------------------------------------------
    'Esta pasarela no cobra dinero' => 'This payment gateway does not take any money',
    'El pago se resuelve con un simulador interno. <strong>No introduzcas una tarjeta real.</strong> Usa cualquiera de estas tarjetas de prueba para ver los dos resultados posibles:'
        => 'Payment is handled by an internal simulator. <strong>Do not enter a real card.</strong> Use any of these test cards to see the two possible outcomes:',
    'Tarjetas de prueba admitidas' => 'Accepted test cards',
    'Caducidad: cualquier fecha futura (por ejemplo 12/28). CVV: tres dígitos cualesquiera.'
        => 'Expiry date: any future date (for example 12/28). CVV: any three digits.',
    'Revisa los datos de la tarjeta' => 'Please check the card details',
    'Enviar a' => 'Deliver to',
    'Modificar datos' => 'Change details',
    'Datos de la tarjeta de prueba' => 'Test card details',
    'Nombre que aparece en la tarjeta' => 'Name as it appears on the card',
    'Se valida con el algoritmo de Luhn. Solo guardamos los cuatro últimos dígitos.'
        => 'The number is checked with the Luhn algorithm. We only store the last four digits.',
    'Caducidad (MM/AA)' => 'Expiry date (MM/YY)',
    'No se almacena en ningún momento.' => 'It is never stored.',
    // Texto que kitsune.js pone en el botón mientras se envía el formulario de pago.
    'Procesando el pago simulado…' => 'Processing the simulated payment…',
    'Pagar {importe} (simulado)' => 'Pay {importe} (simulated)',
    'Al pulsar se genera el pedido y se registra el evento <code>payment.simulated</code>.'
        => 'Pressing the button creates the order and records the <code>payment.simulated</code> event.',

    // --- views/order/consulta.php -------------------------------------------
    'Introduce la referencia del pedido y el correo electrónico con el que lo hiciste. Pedimos ambos datos para que nadie pueda ver los datos de envío conociendo solo la referencia.'
        => 'Enter your order reference and the email address you used to place the order. We ask for both so that nobody can see the delivery details just by knowing the reference.',
    'Correo electrónico de la compra' => 'Email address used for the order',
    'tu@correo.test' => 'you@email.test',
    'Ver el pedido' => 'View order',
    '¿Necesitas ayuda con un pedido? Escríbenos desde el <a href="{url}">formulario de soporte</a>.'
        => 'Need help with an order? Get in touch using the <a href="{url}">support form</a>.',

    // --- views/order/detalle.php --------------------------------------------
    // Confirmación: cómo ha salido el correo (una clave por frase completa)
    '¡Pedido confirmado!' => 'Order confirmed!',
    'Tu pedido está confirmado, pero no hemos podido enviar el correo a {correo}.'
        => 'Your order is confirmed, but we could not send the email to {correo}.',
    'Tu factura {factura} está disponible en esta misma página.' => 'Your invoice {factura} is available on this page.',
    'Te hemos enviado la confirmación y la factura {factura} a {correo}.'
        => 'We have sent the confirmation and invoice {factura} to {correo}.',
    'Hemos enviado la confirmación a {correo}.' => 'We have sent the confirmation to {correo}.',
    'Si no la ves en unos minutos, revisa la carpeta de spam.' => 'If you do not see it within a few minutes, check your spam folder.',
    'Guarda la referencia: con ella y tu correo puedes consultar el estado cuando quieras.'
        => 'Keep the reference safe: together with your email address, it lets you check the order status whenever you like.',
    'Prototipo académico: el correo es real, pero el pedido, el pago y la factura son ficticios.'
        => 'Academic prototype: the email is real, but the order, the payment and the invoice are fictitious.',
    'Prototipo: el mensaje ha quedado guardado en el <a href="{url}">buzón de pruebas del back-office</a>, donde el equipo puede ver el motivo del fallo.'
        => 'Prototype: the message has been saved in the <a href="{url}">test mailbox in the back office</a>, where the team can see why it failed.',
    'Prototipo: esta dirección no está autorizada para recibir correo real, así que el mensaje solo queda en el <a href="{url}">buzón de pruebas del back-office</a>.'
        => 'Prototype: this address is not authorised to receive real email, so the message is only kept in the <a href="{url}">test mailbox in the back office</a>.',
    'Prototipo: el correo no sale de la aplicación.' => 'Prototype: the email does not leave the application.',
    'El equipo puede abrirlo en el <a href="{url}">buzón de pruebas del back-office</a>.'
        => 'The team can open it in the <a href="{url}">test mailbox in the back office</a>.',
    // Estado, artículos, entrega y pago
    'Estado del pedido' => 'Order status',
    'Líneas del pedido {referencia}' => 'Items in order {referencia}',
    'Producto' => 'Product',
    'Entrega y pago' => 'Delivery and payment',
    'Dirección de envío' => 'Delivery address',
    // Marca de tarjeta que guarda el simulador cuando no reconoce el número
    'Desconocida' => 'Unknown',
    'Referencia {referencia}' => 'Reference {referencia}',
    'Autorización {codigo}' => 'Authorisation {codigo}',
    'Todavía sin intentos de pago registrados.' => 'No payment attempts recorded yet.',
    // Importe del pedido
    'Importe del pedido' => 'Order amount',
    'Envío ({metodo})' => 'Delivery ({metodo})',
    'IVA 21 %' => 'VAT (21%)',
    'Ver factura {numero}' => 'View invoice {numero}',
    'Abrir una incidencia' => 'Report an issue',
    'Seguir comprando' => 'Continue shopping',
    'Pedido generado el {fecha}. Ningún importe ha sido cobrado: el pago es una simulación del prototipo académico.'
        => 'Order placed on {fecha}. Nothing has been charged: payment is simulated in this academic prototype.',

    // --- views/support/form.php ---------------------------------------------
    'Cuéntanos qué ha pasado. Cada solicitud genera una referencia de seguimiento y queda registrada como evento (<code>support.requested</code> o <code>incident.created</code>) para que el equipo interno pueda tratarla.'
        => 'Tell us what happened. Each request gets a tracking reference and is logged as an event (<code>support.requested</code> or <code>incident.created</code>) so that the internal team can deal with it.',
    'Si la indicas, la incidencia se vincula al pedido.' => 'If you include it, the issue will be linked to the order.',
    'Describe lo que ha ocurrido con el mayor detalle posible.' => 'Describe what happened in as much detail as possible.',
    'Enviar solicitud' => 'Send request',
    'Prototipo académico: no envíes datos personales reales. El mensaje se guarda en la base de datos de pruebas y el acuse de recibo queda en el buzón de pruebas del equipo; solo se envía por correo real a las direcciones que el equipo ha autorizado expresamente.'
        => 'Academic prototype: do not send real personal information. The message is stored in the test database and the acknowledgement stays in the team\'s test mailbox; it is only sent by real email to addresses the team has expressly authorised.',

    // --- views/support/enviado.php ------------------------------------------
    '¡Recibido! Gom se pone con ello' => 'Received! Gom is on the case',
    'Guarda esta referencia para el seguimiento.' => 'Keep this reference to follow up on your request.',
    'Qué ha ocurrido por dentro' => 'What happened behind the scenes',
    'Se ha creado un registro en la tabla <code>support_tickets</code> con estado «abierta».'
        => 'A record has been created in the <code>support_tickets</code> table with the status “open”.',
    'Se ha emitido el evento <code>{evento}</code>, almacenado en base de datos y en el fichero de eventos del día.'
        => 'The <code>{evento}</code> event has been emitted and stored in the database and in today\'s event file.',
    // Acuse de recibo: cómo ha salido el correo (una clave por frase completa)
    'Se ha enviado un acuse de recibo a <strong>{correo}</strong> (si no lo ves, revisa la carpeta de spam).'
        => 'An acknowledgement has been sent to <strong>{correo}</strong> (if you do not see it, check your spam folder).',
    'Además queda guardado en el <a href="{url}">buzón de pruebas</a> del back-office.'
        => 'It is also saved in the <a href="{url}">test mailbox</a> in the back office.',
    'No se ha podido enviar el acuse de recibo a <strong>{correo}</strong>; queda guardado en el <a href="{url}">buzón de pruebas</a> del back-office, donde se ve el motivo del fallo.'
        => 'The acknowledgement could not be sent to <strong>{correo}</strong>; it is saved in the <a href="{url}">test mailbox</a> in the back office, where the reason for the failure is shown.',
    'El acuse de recibo para <strong>{correo}</strong> no se ha enviado por correo real porque esa dirección no está autorizada: queda en el <a href="{url}">buzón de pruebas</a> del back-office.'
        => 'The acknowledgement for <strong>{correo}</strong> has not been sent by real email because that address is not authorised: it stays in the <a href="{url}">test mailbox</a> in the back office.',
    'Se ha generado un acuse de recibo para <strong>{correo}</strong>.'
        => 'An acknowledgement has been generated for <strong>{correo}</strong>.',
    'En el prototipo no sale de la aplicación: queda en el <a href="{url}">buzón de pruebas</a> del back-office.'
        => 'In the prototype it does not leave the application: it stays in the <a href="{url}">test mailbox</a> in the back office.',
    'El pedido <strong>{referencia}</strong> ha pasado al estado «con incidencia» para que el equipo lo revise.'
        => 'Order <strong>{referencia}</strong> has been moved to the “issue reported” status so that the team can review it.',
    'Volver al catálogo' => 'Back to the catalogue',
];
