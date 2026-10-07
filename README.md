# Kitsune Notes · canal digital de venta instrumentado

Prototipo funcional de tienda en línea para **Kitsune Notes**, una papelería ficticia de
material de escritorio y organización importado de Japón y Corea, con estética kawaii.
El catálogo permanente se organiza por **categoría de producto** (qué es) y por
**línea de diseño** (cómo es). En la tienda, cada línea de diseño es una **colección con
personaje propio**: Kitsune (zorrito), Neko (gatito), Tokki (conejito) y Gom (osito).

Desarrollado para la **Tarea 1** de la asignatura *Soluciones Informáticas para la Empresa*
(Grado en Ingeniería Informática, UCAM).

> **Aviso.** Es un prototipo académico sin actividad comercial real. La empresa, las marcas,
> los personajes, los productos, los precios, los clientes, los pagos y los pedidos son
> ficticios. No se realiza ningún cobro y no se envía ningún producto.

---

## 1. Qué incluye

| Requisito de la tarea | Dónde está implementado |
|---|---|
| Página principal | `/` (con un aviso informativo de cookies hasta que se cierra) |
| Catálogo por categorías | `/catalogo`, `/categoria/{slug}` |
| Líneas de diseño (colecciones) | `/colecciones`, `/coleccion/{slug}` y filtro en el catálogo |
| Ficha de producto | `/producto/{slug}` (descripción, ficha técnica, stock, desglose de IVA) |
| Al menos 8 productos | 12 referencias: 3 por categoría y 3 por colección |
| Carrito | `/carrito` (añadir, actualizar, quitar, cupón) |
| Checkout | `/checkout` → `/pago` |
| Impuestos, gastos y descuentos | IVA 21 %, envío estándar/exprés, envío gratis desde 35 €, envoltorio opcional, dos cupones |
| Pago simulado | `/pago` con tarjetas de prueba (autorización y rechazo) |
| Pedido con identificador único | Referencia `KN-AAAA-NNNNNN` |
| Estados de pedido | creado, pagado simulado, pendiente de preparación, enviado, entregado, cancelado, con incidencia |
| Factura | `/pedido/{referencia}/factura`: factura numerada `F-AAAA-NNNNNN` con desglose de IVA, imprimible o guardable como PDF |
| Correo de confirmación | Se «envía» al cliente al pagar; por defecto queda en un **buzón de pruebas** (`/admin/correos`) y, si se activa, **se entrega de verdad por SMTP** a las direcciones autorizadas (sección 7) |
| Aviso de cookies | Banner informativo en la portada (solo hay cookies técnicas), con detalle en `/aviso-academico#cookies` |
| Idiomas y monedas | Botones **ES / EN** en la franja superior. En español todo sigue igual y en euros (`12,90 €`); en inglés la tienda se ve traducida y vende en libras (`£10.97`), con el pedido, la factura y los correos en ese idioma y esa moneda (sección 17) |
| Back-office | `/admin`: pedidos y cambio de estado, **gestión de productos**, **buzón de correos de prueba**, eventos e incidencias |
| Persistencia | SQLite por defecto, MySQL/MariaDB opcional (ambos vía PDO) |
| Instrumentación de eventos | 6 eventos exigidos + 11 adicionales, en base de datos, fichero JSONL y API |

---

## 2. Requisitos

* **PHP 8.1 o superior** con las extensiones `pdo_sqlite` (o `pdo_mysql`), `mbstring`,
  `fileinfo` y `json`.
* Solo si se activa el envío real de correos (sección 7): la extensión `openssl`.
* Nada más: **no hace falta Composer, Node ni ningún paso de compilación.**

```bash
php -v
php -m | grep -E "pdo_sqlite|mbstring|fileinfo|json|openssl"
```

---

## 3. Instalación y ejecución en local

```bash
# 1. Clonar el repositorio
git clone <URL-DEL-REPOSITORIO> kitsune-notes
cd kitsune-notes

# 2. Crear el fichero de configuración local a partir del ejemplo
cp .env.example .env

# 3. Crear la base de datos y cargar los datos de prueba
php bin/install.php

# 4. Arrancar el servidor de desarrollo
php -S localhost:8000 -t public
```

Abrir <http://localhost:8000>.

Para **rehacer la base de datos desde cero** (borra pedidos, eventos y productos creados):

```bash
php bin/install.php --fresh
```

Si ya tenías una versión anterior del proyecto:

* **Sin facturas ni buzón de correos** (le faltan las tablas `invoices` y `mail_outbox`):
  ejecuta `php bin/install.php --fresh`.
* **Con facturas pero sin envío real de correos**: no hace falta hacer nada. Al arrancar, la
  tienda añade sola a `mail_outbox` las tres columnas de entrega (`delivery_status`,
  `delivery_detail`, `delivered_at`) y conserva tus datos.

### Windows

