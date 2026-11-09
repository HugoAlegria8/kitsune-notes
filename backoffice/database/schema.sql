-- Kitsune Gestión · esquema de la base de datos propia del back-office (SQLite).
-- Es independiente de la base de datos de la tienda: los datos llegan por la
-- integración (n8n) y nunca se leen directamente de la tienda.
-- Fechas en ISO-8601 con zona (Europe/Madrid); importes en céntimos enteros.

CREATE TABLE IF NOT EXISTS usuarios (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    email         TEXT    NOT NULL UNIQUE,
    nombre        TEXT    NOT NULL,
    password_hash TEXT    NOT NULL,
    rol           TEXT    NOT NULL DEFAULT 'operador',
    creado_en     TEXT    NOT NULL
);

-- Clientes tal y como los conoce el back-office (CRM). Solo los datos necesarios.
CREATE TABLE IF NOT EXISTS clientes (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    tienda_cliente_id     INTEGER UNIQUE,
    nombre                TEXT    NOT NULL DEFAULT '',
    email                 TEXT    NOT NULL DEFAULT '',
    telegram_chat_id      TEXT,
    telegram_vinculado_en TEXT,
    creado_en             TEXT    NOT NULL,
    actualizado_en        TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_clientes_email ON clientes (email);
CREATE INDEX IF NOT EXISTS idx_clientes_telegram ON clientes (telegram_chat_id);

CREATE TABLE IF NOT EXISTS pedidos (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    referencia       TEXT    NOT NULL UNIQUE,
    cliente_id       INTEGER REFERENCES clientes (id),
    estado           TEXT    NOT NULL,
    estado_pago      TEXT    NOT NULL DEFAULT 'pendiente',
    moneda           TEXT    NOT NULL DEFAULT 'EUR',
    total_cents      INTEGER NOT NULL DEFAULT 0,
    total_eur_cents  INTEGER NOT NULL DEFAULT 0,
    unidades         INTEGER NOT NULL DEFAULT 0,
    metodo_envio     TEXT,
    provincia_envio  TEXT,
    idioma           TEXT    NOT NULL DEFAULT 'es',
    cupon            TEXT,
    transportista    TEXT,
    seguimiento      TEXT,
    creado_en_tienda TEXT    NOT NULL,
    recibido_en      TEXT    NOT NULL,
    actualizado_en   TEXT    NOT NULL,
    evento_origen    TEXT
);
CREATE INDEX IF NOT EXISTS idx_pedidos_estado ON pedidos (estado);

CREATE TABLE IF NOT EXISTS lineas_pedido (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    pedido_id        INTEGER NOT NULL REFERENCES pedidos (id) ON DELETE CASCADE,
    sku              TEXT    NOT NULL,
    nombre           TEXT    NOT NULL,
    cantidad         INTEGER NOT NULL,
    precio_cents     INTEGER NOT NULL,
    precio_eur_cents INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS pagos (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    pedido_id       INTEGER NOT NULL REFERENCES pedidos (id) ON DELETE CASCADE,
    evento_origen   TEXT    NOT NULL UNIQUE,
    resultado       TEXT    NOT NULL,
    importe_cents   INTEGER NOT NULL,
    moneda          TEXT    NOT NULL,
    referencia_pago TEXT,
    motivo_rechazo  TEXT,
    ocurrido_en     TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS historial_pedido (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    pedido_id       INTEGER NOT NULL REFERENCES pedidos (id) ON DELETE CASCADE,
    estado_anterior TEXT,
    estado_nuevo    TEXT    NOT NULL,
    origen          TEXT    NOT NULL,
    actor           TEXT    NOT NULL,
    nota            TEXT,
    creado_en       TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS incidencias (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    referencia_ticket TEXT    UNIQUE,
    pedido_id         INTEGER REFERENCES pedidos (id),
    cliente_id        INTEGER REFERENCES clientes (id),
    tipo              TEXT    NOT NULL,
    asunto            TEXT    NOT NULL DEFAULT '',
    estado            TEXT    NOT NULL DEFAULT 'abierta',
    categoria         TEXT,
    prioridad         TEXT,
    canal             TEXT    NOT NULL,
    idioma            TEXT    NOT NULL DEFAULT 'es',
    creada_en         TEXT    NOT NULL,
    resuelta_en       TEXT,
    actualizada_en    TEXT    NOT NULL,
    evento_origen     TEXT
);
CREATE INDEX IF NOT EXISTS idx_incidencias_estado ON incidencias (estado);

CREATE TABLE IF NOT EXISTS mensajes_incidencia (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    incidencia_id INTEGER NOT NULL REFERENCES incidencias (id) ON DELETE CASCADE,
    autor         TEXT    NOT NULL, -- cliente | equipo | agente | sistema
    canal         TEXT    NOT NULL, -- formulario_web | telegram | interno | simulado
    texto         TEXT    NOT NULL,
    creado_en     TEXT    NOT NULL
);

-- Propuestas del agente de IA: su salida estructurada y la revisión humana.
CREATE TABLE IF NOT EXISTS propuestas (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    incidencia_id       INTEGER NOT NULL REFERENCES incidencias (id) ON DELETE CASCADE,
    tipo_caso           TEXT    NOT NULL DEFAULT '',
    datos_consultados   TEXT    NOT NULL DEFAULT '[]',
    accion_propuesta    TEXT    NOT NULL DEFAULT '',
    mensaje_cliente     TEXT    NOT NULL DEFAULT '',
    justificacion       TEXT    NOT NULL DEFAULT '',
    confianza           REAL,
    prioridad           TEXT,
    riesgos             TEXT    NOT NULL DEFAULT '[]',
    estado              TEXT    NOT NULL,
    salida_agente       TEXT    NOT NULL DEFAULT '{}',
    modelo              TEXT,
    ejecucion_n8n       TEXT,
    revisado_por        TEXT,
    revisado_en         TEXT,
    comentario_revision TEXT,
    mensaje_final       TEXT,
    resultado           TEXT,
    ejecutada_en        TEXT,
    creada_en           TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_propuestas_estado ON propuestas (estado);

-- Bandeja de salida hacia n8n: cambios hechos aquí que deben llegar a la tienda
-- o al cliente. Si n8n o la tienda no responden, quedan pendientes y se reintentan.
CREATE TABLE IF NOT EXISTS sincronizaciones (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    tipo              TEXT    NOT NULL,
    entidad           TEXT    NOT NULL,
    carga             TEXT    NOT NULL,
    estado            TEXT    NOT NULL DEFAULT 'pendiente', -- pendiente | confirmada | conflicto | fallida
    intentos          INTEGER NOT NULL DEFAULT 0,
    ultimo_error      TEXT,
    respuesta         TEXT,
    siguiente_intento TEXT,
    creada_en         TEXT    NOT NULL,
    actualizada_en    TEXT    NOT NULL,
    confirmada_en     TEXT
);
CREATE INDEX IF NOT EXISTS idx_sincronizaciones_estado ON sincronizaciones (estado);

-- Registro de integración: cada llamada a la API y cada resultado que anota n8n.
CREATE TABLE IF NOT EXISTS registro_integracion (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    creado_en   TEXT    NOT NULL,
    origen      TEXT    NOT NULL, -- api | n8n | backoffice
    flujo       TEXT,
    ejecucion   TEXT,
    operacion   TEXT    NOT NULL,
    event_id    TEXT,
    entidad     TEXT,
    resultado   TEXT    NOT NULL, -- ok | duplicado | ignorado | error
    tipo_error  TEXT,
    codigo_http INTEGER,
    mensaje     TEXT,
    duracion_ms INTEGER
);
CREATE INDEX IF NOT EXISTS idx_registro_resultado ON registro_integracion (resultado);

-- Eventos de la tienda recibidos por la integración (para el embudo y la deduplicación).
CREATE TABLE IF NOT EXISTS eventos_recibidos (
    event_id          TEXT PRIMARY KEY,
    event_name        TEXT NOT NULL,
    occurred_at       TEXT NOT NULL,
    session_id        TEXT,
    pedido_ref        TEXT,
    importe_eur_cents INTEGER,
    resultado         TEXT,
    recibido_en       TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_eventos_nombre ON eventos_recibidos (event_name);
