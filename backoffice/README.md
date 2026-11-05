# Kitsune Gestión · back-office de Kitsune Notes (Tarea 2)

> Prototipo académico (UCAM). Datos ficticios, sin actividad comercial real.

Kitsune Gestión es el sistema interno de operaciones de la tienda Kitsune Notes. Es un **sistema
distinto de la tienda**: código, base de datos (SQLite propia), usuarios y API propios. No lee nunca
la base de datos de la tienda; todo le llega por la integración con n8n (ver `../integracion`).

## Qué hace

| Pantalla | Para qué |
|---|---|
| **Cuadro de mando** (`/`) | Indicadores y gráficos calculados en el momento a partir de los registros del sistema |
| **Pedidos** (`/pedidos`) | Pedidos llegados de la tienda: cliente, líneas, pagos, historial. El personal cambia el estado (preparación, envío con seguimiento, entrega) y el cambio se sincroniza con la tienda |
| **Incidencias** (`/incidencias`) | Consultas e incidencias de postventa asociadas al cliente y al pedido, con su conversación (web, Telegram, equipo, agente) |
| **Propuestas del agente** (`/propuestas`) | Lo que propone el agente de IA. Una persona lo aprueba (puede corregir el texto) o lo rechaza con un motivo |
| **Integración** (`/integracion`) | Trazabilidad: bandeja de salida hacia n8n (pendientes, conflictos, reintentos), registro de integración y eventos recibidos |

## Instalación en local

Requisitos: PHP 8.1 o superior con `pdo_sqlite` y `curl` (el mismo PHP de la tienda).

```bash
cd backoffice
php bin/instalar.php                 # crea .env con claves aleatorias y la base de datos
php -S localhost:8100 -t public      # http://localhost:8100
```

Usuario de prueba: `gestion@kitsunenotes.test` / `gestion-demo-2026` (variable `KG_ADMIN_PASSWORD`;
cámbiala en el servidor). La base de datos empieza vacía a propósito: los datos llegan por la integración.
`php bin/instalar.php --fresh` la vacía.

## Configuración (`.env`)

| Variable | Para qué |
|---|---|
| `KG_APP_URL` | Dirección pública (se usa en los enlaces que n8n envía al equipo) |
| `KG_APP_DEBUG` | `1` en local, `0` en el servidor |
| `KG_DB_PATH` | Ruta de la base de datos (por defecto `storage/gestion.sqlite`) |
| `KG_ADMIN_EMAIL`, `KG_ADMIN_PASSWORD` | Usuario del equipo que se crea la primera vez |
| `KG_CLAVE_N8N` | Clave de la API para los flujos de n8n (permisos de integración) |
| `KG_CLAVE_AGENTE` | Clave de la API para las herramientas del agente (solo consultar y clasificar) |
| `KG_N8N_WEBHOOK_URL` | Webhook del flujo «03 · Eventos del back-office» |
| `KG_N8N_SECRETO` | Secreto compartido con n8n para firmar los cambios (HMAC-SHA256) |
| `KG_N8N_TIMEOUT` | Segundos de espera por n8n (20 por defecto) |

## API REST (`/api/v1`)

Autenticación con la cabecera `X-Api-Key`. Respuestas JSON con `ok`; los errores llevan `error`,
`mensaje` y, si son de validación, `detalles` con el campo exacto. Cada llamada queda en el registro de
integración (operación, resultado, código HTTP, duración, ejecución de n8n y evento de origen).

| Método y ruta | Permiso | Qué hace |
|---|---|---|
| `GET /salud` | — | Estado del servicio (sirve para mantenerlo despierto) |
| `PUT /pedidos/{referencia}` | `pedidos:escribir` | Alta idempotente de un pedido de la tienda (escenario 1) |
| `GET /pedidos/{referencia}` | `pedidos:leer` | Ficha compacta, sin datos de contacto (herramienta del agente) |
| `POST /pedidos/{referencia}/pagos` | `pedidos:escribir` | Resultado de un pago simulado (idempotente por evento) |
| `POST /pedidos/{referencia}/estado` | `pedidos:escribir` | Refleja un cambio de estado hecho en la tienda |
| `GET /clientes/{id}/historial` | `clientes:leer` | Pedidos e incidencias anteriores (herramienta del agente) |
| `POST /incidencias` | `incidencias:escribir` | Alta idempotente de una incidencia (escenario 3) |
| `GET /incidencias/{id}` | `incidencias:leer` | Incidencia con su conversación |
| `POST /incidencias/{id}/clasificacion` | `incidencias:clasificar` | Categoría y prioridad (acción reversible del agente) |
| `POST /propuestas` | `propuestas:escribir` | Guarda la salida del agente como propuesta pendiente de revisión |
| `POST /eventos` | `eventos:escribir` | Copia mínima de un evento de la tienda (embudo; idempotente) |
| `POST /registro` | `registro:escribir` | n8n anota el resultado de cada ejecución |
| `POST /telegram/vinculos` | `telegram:escribir` | Vincula el chat de Telegram con el cliente del pedido |
| `POST /telegram/mensajes` | `telegram:escribir` | Añade a su incidencia un mensaje del cliente por Telegram |
| `GET /telegram/{chat_id}/pedidos` | `pedidos:leer` | Pedidos del cliente (comando `/estado` del bot) |
| `GET /metricas` | `metricas:leer` | Los datos del cuadro de mando en JSON |