1. **Instala PHP 8.1 o superior** (probado con 8.4). Descarga el ZIP *VS17 x64 Non Thread Safe*
   de <https://windows.php.net/download/>, extráelo en `C:\php` (debe existir `C:\php\php.exe`)
   y añade `C:\php` al `PATH` de usuario (PowerShell, sin permisos de administrador):

   ```powershell
   [Environment]::SetEnvironmentVariable("Path", [Environment]::GetEnvironmentVariable("Path","User") + ";C:\php", "User")
   ```

   Cierra y vuelve a abrir PowerShell. Si `php -v` se queja de `VCRUNTIME140.dll`, instala el
   *Visual C++ Redistributable 2015-2022 x64* (<https://aka.ms/vs/17/release/vc_redist.x64.exe>).

2. **Crea el `php.ini` y activa las extensiones del proyecto** (PowerShell):

   ```powershell
   cd C:\php
   Copy-Item php.ini-development php.ini
   (Get-Content php.ini) `
     -replace '^;extension_dir = "ext"', 'extension_dir = "ext"' `
     -replace '^;extension=(fileinfo|mbstring|pdo_sqlite|sqlite3|openssl)\s*$', 'extension=$1' |
     Set-Content php.ini -Encoding ASCII
   ```

   `openssl` solo es necesaria para el envío real de correos (sección 7), pero conviene
   activarla ya. **Si ya habías ejecutado este paso sin `openssl`**, actívala ahora con:

   ```powershell
   (Get-Content C:\php\php.ini) -replace '^;extension=openssl\s*$', 'extension=openssl' |
     Set-Content C:\php\php.ini -Encoding ASCII
   ```

3. **Comprueba la instalación**:

   ```powershell
   php -v
   php -m | findstr /i "pdo_sqlite mbstring fileinfo json openssl"
   ```

4. **Arranca el proyecto.** En la carpeta del proyecto, doble clic en **`iniciar-windows.bat`**
   (crea el `.env` y la base de datos si faltan y arranca el servidor en
   <http://localhost:8000>), o a mano:

   ```powershell
   Copy-Item .env.example .env
   php bin/install.php
   php -S localhost:8000 -t public
   ```

Si ves símbolos raros en la consola al instalar es solo una cuestión de codificación de la
ventana (`chcp 65001` la corrige); no afecta a la aplicación.

Para que la factura llegue a una bandeja de entrada real, sigue la sección 7 («Envío real de
correos»); en Windows solo hay que añadir, si da un error de certificado, el paquete
`cacert.pem` que se explica allí.

---

## 4. Usuarios y datos de prueba

### Back-office

| Campo | Valor |
|---|---|
| URL | <http://localhost:8000/admin/login> |
| Usuario | `admin@kitsunenotes.test` |
| Contraseña | la de `KN_ADMIN_PASSWORD` en `.env` (por defecto `kitsune-demo-2026`) |

Son credenciales **de demostración** para un prototipo: no corresponden a ninguna cuenta real.
La contraseña no está escrita en el código; se lee del fichero `.env` (no versionado) y se
almacena cifrada con `password_hash()`.

### Clientes de demostración

La tienda permite comprar como invitado, así que los clientes no tienen contraseña.
El seed carga tres clientes ficticios con el dominio reservado `.test`:

* `ana.demo@kitsunenotes.test` — Ana Demo Ruiz (Murcia)
* `bruno.demo@kitsunenotes.test` — Bruno Demo Serra (Valencia)
* `clara.demo@kitsunenotes.test` — Clara Demo Vidal (Madrid)

Para consultar un pedido desde `/pedidos` hacen falta **la referencia y el correo** con el que
se hizo la compra.

Cada pedido de ejemplo trae ya su factura (`F-2026-000001` a `000003`) y su correo de
confirmación en el buzón de pruebas (además de un aviso de envío y un acuse de soporte).

### Tarjetas de prueba (pago simulado)

| Número | Resultado |
|---|---|
| `4242 4242 4242 4242` | Autorizado (VISA) |
| `5555 5555 5555 4444` | Autorizado (Mastercard) |
| `4000 0000 0000 0002` | Rechazado — fondos insuficientes |
| `4000 0000 0000 0069` | Rechazado — tarjeta caducada |

Caducidad: cualquier fecha futura (`12/28`). CVV: tres dígitos.
Cualquier otro número que supere la validación de Luhn se autoriza.

### Códigos de descuento

| Código | Descuento | Condición |
|---|---|---|
| `KITSUNE10` | 10 % sobre los artículos | sin mínimo |
| `FRESITA5` | 5 € | mínimo 25 € en artículos |

---

## 5. Recorrido de demostración (8 minutos)

1. **Portada** → leer el **aviso de cookies** de la primera visita y cerrarlo con «Entendido» (no
   vuelve a salir en 180 días); elegir una colección (por ejemplo, Neko) y ver cómo cruza varias
   categorías.
2. **Ficha de producto** → se emite `product.viewed`.
3. **Añadir al carrito** → se emite `cart.item_added`.
4. **Carrito** → aplicar `KITSUNE10` y comprobar el recálculo de descuento, envío e IVA.
5. **Checkout** → datos de envío, método de envío y envoltorio → se emite `checkout.started`.
   Al cambiar el método de envío o marcar el envoltorio, el resumen se recalcula al momento
   (lo calcula el servidor; sin JavaScript hay un botón «Actualizar total»).
6. **Pago** → primero con `4000 0000 0000 0002` (rechazo) y después con `4242…` (autorización).
   El pedido se crea una sola vez: el segundo intento reutiliza el mismo pedido (salvo que
   entre medias se cambie el carrito o el idioma, y con él la moneda: entonces el pedido sin
   pagar se cancela, se devuelve su stock y se genera uno nuevo con el importe que se ve).
   Se emiten `order.created` y `payment.simulated` (dos veces, con resultados distintos).
   El rechazo **no** genera factura ni consume número.
7. **Confirmación** → referencia `KN-2026-NNNNNN`, cronología de estados y el número de la
   **factura** (`F-2026-NNNNNN`) con el botón «Ver factura». Se emiten `invoice.issued` y
   `email.sent`.
8. **Factura** → emisor, cliente, líneas, desglose de IVA y datos del pago. «Imprimir o guardar
   como PDF» abre el diálogo del navegador con una hoja A4 sin menús ni botones.
9. **Soporte** → abrir una incidencia sobre el pedido: se emite `incident.created`, el pedido
   pasa automáticamente a «con incidencia» y se deja un acuse de recibo en el buzón.
10. **Back-office · correos** → entrar en `/admin/login`, abrir **Correos** y leer el correo de
    confirmación tal y como lo recibiría el cliente (con la factura dentro); descargarlo como `.eml`.
    Con el envío real activado (sección 7) y una compra hecha con una dirección autorizada, el
    mismo correo llega además a la bandeja de entrada y el buzón lo marca como «Enviado por SMTP».
11. **Back-office · pedidos** → cambiar el estado a «enviado» (se emite `order.status_changed` y
    se deja en el buzón el aviso de envío).
12. **Back-office · productos** → dar de alta un producto, editar su precio (el evento
    `product.updated` guarda el antes y el después), retirarlo del catálogo y comprobar que
    un producto con ventas no se puede borrar.
13. **Back-office · eventos** → la traza completa y la exportación JSON/CSV.
14. **Idioma y moneda** → volver a la tienda y pulsar **EN** en la franja superior: los textos
    pasan a inglés y los precios a libras con el símbolo delante (`£10.97`). Hacer una compra:
    el pedido se guarda en libras con su tipo de cambio, y la factura y el correo salen en
    inglés. Pulsar **ES** en la página del pedido: el marco vuelve al español, pero el pedido
    y su factura siguen en libras y la factura sigue en inglés. En el back-office ese pedido
    aparece en libras, con su contravalor en euros.

---

## 6. Gestión de productos (back-office)

Desde `/admin/productos` el personal puede:

| Acción | Qué hace | Evento |
|---|---|---|
| **Alta** | Crea un producto. SKU (`KN-CUA-004`…) y dirección web se generan solos si se dejan vacíos. | `product.created` |
| **Edición** | Modifica cualquier campo. Solo se guarda si algo ha cambiado. | `product.updated` con los campos cambiados |
| **Retirar** | Lo oculta de la tienda sin borrarlo. Sus pedidos y eventos se conservan. Reversible. | `product.archived` |
| **Volver a publicar** | Deshace la retirada. | `product.restored` |
| **Eliminar** | Borrado definitivo, con casilla de confirmación. **Solo si nunca se ha vendido.** | `product.deleted` |

Reglas de negocio aplicadas:

* **Un producto vendido no se borra, se retira.** Las líneas de pedido lo referencian y el
  histórico de ventas tiene que seguir siendo consultable. El formulario explica el motivo
  y muestra cuántas líneas de pedido y cuántos eventos tiene el producto.
* **Los eventos sobreviven al borrado.** La tabla `events` no declara claves ajenas a
  propósito y la carga útil lleva SKU y nombre, de modo que el rastro de un producto eliminado
  sigue siendo legible.
* **Validación completa**: precio en euros (`12,90`), precio tachado mayor que el precio
  actual, stock y peso en rangos, SKU y dirección web únicos, ficha técnica en formato
  `Clave: valor`, categoría y colección existentes.
* **Versión en inglés opcional**: nombre, resumen, descripción y ficha técnica tienen su campo
  en inglés. Lo que se deja vacío se muestra en español en la tienda en inglés, y el listado
  marca el producto como «sin traducir». El precio se escribe siempre en euros (sección 17).
* **Imagen**: se elige entre las ilustraciones del proyecto o se sube un PNG, JPEG o WebP de
  hasta 2 MB. El tipo se comprueba por el **contenido** del fichero (no por la extensión), se
  guarda con un nombre aleatorio en `public/uploads/productos/` y esa carpeta tiene un
  `.htaccess` que impide ejecutar scripts. Al cambiar o borrar un producto, la imagen subida
  que ya no usa nadie se elimina.

---

## 7. Facturas y correos (buzón de pruebas y envío real opcional)

El canal digital emite una factura por cada pedido pagado y la «envía» por correo al cliente.
Como el prototipo no puede (ni debe) escribir a clientes reales, **por defecto el correo no sale
de la aplicación**: se guarda en un buzón de pruebas que consulta el personal del back-office.

Si el equipo quiere ver llegar la factura a una bandeja de entrada de verdad (por ejemplo, para
la demostración), puede activar el **envío real por SMTP**, que es opcional, está desactivado
por defecto y solo entrega a las direcciones que el equipo autorice expresamente (más abajo,
«Envío real de correos»).

### Cómo ver la factura que «llega al correo» (modo por defecto)

1. Haz una compra con una tarjeta autorizada (`4242 4242 4242 4242`). La confirmación dice
   «Te hemos enviado la confirmación y la factura F-2026-NNNNNN a …» y, debajo, aclara que en el
   prototipo el correo no sale de la aplicación y queda en el buzón de pruebas.
2. Entra en el back-office (`/admin/login`) y abre **Correos** (el menú indica cuántos están sin
   leer). Abre «Pedido … confirmado · Factura …»: se ve igual que lo vería el cliente, con la
   factura incrustada.
3. Desde ahí, **Ver factura** abre el documento imprimible (*Guardar como PDF* desde el
   navegador) y **Descargar .eml** permite abrir el mensaje en un cliente de correo
   (Outlook, Thunderbird…).
4. El cliente ve la misma factura desde la página de su pedido («Ver factura») o con el enlace
   firmado que lleva el correo.

### Qué correos se generan

| Plantilla | Cuándo | Contenido |
|---|---|---|
| `pedido_confirmado` | Pago autorizado | Confirmación, factura incrustada y enlace firmado |
| `pedido_enviado` | El pedido pasa a «enviado» | Aviso de envío |
| `soporte_recibido` | Se registra una solicitud de soporte o una incidencia | Acuse de recibo con la referencia |
| `prueba_smtp` | Solo al ejecutar `php bin/probar-correo.php` | Mensaje de comprobación del envío real |

### Reglas de la factura

* Solo se emite para un pedido con **pago autorizado**: un pago rechazado no genera factura ni
  consume número.
* Numeración **correlativa y sin huecos por año** (`F-AAAA-NNNNNN`): se asigna dentro de una
  transacción, con restricción `UNIQUE(año, número)` y reintento si dos pagos coinciden.
* **Idempotente**: pedir la factura de un pedido que ya la tiene devuelve la existente. El
  back-office puede reenviar el correo, pero nunca crea una segunda factura.
* **Inmutable**: al emitirla se guarda una copia congelada (emisor, cliente, líneas, descuento,
  envío, desglose de IVA y pago). Editar un producto o los datos del cliente no altera facturas
  ya emitidas.
* Lleva el sello **«Factura de prueba · sin validez fiscal»** y un emisor ficticio (NIF `B00000000`).
* Si falla la emisión o el correo, **la compra no se rompe**: se registra el error y el personal
  puede reintentarlo con «Reenviar confirmación y factura» (con el envío real activado, el
  reintento vuelve a intentar la entrega por SMTP).

### Seguridad del buzón

* El HTML del correo se muestra en un `<iframe sandbox>` sin scripts: aunque el nombre de un
  cliente contenga código, no se ejecuta.
* Los enlaces a la factura que llevan los correos están firmados con **HMAC-SHA256** (clave
  `KN_LINK_SECRET`). Sin sesión del comprador ni firma válida, la factura no se muestra: se
  redirige a la consulta de pedidos.
* El `.eml` descargable sanea las cabeceras (sin inyección) y codifica asunto y cuerpo
  (RFC 2047 y *quoted-printable*).
* El evento `email.sent` no guarda la dirección del destinatario ni el texto del error del servidor.

### Envío real de correos (opcional)

Todos los mensajes salen por un único punto, `Mailer::send()`: guarda siempre el mensaje en el
buzón de pruebas y, **solo si el envío real está activado y el destinatario está autorizado**, lo
entrega además por SMTP con un cliente propio (`src/Support/SmtpClient.php`, sin dependencias).
Las plantillas (`views/mail/`) son las mismas en los dos modos.

| Situación | Qué ocurre | Insignia en el buzón |
|---|---|---|
| Envío real desactivado (por defecto) | Solo se guarda en el buzón | Solo buzón |
| Activado, destinatario **autorizado** | Se entrega por SMTP y se guarda | Enviado por SMTP |
| Activado, destinatario **no autorizado** (p. ej. un correo inventado en el checkout) | Solo se guarda en el buzón | Solo buzón |
| Activado, pero el servidor falla (contraseña mala, sin red, certificado…) | El pedido se completa igualmente; el correo se guarda y se anota el motivo | Fallo de envío |

#### Paso a paso con Gmail

> Recomendación: usa una **cuenta de Gmail secundaria** creada para el proyecto y, al terminar
> la demostración, **revoca la contraseña de aplicación** (y no la compartas con nadie).

1. En <https://myaccount.google.com/security> activa la **verificación en dos pasos** de esa
   cuenta (sin ella Google no permite crear contraseñas de aplicación).
2. Entra en <https://myaccount.google.com/apppasswords>, ponle un nombre (por ejemplo
   «Kitsune Notes») y pulsa **Crear**. Google muestra una contraseña de **16 caracteres** (en
   cuatro grupos de cuatro): cópiala en ese momento, no se vuelve a mostrar.
   Esta opción no aparece si la verificación en dos pasos es solo con llaves de seguridad, si la
   cuenta tiene activada la Protección avanzada o si es una cuenta de trabajo o de centro
   educativo (por ejemplo, una cuenta institucional): en esos casos usa otra cuenta de Gmail.
   Google indica que las contraseñas de aplicación no se recomiendan y que en la mayoría de los
   casos no son necesarias (prefiere «Iniciar sesión con Google»); para este prototipo, que solo
   implementa usuario y contraseña, son la forma más simple.
3. Abre el fichero **`.env`** del proyecto (el tuyo, **no** `.env.example`) y añade al final:

   ```ini
   KN_MAIL_TRANSPORT=smtp
   KN_SMTP_HOST=smtp.gmail.com
   KN_SMTP_PORT=587
   KN_SMTP_ENCRYPTION=tls
   KN_SMTP_USER=tu.cuenta@gmail.com
   KN_SMTP_PASS=abcd efgh ijkl mnop
   KN_MAIL_FROM=tu.cuenta@gmail.com
   KN_MAIL_ALLOWED_TO=tu.cuenta@gmail.com
   ```

   `KN_SMTP_USER` es la dirección completa; `KN_SMTP_PASS` es la contraseña de aplicación (vale
   con o sin espacios); `KN_MAIL_FROM` debe ser la misma cuenta (Gmail solo admite como
   remitente la propia cuenta o un alias verificado); `KN_MAIL_ALLOWED_TO` es la **lista de
   destinatarios autorizados** y puede llevar varias direcciones separadas por comas.
4. Guarda el `.env` (el servidor lo lee en cada petición: no hace falta reiniciarlo) y
   **comprueba la configuración sin hacer una compra**:

   ```bash
   php bin/probar-correo.php tu.cuenta@gmail.com
   ```

   El script usa exactamente el mismo código que la tienda y dice si el servidor ha aceptado el
   mensaje o, si no, en qué paso falló y qué hacer. Nunca muestra la contraseña.
5. Haz una compra de prueba (tarjeta `4242 4242 4242 4242`) escribiendo en el checkout **esa
   misma dirección** como correo del cliente. Al confirmar el pedido llegará a tu bandeja de
   entrada el correo «Pedido … confirmado · Factura …» con la factura dentro (la primera vez,
   mira también la carpeta de spam). En **Admin → Correos** el mensaje aparece con la insignia
   «Enviado por SMTP». Con esa misma dirección también te llegarán el aviso de envío (al pasar
   el pedido a «enviado» desde el back-office) y el acuse de recibo de una solicitud de soporte.

#### Variables de configuración

Todas se escriben en el `.env` local; ninguna tiene valor por defecto que envíe correo.

| Variable | Significado | Por defecto |
|---|---|---|
| `KN_MAIL_TRANSPORT` | `buzon` (solo buzón de pruebas) o `smtp` (envío real) | `buzon` |
| `KN_MAIL_ALLOWED_TO` | Direcciones (`a@b.es`) o dominios (`@b.es`) autorizados, separados por comas | vacío = nadie |
| `KN_SMTP_HOST` | Servidor SMTP (`smtp.gmail.com`, o el de tu hosting) | — |
| `KN_SMTP_PORT` | `587` (STARTTLS) o `465` (TLS implícito) | `587` |
| `KN_SMTP_ENCRYPTION` | `tls` (STARTTLS), `ssl` (TLS implícito) o `none` (solo para servidores locales de prueba) | `tls` |
| `KN_SMTP_USER`, `KN_SMTP_PASS` | Usuario y contraseña del servidor SMTP | — |
| `KN_MAIL_FROM`, `KN_MAIL_FROM_NAME` | Remitente que ve el cliente | `pedidos@kitsunenotes.test`, `Kitsune Notes` |
| `KN_SMTP_TIMEOUT` | Segundos de espera por operación | `10` |
| `KN_SMTP_CAFILE` | Ruta del paquete de certificados `cacert.pem` (Windows) | — |
| `KN_APP_URL` | URL pública de la tienda, para los enlaces del correo | la de la petición (`http://localhost:8000` en local) |
| `KN_COMPANY_EMAIL` | Correo de contacto **ficticio** que sale en la factura | `pedidos@kitsunenotes.test` |

Cualquier servidor SMTP con usuario y contraseña y STARTTLS o TLS implícito sirve; si la tienda se
despliega en un hosting, lo más sencillo suele ser el SMTP del propio hosting (por ejemplo,
`smtp.tudominio.es`, puerto 465, cifrado `ssl`).

#### Windows: `openssl` y certificados

* **`openssl`** es necesaria para el correo cifrado. Si no está activada, el propio programa lo
  dice («La extensión openssl de PHP no está activada…»). Se activa como se explica en el
  apartado «Windows» (paso 2) y se comprueba con `php -m | findstr /i openssl`.
* Si el envío falla con un error de **certificado no verificable**, es que PHP no encuentra la
  lista de autoridades de confianza (algo habitual en Windows). Descarga
  <https://curl.se/ca/cacert.pem>, guárdalo como `C:\php\cacert.pem` y añade al `.env`:

  ```ini
  KN_SMTP_CAFILE=C:\php\cacert.pem
  ```

  La verificación del certificado **no se puede desactivar**: es la forma de asegurarse de que la
  contraseña solo llega al servidor de verdad.

#### Problemas frecuentes

| Mensaje (resumido) | Causa y solución |
|---|---|
| «El servidor rechazó el usuario o la contraseña (535 …)» o «(534 … Application-specific password required)» | Con Gmail: se ha usado la contraseña normal de la cuenta en lugar de la de aplicación, la contraseña de aplicación está mal copiada o revocada, el usuario no es la dirección completa, o falta la verificación en dos pasos. Crea otra contraseña de aplicación y cópiala en `KN_SMTP_PASS`. |
| «… no está autorizada» / «no está en KN_MAIL_ALLOWED_TO» | La dirección del checkout no figura en `KN_MAIL_ALLOWED_TO`. Añádela (o usa la que sí figura). |
| Certificado no verificable | Ver «Windows: openssl y certificados». |
| «La extensión openssl de PHP no está activada» | Activa `extension=openssl` en `php.ini` y reinicia el servidor. |
| Tiempo de espera agotado o conexión rechazada | Sin red o puerto bloqueado (algunas redes bloquean el 587; prueba `465` con `KN_SMTP_ENCRYPTION=ssl`). |
| «Fallo de envío» en el buzón, pero la compra terminó | Es lo esperado: el motivo exacto está en **Admin → Correos → el mensaje → Entrega**. Corrige el `.env` y pulsa «Reenviar confirmación y factura» en el pedido. |
| No llega nada, sin error | Revisa spam; comprueba que el pedido se hizo con una dirección autorizada (la insignia debe decir «Enviado por SMTP»). |

#### Garantías de seguridad del envío real

* **Desactivado por defecto** y, aun activado, **solo escribe a direcciones autorizadas**
  (comparación exacta; los dominios hay que escribirlos como `@dominio`). Una dirección que
  parezca autorizada pero no lo sea (`tu.cuenta@gmail.com.otro.es`, `otro+tu.cuenta@gmail.com`)
  no se envía.
* **Cifrado obligatorio y certificado siempre verificado** (TLS 1.2 o superior). Sin cifrado solo
  se admiten servidores locales de prueba. Nunca se envía la contraseña por una conexión sin
  cifrar hacia Internet.
* **Las credenciales viven solo en el `.env` local** (que está en `.gitignore`). No se escriben en
  la base de datos, en los eventos, en el log de errores, en los mensajes de error ni en
  ninguna pantalla; el buzón muestra las direcciones autorizadas enmascaradas (`t***@gmail.com`).
* **Sin inyección**: los nombres y direcciones se validan, las cabeceras se sanean y una
  dirección con saltos de línea o comandos SMTP se rechaza antes de abrir la conexión.
* **El fallo del correo no rompe la compra**: el pedido y la factura se crean igualmente y el
  correo queda en el buzón con su motivo. Los eventos `email.sent` registran el `canal`
  (`buzon_pruebas` o `smtp`) y el resultado (`solo_buzon`, `enviado` o `fallido`), nunca la
  dirección ni el texto del error.
* **Esperas acotadas**: cada envío tiene un límite total de tres veces `KN_SMTP_TIMEOUT` (30
  segundos por defecto, 8 como mínimo). El envío es síncrono, así que si el servidor SMTP no
  responde, la pantalla de confirmación puede tardar hasta ese tiempo en aparecer; en un entorno
  real se haría en segundo plano con una cola.

#### Privacidad y buenas prácticas

* Nunca copies la contraseña en el repositorio, en `.env.example`, en la memoria del trabajo, en
  capturas ni en un chat (tampoco en el de una herramienta de IA). Si se te escapa en algún
  sitio, **revócala** en
  <https://myaccount.google.com/apppasswords> y crea otra.
* La base de datos de tu equipo guardará la dirección real que uses al comprar (pedido, cliente
  y buzón). Está en `storage/`, que no se versiona: no la incluyas en la entrega ni en capturas.
* En el repositorio y en las capturas todo sigue siendo ficticio (`.test`, NIF `B00000000`). La
  cuenta real que uses para enviar **no aparece en las facturas** (el contacto de la factura es
  `KN_COMPANY_EMAIL`, ficticio).
* El correo lleva un enlace «Ver factura» que apunta a `KN_APP_URL`: en local será
  `http://localhost:8000/…` y solo se abrirá en tu ordenador mientras el servidor esté en marcha.
  No importa para la demostración, porque **la factura va incrustada en el propio correo**.

#### Alcance de la comprobación

El cliente SMTP se ha probado contra servidores SMTP de prueba locales (STARTTLS, TLS implícito,
autenticación `PLAIN` y `LOGIN`, contraseña incorrecta, rechazo de destinatario, certificado no
fiable, servidor mudo o lento, mensajes grandes y puntos al inicio de línea). **No se ha podido
probar contra los servidores reales de Gmail**: si algo falla con tu cuenta, `php
bin/probar-correo.php` indica en qué paso y los mensajes de la tabla anterior cubren las causas
habituales.

---

## 8. Estructura del proyecto

```
kitsune-notes/
├── public/                 # Raíz web (único directorio expuesto)
│   ├── index.php           # Front controller
│   ├── .htaccess           # Reescritura de URL (Apache)
│   ├── assets/             # CSS, JS e ilustraciones SVG propias
│   └── uploads/productos/  # Imágenes subidas desde el back-office (no versionadas)
├── src/
│   ├── Core/               # Infraestructura: router, PDO, vistas, validación, sesión, traductor, actualización ligera del esquema
│   ├── Controller/         # Coordinación petición → servicios → vista
│   ├── Service/            # Lógica de negocio (carrito, precios, monedas, pedidos, pagos, catálogo, eventos, facturas, correos)
│   ├── Repository/         # Acceso a datos (una clase por agregado)
│   ├── Support/            # Utilidades (importes y formato de moneda, UUID, mensajes MIME, cliente SMTP)
│   ├── bootstrap.php       # Autocargador PSR-4 propio y configuración
│   └── routes.php          # Tabla de rutas
├── views/                  # Plantillas PHP (layouts, tienda, back-office, facturas y correos)
├── lang/en/                # Traducción al inglés de la interfaz: «texto en español» => «texto en inglés»
├── database/
│   ├── schema.sql          # Esquema SQLite
│   ├── schema.mysql.sql    # Esquema equivalente MySQL/MariaDB
│   ├── seed.php            # Datos de prueba
│   └── translations_en.php # Traducción al inglés del catálogo de prueba
├── storage/                # Base de datos, eventos y logs (no versionado)
├── bin/install.php         # Instalador
├── bin/probar-correo.php   # Comprueba el envío real de correos (sección 7)
├── iniciar-windows.bat     # Arranque con doble clic en Windows
├── tools/                  # Utilidades de desarrollo (ilustraciones, vista previa estática, comprobación de traducciones)
└── docs/capturas/          # Evidencias del recorrido completo
```

La separación es la habitual en tres capas: **presentación** (`views/`, `Controller/`),
**lógica de negocio** (`Service/`) y **persistencia** (`Repository/`, PDO). Los controladores
no escriben SQL y los repositorios no saben nada de HTTP. Por ejemplo, toda la gestión de
productos vive en `CatalogAdminService`; `AdminProductController` solo recoge la petición y
elige la vista.

---

## 9. Modelo de datos

**Datos maestros**: `categories`, `design_lines` (colecciones), `products`, `coupons`,
`customers`, `staff_users`.
**Datos transaccionales**: `orders`, `order_lines`, `payments`, `order_status_history`,
`support_tickets`, `invoices`, `mail_outbox`, `events`.

Decisiones relevantes:

* Los importes se guardan como **enteros en céntimos**, nunca en coma flotante.
* Los precios de catálogo son **PVP con IVA incluido** (venta B2C en España); la base imponible
  y la cuota se calculan por desglose y **se persisten en el pedido**, de modo que una factura
  antigua sigue siendo reproducible si cambia el tipo impositivo.
* `order_lines` guarda una **copia congelada** (SKU, nombre, precio, colección) del producto en
  el momento de la compra: editar o retirar un producto no altera pedidos históricos.
* Un producto con ventas **no se borra, se retira** (`is_active = 0`); `updated_at` registra la
  última modificación.
* De la tarjeta solo se almacenan **los cuatro últimos dígitos** y la marca. El PAN completo y
  el CVV no se guardan en ningún sitio.
* La tabla `events` es **de solo inserción** y **no tiene claves ajenas**: un evento es un
  hecho pasado que debe sobrevivir aunque se borre el dato maestro al que se refiere.
* `invoices` guarda cada factura con su número (`UNIQUE`), su pedido (`UNIQUE`: una factura por
  pedido), los importes y una **copia congelada en JSON** de lo que vio el cliente.
* `mail_outbox` guarda cada correo tal y como se «envió» (HTML, texto, destinatario, asunto), si
  el personal lo ha abierto y **el resultado de su entrega** (`delivery_status`: `solo_buzon`,
  `enviado` o `fallido`; `delivery_detail`: respuesta del servidor o motivo del fallo, sin
  credenciales; `delivered_at`). Como `events`, **no tiene claves ajenas**: es un registro de
  hechos pasados. Las bases creadas antes del envío real reciben esas tres columnas
  automáticamente al arrancar (`SchemaUpgrade`, idempotente).
* Cada colección guarda su personaje (`mascot`), su nombre original en japonés o coreano
  (`native_name`) y sus dos colores.
* **Idiomas**: el texto original de los datos maestros está en español. Las columnas terminadas
  en `_en` (`name_en`, `summary_en`, `description_en`, `specs_json_en`…) guardan su traducción
  y son opcionales: vacías, se muestra el español.
* **Monedas**: los precios del catálogo están siempre en euros. Cada pedido guarda la moneda
  en la que se hizo (`currency`), el idioma (`locale`), el tipo de cambio aplicado
  (`fx_rate_micros`, en millonésimas: 850000 = 0,85) y el contravalor de su total en euros
  (`total_base_cents`). Todos los importes del pedido, de sus líneas, de su pago y de su
  factura están en la moneda del pedido; el contravalor permite sumar pedidos de monedas
  distintas. Las bases de datos anteriores reciben estas columnas al arrancar (`SchemaUpgrade`),
  con el contravalor de los pedidos antiguos igual a su total y el catálogo de prueba traducido.

---

## 10. Instrumentación de eventos

### Eventos emitidos

| Evento | Cuándo |
|---|---|
| `product.viewed` | Visita a la ficha de un producto |
| `cart.item_added` | Alta de un producto en el carrito |
| `cart.item_removed` | Baja de un producto del carrito *(adicional)* |
| `checkout.started` | Entrada al checkout (una vez por composición de carrito) |
| `order.created` | Generación del pedido |
| `payment.simulated` | Resultado del cobro simulado (autorizado o rechazado) |
| `support.requested` | Consulta de soporte sin pedido asociado |
| `incident.created` | Incidencia sobre un pedido existente |
| `order.status_changed` | Cambio de estado del pedido *(adicional)* |
| `product.created` | Alta de producto desde el back-office *(adicional)* |
| `product.updated` | Modificación de producto, con los campos cambiados *(adicional)* |
| `product.archived` | Producto retirado del catálogo *(adicional)* |
| `product.restored` | Producto vuelto a publicar *(adicional)* |
| `product.deleted` | Borrado definitivo de un producto sin ventas *(adicional)* |
| `invoice.issued` | Emisión de la factura de un pedido pagado *(adicional)* |
| `invoice.viewed` | El cliente abre su factura, desde el pedido o desde el enlace del correo; una vez por sesión *(adicional)* |
| `email.sent` | Correo generado por la tienda: se guarda siempre en el buzón y, con el envío real activado, se entrega por SMTP. La carga útil indica el `canal` (`buzon_pruebas` o `smtp`) y la `entrega` (`solo_buzon`, `enviado` o `fallido`); no guarda la dirección del destinatario *(adicional)* |

### Formato

Cada evento tiene un sobre común y una carga útil propia:

```json
{
  "event_id": "7f317141-82e8-4fcd-b7f0-bf4e822f5d17",
  "event_name": "product.updated",
  "schema_version": "1.1",
  "source": "kitsune-notes.web",
  "occurred_at": "2026-09-22T12:38:06+02:00",
  "session_id": "db67d98fd186a0c7b17d59106e2e587e",
  "actor_type": "personal",
  "product_id": 13,
  "ip_hash": "99af3305cf8281b70f6b18250409393c",
  "data": {
    "sku": "KN-ORG-004",
    "nombre": "Pompón Tokki Mochi para estuche",
    "cambios": {
      "precio_cents": { "antes": 650, "despues": 590 },
      "stock": { "antes": 40, "despues": 55 }
    },
    "operador": "admin@kitsunenotes.test"
  }
}
```

La IP se guarda **seudonimizada** (SHA-256 con sal), nunca en claro.

**Versión 1.1 del esquema (idiomas y monedas).** Es compatible con la 1.0: no se quita ni se
renombra ningún campo, solo se añaden. Los eventos con importes llevan siempre `moneda`
(`EUR` o `GBP`) y los importes van en esa moneda; para poder sumar o comparar sin convertir,
llevan además su contravalor en euros:

| Evento | Campos añadidos |
|---|---|
| `product.viewed` | `moneda`, `precio_eur_cents`, `idioma` |
| `cart.item_added` | `moneda`, `precio_unitario_eur_cents`, `idioma` |
| `checkout.started` | `moneda`, `total_estimado_eur_cents`, `idioma` |
| `order.created` | `moneda` (antes siempre `EUR`), `idioma`, `tipo_cambio`, `total_eur_cents` y `precio_eur_cents` en cada línea |
| `payment.simulated` | `moneda` (antes siempre `EUR`), `importe_eur_cents` |
| `invoice.issued` | `total_eur_cents`, `idioma` |
| `email.sent`, `support.requested`, `incident.created` | `idioma` |

Quien analice los eventos (Tarea 2) puede trabajar solo con los campos `*_eur_cents` y usar
`moneda` e `idioma` como dimensiones.

### Dónde se almacenan y cómo consumirlos

1. **Base de datos** — tabla `events`, consultable desde `/admin/eventos`.
2. **Fichero JSON Lines** — `storage/events/events-AAAA-MM-DD.jsonl`, un evento por línea,
   pensado para que un proceso externo lo lea de forma incremental.
3. **API interno** — punto de integración previsto para la Tarea 2:

```bash
# Todos los eventos en JSON
curl -H "X-API-Token: token-demo-tarea2" \
     "http://localhost:8000/api/eventos?formato=json"

# Solo los pedidos creados, en CSV
curl -H "X-API-Token: token-demo-tarea2" \
     "http://localhost:8000/api/eventos?nombre=order.created&formato=csv"

# Catálogo como datos maestros
curl -H "X-API-Token: token-demo-tarea2" "http://localhost:8000/api/productos"

# Estado del servicio (sin token)
curl "http://localhost:8000/api/salud"
```

Filtros admitidos: `nombre`, `desde`, `hasta`, `sesion`, `limite`, `desplazamiento`, `formato`.

---

## 11. Despliegue en hosting

### Apache (hosting compartido con cPanel)

1. Subir el proyecto **fuera** de `public_html` y apuntar el *document root* del dominio a
   `public/`. Si el panel no permite cambiar el *document root*, subir el contenido de `public/`
   a `public_html/` y el resto del proyecto a una carpeta hermana, ajustando la ruta de
   `require` en `public/index.php`.
2. Crear el fichero `.env` en el servidor a partir de `.env.example` y cambiar
   `KN_APP_DEBUG=0`, `KN_ADMIN_PASSWORD`, `KN_EVENT_SALT`, `KN_API_TOKEN` y `KN_LINK_SECRET`
   (la clave que firma los enlaces a la factura). Si la tienda está detrás de un proxy o en un
   subdirectorio, define también `KN_APP_URL` (URL pública) para que los enlaces de los correos
   sean correctos. Si activas el envío real de correos (sección 7), escribe las variables
   `KN_SMTP_*` y `KN_MAIL_ALLOWED_TO` **solo** en este `.env` del servidor, nunca en el
   repositorio; en un hosting suele bastar con el servidor SMTP que ofrece el propio proveedor.
3. Dar permisos de escritura a `storage/` (base de datos, eventos y logs) y a
   `public/uploads/productos/` (imágenes subidas desde el back-office).
4. Ejecutar el instalador una vez, por SSH (`php bin/install.php`) o mediante el gestor de
   tareas del panel.
5. Comprobar que `mod_rewrite` está activo: el `.htaccess` incluido ya hace el resto.

La aplicación **también funciona en un subdirectorio** (por ejemplo `midominio.com/tienda`):
las URL se generan a partir de `SCRIPT_NAME`, no hay rutas absolutas escritas a mano.

### MySQL / MariaDB

En el `.env` del servidor:

```ini
KN_DB_DRIVER=mysql
KN_DB_HOST=localhost
KN_DB_NAME=nombre_de_la_base
KN_DB_USER=usuario
KN_DB_PASS=contraseña
```

El instalador usará automáticamente `database/schema.mysql.sql`. Todas las consultas usan
cada parámetro con nombre una sola vez, que es lo que exige MySQL con preparación nativa.

### Nginx

```nginx
root /ruta/al/proyecto/public;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ^~ /uploads/ {
    location ~ \.php$ { deny all; }
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

---

## 12. Seguridad, privacidad y accesibilidad

* Sentencias **preparadas** en todas las consultas (sin concatenar entradas en SQL).
* **Escape sistemático** de la salida HTML (`View::e`) para prevenir XSS.
* **Token CSRF** en todos los formularios que modifican estado.
* Contraseñas con `password_hash()` / `password_verify()` y regeneración del identificador de
  sesión al iniciar sesión.
* Cookies de sesión `HttpOnly` y `SameSite=Lax`; cabeceras `X-Content-Type-Options`,
  `X-Frame-Options` y `Referrer-Policy`.
* **Solo tres cookies, todas propias y técnicas**: `kitsune_session` (carrito, mensajes y token CSRF;
  hasta que se cierra el navegador), `kitsune_aviso_cookies` (recuerda que se cerró el aviso;
  180 días, `privacy.cookie_notice_days` en `config/config.php`) y `kitsune_idioma` (recuerda el
  idioma elegido con los botones ES/EN; solo se crea si el visitante cambia de idioma, dura 180
  días y es `HttpOnly`). Están listadas con su duración en `/aviso-academico#cookies`. Como no hay cookies de analítica, publicidad ni
  terceros que aceptar o rechazar, el banner de la portada es **informativo** («Entendido») y
  no un panel de consentimiento. Funciona también sin JavaScript: el botón envía un formulario
  (`POST /cookies/entendido`, con token CSRF) y es el servidor quien guarda la cookie; con
  JavaScript se guarda sin recargar. Si algún día se añadiera analítica o publicidad, habría que
  sustituirlo por un consentimiento real (aceptar o rechazar por categorías y sin cargar nada
  antes de aceptar).
* **Ninguna credencial en el repositorio**: todo lo sensible vive en `.env`, que está en
  `.gitignore`.
* Subida de imágenes validada por contenido, con tamaño y dimensiones máximas, nombre
  aleatorio y ejecución de scripts bloqueada en la carpeta de subidas.
* Datos de tarjeta: solo los cuatro últimos dígitos; el CVV no se guarda ni se registra.
* IP seudonimizada en los eventos.
* Facturas: solo se muestran con la sesión del comprador o con un enlace firmado (HMAC-SHA256);
  una firma incorrecta nunca revela datos del pedido.
* Correos: HTML en un `iframe` con `sandbox` y sin scripts, cabeceras del `.eml` saneadas y
  `email.sent` sin la dirección del destinatario.
* Envío real (opcional): desactivado por defecto, solo a direcciones autorizadas, TLS 1.2+ con
  verificación de certificado siempre activa, credenciales únicamente en el `.env` local (nunca en
  base de datos, eventos, logs ni mensajes de error) y un fallo de envío nunca rompe la compra
  (sección 7).
* Los precios **nunca** llegan desde el navegador: la sesión guarda únicamente identificadores
  de producto y cantidades, y los importes se recalculan en el servidor en cada petición.
  También el resumen del checkout: al cambiar el envío o el envoltorio, el navegador pide a
  `/checkout/resumen` el fragmento ya calculado y no suma nada por su cuenta.
* **Contraste AA** comprobado para todos los pares de color de texto de la paleta «fresa y
  nata» (el rosa de los botones se oscureció hasta `#D6336C` para que el texto blanco llegue a
  4,6:1), foco visible, enlace para saltar al contenido, etiquetas en todos los campos y
  respeto de `prefers-reduced-motion`.

---

## 13. Limitaciones conocidas

1. **El pago es una simulación.** No hay integración con ninguna pasarela real ni gestión de
   reembolsos, capturas parciales o 3-D Secure.
2. **No hay cuentas de cliente.** La compra es como invitado; no existe registro, recuperación
   de contraseña ni histórico de pedidos por usuario (la consulta se hace con referencia +
   correo).
3. **El carrito vive en la sesión**, no en base de datos: no se recupera desde otro dispositivo.
4. **El stock se descuenta al crear el pedido** y no se repone al cancelarlo desde el
   back-office (sí cuando la propia tienda sustituye un pedido sin pagar porque el cliente ha
   cambiado el carrito o la moneda). Tampoco hay reservas temporales ni control de concurrencia fino.
5. **Un solo rol en el back-office.** Cualquier usuario interno puede editar el catálogo; no
   hay permisos por rol, flujo de aprobación ni versiones anteriores de un producto (solo el
   rastro de cambios de los eventos `product.updated`).
6. **Las imágenes subidas no se redimensionan** ni se optimizan; tampoco hay importación masiva
   de productos (CSV) ni gestión de categorías o colecciones desde el panel.
7. **El correo real es opcional y está acotado.** Por defecto cada mensaje solo se guarda en el
   buzón de pruebas (`/admin/correos`) y puede descargarse como `.eml`. El envío por SMTP
   (sección 7) se activa a mano en el `.env`, solo escribe a direcciones autorizadas y no está
   pensado para clientes reales: es síncrono (la pantalla de confirmación espera al servidor
   SMTP, hasta 30 s por defecto), no hay cola ni reintentos automáticos (el reintento es manual,
   desde el pedido), no gestiona rebotes ni bajas, solo autentica con usuario y contraseña (no
   OAuth 2.0) y se ha probado contra servidores de prueba locales, no contra Gmail real.
8. **El buscador es un `LIKE` simple**, sin tolerancia a errores tipográficos ni relevancia.
9. **El API interno usa un token estático.** En producción debería sustituirse por OAuth 2.0 o
   claves rotativas por consumidor, con limitación de tasa.
10. **Sin pruebas automatizadas.** La verificación ha sido manual y mediante un recorrido
    automatizado del flujo de compra y del back-office.
11. **Dos idiomas y dos monedas, pero un solo país.** La tienda vende en euros y en libras,
    pero el envío sigue siendo solo a España peninsular (código postal de 5 dígitos, provincia)
    y se aplica siempre el IVA español del 21 %, también a los pedidos en libras. El tipo de
    cambio es **fijo y de demostración** (`KN_FX_EUR_GBP`): no se consulta ninguna fuente
    oficial ni se actualiza solo. Las direcciones web no se traducen (`/carrito`, `/pedido/…`),
    el back-office y el API están solo en español, y añadir un tercer idioma exige un catálogo
    nuevo en `lang/` y columnas de traducción nuevas en la base de datos (sección 17).
12. **SQLite no está pensado para alta concurrencia.** Para tráfico real habría que pasar a
    MySQL/MariaDB o PostgreSQL, previsto en la capa PDO pero no probado en carga.
13. **Las facturas son de prueba.** Emisor y NIF ficticios, sin validez fiscal ni requisitos
    legales de facturación. Cancelar un pedido facturado no genera factura rectificativa: la
    factura emitida es inmutable.
14. **El PDF se obtiene imprimiendo desde el navegador** (la página ya está maquetada para una
    hoja A4). No hay generación de PDF en el servidor ni factura adjunta en el correo: la factura
    va incrustada en el cuerpo del mensaje, incluso en el correo real.
15. **El aviso de cookies es informativo y solo sale en la portada.** Es válido mientras la
    tienda use únicamente cookies técnicas y no sustituye una revisión legal. Para mostrarlo en
    todas las páginas basta con pasar `cookieNotice` desde cada controlador (o compartirlo con
    `View::share`), porque lo dibuja la plantilla general (`views/layout/main.php`).

---

## 14. Utilidades de desarrollo

Las ilustraciones de producto, los cuatro personajes y el logotipo son vectores propios
generados por script; no se usa ninguna fotografía, logotipo ni personaje de terceros:

```bash
python3 tools/generar_ilustraciones.py
```

Para generar una copia estática navegable de la tienda (útil para enseñarla sin servidor PHP),
con la aplicación en marcha:

```bash
python3 tools/exportar_vista_previa.py http://localhost:8000 ./vista-previa
```

La copia estática incluye también una factura y el buzón de correos con el correo de
confirmación del pedido de ejemplo. Se genera siempre con el envío real desactivado, así que no
envía ni muestra ningún correo real.

Para comprobar el envío real de correos sin hacer una compra (sección 7):

```bash
php bin/probar-correo.php tu.direccion@ejemplo.com
```

Para comprobar que no falta ninguna traducción después de tocar un texto (sección 17):

```bash
php tools/comprobar_traducciones.php
```

---

## 15. Equipo y reparto de responsabilidades

> Completar antes de la entrega. El historial de commits debe reflejar la participación de
> todos los miembros del grupo.

| Integrante | Responsabilidad principal |
|---|---|
| | Modelo de datos y persistencia |
| | Catálogo, colecciones y ficha de producto |
| | Carrito, checkout y motor de precios |
| | Pago simulado y ciclo de vida del pedido |
| | Back-office, gestión de productos, facturas y correos, instrumentación de eventos y despliegue |

## 16. Uso de IA generativa

El uso de herramientas de IA generativa en este proyecto está declarado en el **anexo
correspondiente de la memoria**, con el detalle de tareas asistidas, errores detectados,
cambios realizados por el grupo y forma de validación, según exige el enunciado de la tarea.

---

## 17. Idiomas y monedas (español / inglés, euros / libras)

### Qué ve el visitante

En la franja superior de la tienda hay dos botones pequeños, **ES** y **EN**. Al pulsar uno, la
página en la que se está se recarga en ese idioma y la elección se recuerda 180 días en una
cookie técnica (`kitsune_idioma`).

| | Español (por defecto) | English |
|---|---|---|
| Textos | Los originales, sin cambios | Traducidos: interfaz, catálogo, mensajes y errores de formulario |
| Moneda de compra | Euro | Libra esterlina |
| Formato del importe | `1.234,56 €` (símbolo detrás) | `£1,234.56` (símbolo delante) |
| Fechas | `06/10/2026 18:12` | `6 Oct 2026, 18:12` |
| Correos y factura | En español y en euros | En inglés y en libras |

Dos reglas que conviene tener claras para la defensa:

* **El símbolo depende de la moneda y los separadores del idioma.** El euro va siempre detrás
  del importe y la libra siempre delante, se mire en el idioma que se mire.
* **Un pedido se muestra siempre en SU moneda y su factura en SU idioma.** Un pedido hecho en
  inglés sigue en libras aunque después se consulte en español (`£31,33`), y su factura sigue
  en inglés. Por eso las plantillas de pedidos y facturas llaman a
  `$this->money($importe, $pedido['currency'])` y no a `$this->money($importe)`.

El **back-office y el API están solo en español**: son herramientas del equipo. En el panel,
cada pedido aparece en su moneda y el importe acumulado suma el contravalor en euros.

### Cómo se traduce la interfaz

El español es el idioma original y **el propio texto en español es la clave de traducción**
(como en *gettext*). Las plantillas y los controladores siguen escribiendo el texto en español,
envuelto en un ayudante; el inglés se busca en `lang/en/*.php`, que son listas
`'texto en español' => 'texto en inglés'`. En español el ayudante devuelve el texto tal cual,
así que la versión original no puede romperse por una traducción que falte.

```php
<?= $this->t('Añadir al carrito') ?>                                  // texto o atributo: traducido y escapado
<?= $this->t('Pedido {referencia}', ['referencia' => $ref]) ?>        // con valores variables
<?= $this->th('Es un <strong>prototipo académico</strong>.') ?>       // frase con HTML propio
<?= $this->tn('{n} artículo', '{n} artículos', $unidades) ?>          // singular y plural
<?= $this->money($centimos) ?>                                        // en la moneda de la tienda
<?= $this->money($centimos, $order['currency']) ?>                    // en la moneda de un pedido
```

En los controladores, `$this->t('…')` (títulos y mensajes) y `$this->validate(…)` (formularios
con los errores en el idioma activo). Para **cambiar o añadir un texto**:

1. Escríbelo en español en la plantilla, dentro de `t()`: una frase entera por clave, nunca
   trozos concatenados, y los datos variables como `{marcadores}`.
2. Añade su traducción al fichero de `lang/en/` que corresponda (`comun.php`, `tienda.php`,
   `compra.php` o `documentos.php`).
3. Ejecuta `php tools/comprobar_traducciones.php`: avisa de los textos sin traducir, de los
   marcadores que no coinciden y de las traducciones contradictorias. Con `KN_APP_DEBUG=1`,
   además, cualquier texto mostrado sin traducir queda anotado en `storage/logs/php-error.log`.

### Cómo se traduce el catálogo

Los nombres y descripciones de productos, categorías y colecciones están en la base de datos:
el original en español y la traducción en columnas `_en`. `CatalogLocalizer` sustituye unos por
otros al leer, de modo que el resto de la aplicación no sabe en qué idioma se está comprando.

* El catálogo de prueba se traduce con `database/translations_en.php`, que el instalador carga
  solo. `php bin/install.php --traducciones` lo vuelve a aplicar sobre una base ya instalada
  (solo rellena campos vacíos: nunca pisa una traducción corregida a mano).
* Los productos nuevos se traducen desde su ficha del back-office («Versión en inglés»). Si se
  deja vacía, en inglés se ven en español y el listado los marca como «sin traducir».
* El buscador busca en los dos idiomas.

### Monedas y tipo de cambio

* Los precios del catálogo, los gastos de envío, el envoltorio y los cupones se definen **solo
  en euros**. En inglés se convierten a libras con un tipo de cambio **fijo de demostración**
  que se cambia en el `.env`: `KN_FX_EUR_GBP=0.85` (libras por euro). No es un tipo oficial.
* Se convierte el **precio unitario** (redondeado al penique) y a partir de ahí **todo se
  calcula ya en libras**: líneas, descuento, envío, envoltorio, IVA y total. Así el desglose
  suma exactamente el total, sin diferencias de céntimos por redondeos.
* El pedido guarda su moneda, su idioma, el tipo de cambio aplicado y el contravalor de su
  total en euros. Cambiar después `KN_FX_EUR_GBP` no altera los pedidos ya hechos.
* La **factura** se expide en la moneda y el idioma del pedido. Si no está en euros, indica el
  tipo de cambio y el contravalor en euros de la cuota de IVA y del total: el Reglamento de
  facturación (art. 12 del RD 1619/2012) permite facturar en cualquier moneda y lengua siempre
  que la cuota del impuesto figure en euros. En un sistema real el tipo no sería fijo, sino el
  que marca la Ley del IVA (art. 79.Once).
* La numeración de pedidos y facturas es única: no hay series por idioma ni por moneda.
* Si el cliente cambia de idioma **a mitad de compra**, los precios del carrito pasan a la otra
  moneda. Si ya había un pedido sin pagar (un pago rechazado), ese pedido se cancela y se
  genera otro con el importe que se ve en pantalla: nunca se cobra un importe distinto.

### Correos

Cada correo se escribe en el idioma del pedido (o de la solicitud de soporte), no en el de quien
provoca el envío: si el personal marca como enviado, desde el back-office en español, un pedido
hecho en inglés, el aviso sale en inglés y en libras. Sus enlaces llevan `?idioma=en` para que
la tienda se abra en inglés.

### Dónde está cada cosa

| Fichero | Qué hace |
|---|---|
| `config/config.php` (`i18n`, `commerce.currencies`) | Idiomas disponibles, moneda de cada uno, cookie y tipo de cambio |
| `src/Core/Translator.php` | Traductor: idioma activo, catálogos de `lang/`, marcadores y plurales |
| `src/Core/App.php` (`bootLocale`) | Decide el idioma de cada petición y atiende los botones ES/EN |
| `src/Core/View.php` | Ayudantes de plantilla: `t`, `th`, `tn`, `money`, `date`, `inLocale` |
| `src/Support/Money.php` | Formato de cada moneda y conversión con enteros (sin coma flotante) |
| `src/Service/CurrencyService.php` | Moneda activa, tipo de cambio y contravalor en euros |
| `src/Service/CatalogLocalizer.php` | Pone cada producto en el idioma y la moneda del visitante |
| `src/Service/PricingService.php` | Calcula el carrito entero en la moneda de la compra |
| `src/Core/SchemaUpgrade.php` | Añade las columnas nuevas a una base de datos anterior |
| `lang/en/*.php`, `database/translations_en.php` | Las traducciones |
| `tools/comprobar_traducciones.php` | Comprueba que no falta ninguna |

### Añadir otro idioma

1. Declararlo en `config/config.php` (`i18n.locales`) con su etiqueta, su código HTML y su moneda;
   si la moneda es nueva, añadirla a `commerce.currencies` y a `Money::CURRENCIES`.
2. Crear `lang/<código>/` con los mismos ficheros que `lang/en/`.
3. Añadir las columnas `_<código>` a `schema.sql`, `schema.mysql.sql` y `SchemaUpgrade`, y su
   fichero `database/translations_<código>.php`.
4. Ajustar los formatos de fecha y de número del idioma en `View::date()` y `Money::SEPARATORS`.
