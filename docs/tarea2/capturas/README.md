# Capturas de evidencia · Tarea 2

Tomadas en el entorno local de pruebas (tienda, back-office y n8n 2.46.6 en el mismo ordenador),
con el modelo de IA y la API de Telegram **simulados** (`integracion/simuladores`). Sirven para ver
cómo es cada pantalla; las evidencias de la entrega deben salir del entorno desplegado (n8n en
Render, Telegram real y modelo de Groq).

| Captura | Qué muestra |
|---|---|
| `bo-01-acceso.jpg` | Acceso del equipo al back-office (usuarios propios, distintos de la tienda) |
| `bo-02-antes-cuadro-de-mando-vacio.jpg` | **Antes de la integración:** cuadro de mando sin datos |
| `bo-03-antes-sin-pedidos.jpg` | **Antes de la integración:** ningún pedido |
| `bo-04-cuadro-de-mando.jpg` | **Después:** indicadores y gráficos calculados con los datos del sistema |
| `bo-05-pedidos.jpg` | Pedidos llegados de la tienda, con estado, pago y sincronización |
| `bo-06-pedido-recibido.jpg` | Ficha de un pedido: líneas, pagos, historial y cambio de estado sincronizado |
| `bo-07-incidencias.jpg` | Incidencias con categoría y prioridad puestas por el agente |
| `bo-08-incidencia-con-propuesta.jpg` | Conversación de una incidencia (web, Telegram, agente, equipo) y propuestas revisadas |
| `bo-09-propuestas.jpg` | Propuestas del agente: ejecutada, fallo al ejecutar y error del agente |
| `bo-10-integracion.jpg` | Bandeja de salida hacia n8n (confirmadas y en conflicto) y registro de integración |
| `bo-11-registro-errores.jpg` | Registro filtrado por errores, con su tipo |
| `tienda-01-bandeja-de-salida.jpg` | Tienda: eventos enviados a n8n, entregados y rechazados |
| `n8n-01-entrada-eventos-tienda.jpg` | Flujo 01 (rama correcta, ramas de error y respuesta) |
| `n8n-02-agente-incidencias.jpg` | Flujo 02: agente con modelo, tres herramientas y salida estructurada |
| `n8n-03-eventos-backoffice.jpg` | Flujo 03: sincronización con la tienda y envío de lo aprobado |
| `n8n-04-telegram-clientes.jpg` | Flujo 04: bot de Telegram |
| `n8n-00-errores.jpg` | Flujo 00: errores comunes |
| `n8n-05-historial-de-ejecuciones.jpg` | Historial de ejecuciones (trazabilidad) |