Permisos de cada clave (mínimo privilegio):

* **Integración** (`KG_CLAVE_N8N`): todos los anteriores.
* **Agente** (`KG_CLAVE_AGENTE`): `pedidos:leer`, `clientes:leer`, `incidencias:leer` e
  `incidencias:clasificar`. No puede crear propuestas, cambiar pedidos ni escribir al cliente.

## Sincronización con la tienda (bandeja de salida)

Los cambios hechos aquí que deben llegar a la tienda o al cliente se guardan en la tabla
`sincronizaciones` y se envían a n8n firmados (`X-Kitsune-Firma`, `X-Kitsune-Timestamp`):

| Estado | Significado |
|---|---|
| `confirmada` | n8n lo aplicó y respondió 2xx |
| `pendiente` | n8n o la tienda no respondieron: se reintenta a los 1, 2, 5, 10, 30, 60, 120 y 240 minutos (al cargar cualquier página, con el botón «Reintentar pendientes ahora» o con `bin/reintentar-sincronizaciones.php`) |
| `conflicto` | La tienda lo rechazó por una regla de negocio (409/422): lo revisa una persona |
| `fallida` | Se agotaron los reintentos |

## Revisión humana de las propuestas del agente

1. El agente (flujo 02 de n8n) analiza la incidencia, consulta datos y la clasifica.
2. Su salida estructurada llega como propuesta **pendiente de revisión**. Nada se ha enviado al cliente.
3. Una persona del equipo la **aprueba** (puede corregir el mensaje) o la **rechaza** (motivo obligatorio).
   Queda registrado quién, cuándo, el comentario y el texto final.
4. Al aprobarla, n8n la envía por Telegram si el cliente lo tiene vinculado; si no, se ejecuta en modo
   simulado. El resultado vuelve a la propuesta (`ejecutada`, `ejecutada_simulada` o `fallo_ejecucion`).

## Métricas del cuadro de mando

Todas salen de tablas del propio sistema; ninguna se escribe a mano.

| Indicador | Cálculo | Origen |
|---|---|---|
| Pedidos recibidos | Número de pedidos; serie diaria de 14 días (gráfico de barras) | `pedidos` |
| Importe simulado y ticket medio | Suma y media del contravalor en euros de los pedidos con pago autorizado | `pedidos` |
| Pedidos por estado | Recuento por estado | `pedidos` |
| Productos más pedidos | Unidades por SKU, sin cancelados | `lineas_pedido` |
| Incidencias abiertas y resolución media | Abiertas; media de horas entre alta y resolución | `incidencias` |
| Éxito de la integración y errores por tipo | Ejecuciones de n8n correctas frente a fallidas; errores agrupados por tipo | `registro_integracion` |
| Conversión del checkout | Pedidos con pago autorizado entre sesiones que empiezan el pago | `eventos_recibidos` |
| Propuestas del agente | Por estado; tasa de aprobación; aprobadas con el texto corregido | `propuestas` |
| Sincronización | Confirmadas, pendientes, en conflicto y fallidas; latencia media de la API | `sincronizaciones`, `registro_integracion` |

## Estructura

```text
backoffice/
├── public/            raíz web: index.php, .htaccess, assets/gestion.css
├── src/
│   ├── Core/          App, Env, Database, Request, Response, Router, Session, View
│   ├── Api/           ApiController (claves y registro), PedidosApi, IncidenciasApi, IntegracionApi
│   ├── Web/           pantallas: Auth, Panel, Pedidos, Incidencias, Propuestas, Integracion
│   ├── Service/       Pedidos, Incidencias, Propuestas, Sincronizador, RegistroIntegracion, Metricas
│   ├── Support/       Validador, Graficos (SVG)
│   └── routes.php
├── views/             plantillas PHP
├── database/schema.sql
├── bin/               instalar.php, reintentar-sincronizaciones.php
└── storage/           base de datos (no se versiona)
```

## Seguridad

Sentencias preparadas, escape de toda la salida, CSRF en todos los formularios, `password_hash`,
sesión `HttpOnly` y `SameSite=Lax`, cabeceras de seguridad con una CSP estricta (sin scripts ni estilos
en línea), claves comparadas en tiempo constante y secretos solo en el `.env`.

## Limitaciones

* Un solo rol de usuario (operador). En un sistema real habría roles y aprobación a cuatro ojos para
  acciones sensibles.
* SQLite: suficiente para el prototipo, no para mucha concurrencia.
* Las claves de la API son fijas en el `.env`; en producción serían por consumidor, guardadas cifradas
  y rotadas.
