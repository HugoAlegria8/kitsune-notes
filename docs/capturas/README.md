# Capturas de evidencia

Capturas tomadas sobre la aplicación en ejecución mediante un recorrido automatizado
(Chromium) del flujo de compra completo y del back-office. Sirven como evidencia para la
memoria y como apoyo de la exposición-defensa si fallase la demostración en vivo.

| Fichero | Contenido |
|---|---|
| `01-portada.jpg` | Portada: personajes, favoritos y categorías |
| `02-catalogo.jpg` | Catálogo con filtros por categoría y por colección |
| `03-coleccion-neko.jpg` | Página de una colección (línea de diseño) con su personaje |
| `04-colecciones.jpg` | Las cuatro colecciones: Kitsune, Neko, Tokki y Gom |
| `05-ficha-producto.jpg` | Ficha de producto con ficha técnica y desglose de IVA |
| `06-carrito.jpg` | Carrito con el cupón `KITSUNE10` aplicado |
| `07-checkout.jpg` | Datos de envío, método de envío y envoltorio: el resumen de la derecha se actualiza al elegir el envío o marcar el envoltorio |
| `08-pago-rechazado.jpg` | Pago simulado rechazado con la tarjeta `4000 0000 0000 0002` |
| `09-pedido-confirmado.jpg` | Pedido confirmado tras reintentar con `4242…` (mismo pedido), con el aviso de la factura emitida |
| `10-soporte-enviado.jpg` | Incidencia registrada sobre el pedido |
| `11-carrito-vacio.jpg` | Estado vacío del carrito |
| `12-error-404.jpg` | Página de error |
| `13-admin-login.jpg` | Acceso al back-office |
| `14-admin-panel.jpg` | Panel con métricas de pedidos, eventos e incidencias |
| `15-admin-productos.jpg` | Listado de productos con stock, ventas y estado |
| `16-admin-producto-nuevo.jpg` | Alta de producto con selector de ilustración y el bloque opcional de la versión en inglés |
| `17-admin-producto-editar.jpg` | Edición, historial y zona de retirar o eliminar |
| `18-admin-eventos.jpg` | Eventos registrados con su carga útil JSON |
| `19-admin-pedido.jpg` | Detalle de pedido: idioma y moneda de la compra, pagos, estados, cambio de estado, factura y correos enviados |
| `20-movil-portada.jpg` | Portada en 390 px de ancho |
| `21-movil-ficha.jpg` | Ficha de producto en móvil |
| `22-factura.jpg` | Factura del cliente (`F-AAAA-NNNNNN`) con desglose de IVA y sello de prueba |
| `23-admin-buzon.jpg` | Buzón de correos de prueba del back-office, con los no leídos marcados |
| `24-admin-correo.jpg` | Correo de confirmación tal y como lo ve el cliente, con la factura incrustada |
| `25-movil-factura.jpg` | Factura en 390 px, abierta con el enlace firmado del correo |
| `26-movil-buzon.jpg` | Buzón de correos en móvil (cada fila se resume en una sola celda) |
| `27-admin-buzon-smtp.jpg` | Buzón con el **envío real activado**: banner del modo, correo «Enviado por SMTP», «Solo buzón» (dirección no autorizada) y «Fallo de envío» |
| `28-pedido-confirmado-smtp.jpg` | Confirmación de un pedido cuyo correo se ha entregado por SMTP (recuerda revisar el spam) |
| `29-admin-correo-fallido.jpg` | Detalle de un correo cuyo envío falló: la compra se completó y el motivo queda anotado |
| `30-portada-cookies.jpg` | Portada en escritorio en la primera visita, con el **aviso de cookies** abajo (informativo: la tienda solo usa cookies técnicas) |
| `31-movil-cookies.jpg` | El mismo aviso en 390 px: el texto y el botón «Entendido» pasan a ancho completo |
| `32-portada-en.jpg` | Portada **en inglés**: los botones `ES` / `EN` están en la franja superior, junto al aviso académico, con el idioma activo resaltado |
| `33-checkout-en.jpg` | Checkout en inglés con los importes **en libras** (`£` delante del número), envío exprés y envoltorio marcados |
| `34-pedido-confirmado-en.jpg` | Pedido confirmado en inglés y en libras |
| `35-factura-en.jpg` | Factura en inglés y en libras, con el **tipo de cambio aplicado** y el contravalor en euros del IVA y del total |
| `36-pedido-en-libras-visto-en-espanol.jpg` | El mismo pedido tras pulsar `ES`: la página vuelve al español, pero el pedido **sigue en libras** y conserva los nombres de producto con los que se compró |
| `37-admin-pedido-en-libras.jpg` | Back-office (siempre en español): ese pedido con el idioma y la moneda de la compra, el contravalor en euros y el tipo de cambio guardado |
| `38-admin-producto-version-en-ingles.jpg` | Back-office: bloque «Versión en inglés (opcional)» de la ficha de un producto del catálogo |
| `39-movil-portada-en.jpg` | Portada en inglés en 390 px: los botones de idioma comparten línea con «More information» |

Las capturas `01` a `26` están tomadas con la configuración por defecto (envío real
desactivado: los correos solo llegan al buzón de pruebas), con la tienda en español y con el
aviso de cookies ya cerrado (en la portada, `01` y `20`, aparecería abajo en la primera
visita, como muestran `30` y `31`). Las capturas `27` a `29` están tomadas contra un
**servidor SMTP de pruebas local** con direcciones ficticias (no contra Gmail): documentan el
comportamiento de la sección «Envío real de correos» del `README.md` (autorizada, no
autorizada y servidor que rechaza la contraseña).

Las capturas `32` a `39` documentan la sección «Idiomas y monedas» del `README.md`: están
tomadas con la tienda en inglés (botón `EN` de la franja superior) y con el tipo de cambio
fijo de demostración por defecto (1 € = £0,85). De `33` a `37` se sigue siempre la
misma compra, hecha en inglés y guardada en libras.

Para regenerar las primeras basta con arrancar la aplicación y repetir el recorrido descrito
en el apartado 5 del `README.md` (el aviso de cookies se cierra con «Entendido»; `30` y `31` se
toman en una visita nueva a la portada, sin cookies). Para `27` a `29` hay que activar el envío
real (sección 7) y hacer una compra con una dirección autorizada, otra que no lo esté y otra
con una contraseña SMTP incorrecta. Para `32` a `39`, pulsar `EN` y repetir la compra (paso 14
del recorrido); `36` es la página de ese pedido después de pulsar `ES`.
