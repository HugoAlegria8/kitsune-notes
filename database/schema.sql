-- =====================================================================
--  Kitsune Notes - Esquema de base de datos (SQLite)
--  Prototipo académico - Tarea 1 SIE (UCAM)
--
--  Convenciones:
--   * Todos los importes monetarios se almacenan en CÉNTIMOS (INTEGER)
--     para evitar errores de redondeo con coma flotante.
--   * Los precios de producto son PVP con IVA incluido (B2C España).
--     La base imponible y la cuota de IVA se calculan y se persisten
--     en el pedido como datos transaccionales.
--   * Las fechas se almacenan en formato ISO-8601 (texto) en zona
--     Europe/Madrid, con desplazamiento explícito.
--   * Idiomas: el texto original de los datos maestros está en español.
--     Las columnas terminadas en «_en» guardan su traducción al inglés y
--     son opcionales: vacías, la tienda muestra el texto en español.
--   * Monedas: los precios del catálogo están en EUROS (moneda base). Un
--     pedido puede hacerse en otra moneda; entonces todos sus importes van
--     en esa moneda y guarda el tipo de cambio aplicado y el contravalor
--     del total en euros.
-- =====================================================================

PRAGMA foreign_keys = ON;

-- ---------------------------------------------------------------------
-- DATOS MAESTROS
-- ---------------------------------------------------------------------

-- Categoría funcional del producto (qué es)
CREATE TABLE categories (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    slug        TEXT    NOT NULL UNIQUE,
    name        TEXT    NOT NULL,
    tagline     TEXT    NOT NULL DEFAULT '',
    description TEXT    NOT NULL DEFAULT '',
    icon        TEXT    NOT NULL DEFAULT '',
    sort_order  INTEGER NOT NULL DEFAULT 0,
    -- Traducción al inglés (vacío = se muestra el texto en español)
    name_en        TEXT NOT NULL DEFAULT '',
    tagline_en     TEXT NOT NULL DEFAULT '',
    description_en TEXT NOT NULL DEFAULT ''
);

-- Línea de diseño: eje transversal a la categoría. En la tienda se presenta
-- como «colección» y cada una tiene un personaje (mascota) propio.
CREATE TABLE design_lines (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    slug          TEXT    NOT NULL UNIQUE,
    name          TEXT    NOT NULL,
    mascot        TEXT    NOT NULL DEFAULT '',   -- p. ej. «zorrito»
    native_name   TEXT    NOT NULL DEFAULT '',   -- nombre en japonés o coreano
    tagline       TEXT    NOT NULL DEFAULT '',
    description   TEXT    NOT NULL DEFAULT '',
    color_primary TEXT    NOT NULL DEFAULT '#ff8fb8',
    color_soft    TEXT    NOT NULL DEFAULT '#ffe3ee',
    sort_order    INTEGER NOT NULL DEFAULT 0,
    -- Traducción al inglés. El nombre de la colección es un nombre propio
    -- (Kitsune, Neko…) y no se traduce.
    mascot_en      TEXT NOT NULL DEFAULT '',     -- p. ej. «little fox»
    tagline_en     TEXT NOT NULL DEFAULT '',
    description_en TEXT NOT NULL DEFAULT ''
);

CREATE TABLE products (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    sku             TEXT    NOT NULL UNIQUE,
    slug            TEXT    NOT NULL UNIQUE,
    name            TEXT    NOT NULL,
    category_id     INTEGER NOT NULL REFERENCES categories(id),
    design_line_id  INTEGER NOT NULL REFERENCES design_lines(id),
    brand           TEXT    NOT NULL,
    origin          TEXT    NOT NULL,
    summary         TEXT    NOT NULL,
    description     TEXT    NOT NULL,
    specs_json      TEXT    NOT NULL DEFAULT '{}',
    price_cents     INTEGER NOT NULL CHECK (price_cents >= 0),
    compare_at_cents INTEGER,
    tax_rate        REAL    NOT NULL DEFAULT 0.21,
    stock           INTEGER NOT NULL DEFAULT 0 CHECK (stock >= 0),
    weight_grams    INTEGER NOT NULL DEFAULT 0,
    image_path      TEXT    NOT NULL,
    is_active       INTEGER NOT NULL DEFAULT 1,   -- 0 = retirado del catálogo
    is_featured     INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT    NOT NULL,
    updated_at      TEXT,
    -- Traducción al inglés (vacío = se muestra el texto en español). El
    -- precio no se traduce: es único, en euros, y se convierte al mostrarlo.
    name_en         TEXT    NOT NULL DEFAULT '',
    origin_en       TEXT    NOT NULL DEFAULT '',
    summary_en      TEXT    NOT NULL DEFAULT '',
    description_en  TEXT    NOT NULL DEFAULT '',
    specs_json_en   TEXT    NOT NULL DEFAULT ''
);

CREATE INDEX idx_products_category    ON products(category_id);
CREATE INDEX idx_products_design_line ON products(design_line_id);
CREATE INDEX idx_products_active      ON products(is_active);

-- Condiciones comerciales simuladas
CREATE TABLE coupons (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    code                  TEXT    NOT NULL UNIQUE,
    type                  TEXT    NOT NULL CHECK (type IN ('percent', 'fixed')),
    value                 INTEGER NOT NULL,
    min_items_total_cents INTEGER NOT NULL DEFAULT 0,
    description           TEXT    NOT NULL DEFAULT '',
    valid_until           TEXT,
    is_active             INTEGER NOT NULL DEFAULT 1
);

-- ---------------------------------------------------------------------
-- PERSONAS (todas ficticias: prototipo académico)
-- ---------------------------------------------------------------------

CREATE TABLE customers (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    email        TEXT    NOT NULL UNIQUE,
    full_name    TEXT    NOT NULL,
    phone        TEXT    NOT NULL DEFAULT '',
    address_line TEXT    NOT NULL DEFAULT '',
    postal_code  TEXT    NOT NULL DEFAULT '',
    city         TEXT    NOT NULL DEFAULT '',
    province     TEXT    NOT NULL DEFAULT '',
    country      TEXT    NOT NULL DEFAULT 'ES',
    is_demo      INTEGER NOT NULL DEFAULT 1,
    created_at   TEXT    NOT NULL
);

-- Usuarios internos del back-office
CREATE TABLE staff_users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    email         TEXT    NOT NULL UNIQUE,
    full_name     TEXT    NOT NULL,
    password_hash TEXT    NOT NULL,
    role          TEXT    NOT NULL DEFAULT 'operador',
    created_at    TEXT    NOT NULL
);

-- ---------------------------------------------------------------------
-- DATOS TRANSACCIONALES
-- ---------------------------------------------------------------------

CREATE TABLE orders (
    id                   INTEGER PRIMARY KEY AUTOINCREMENT,
    reference            TEXT    NOT NULL UNIQUE,
    customer_id          INTEGER NOT NULL REFERENCES customers(id),
    status               TEXT    NOT NULL,

    -- Moneda e idioma de la compra. Todos los importes del pedido están en
    -- «currency». fx_rate_micros es el tipo de cambio aplicado, en
    -- millonésimas (unidades de «currency» por cada euro: 850000 = 0,85) y
    -- total_base_cents el contravalor del total en euros, que permite sumar
    -- pedidos hechos en monedas distintas.
    currency             TEXT    NOT NULL DEFAULT 'EUR',
    locale               TEXT    NOT NULL DEFAULT 'es',
    fx_rate_micros       INTEGER NOT NULL DEFAULT 1000000,
    total_base_cents     INTEGER NOT NULL DEFAULT 0,

    -- Desglose económico (todos los importes con IVA salvo los marcados)
    items_total_cents    INTEGER NOT NULL DEFAULT 0,
    discount_cents       INTEGER NOT NULL DEFAULT 0,
    shipping_cents       INTEGER NOT NULL DEFAULT 0,
    giftwrap_cents       INTEGER NOT NULL DEFAULT 0,
    taxable_base_cents   INTEGER NOT NULL DEFAULT 0,  -- sin IVA
    tax_cents            INTEGER NOT NULL DEFAULT 0,  -- cuota de IVA
    total_cents          INTEGER NOT NULL DEFAULT 0,
    coupon_code          TEXT,

    -- Envío
    shipping_method      TEXT    NOT NULL DEFAULT 'estandar',
    shipping_name        TEXT    NOT NULL,
    shipping_address     TEXT    NOT NULL,
    shipping_postal_code TEXT    NOT NULL,
    shipping_city        TEXT    NOT NULL,
    shipping_province    TEXT    NOT NULL,
    shipping_country     TEXT    NOT NULL DEFAULT 'ES',
    gift_wrap            INTEGER NOT NULL DEFAULT 0,
    customer_notes       TEXT    NOT NULL DEFAULT '',

    session_id           TEXT    NOT NULL DEFAULT '',
    created_at           TEXT    NOT NULL,
    updated_at           TEXT    NOT NULL
);

CREATE INDEX idx_orders_status   ON orders(status);
CREATE INDEX idx_orders_customer ON orders(customer_id);
CREATE INDEX idx_orders_created  ON orders(created_at);

-- Línea de pedido: congela ("snapshot") los datos maestros en el momento
-- de la compra, de modo que un cambio posterior de precio o de nombre del
-- producto no altere pedidos históricos.
CREATE TABLE order_lines (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id         INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id       INTEGER REFERENCES products(id),
    sku              TEXT    NOT NULL,
    name             TEXT    NOT NULL,
    design_line      TEXT    NOT NULL DEFAULT '',
    image_path       TEXT    NOT NULL DEFAULT '',
    unit_price_cents INTEGER NOT NULL,
    quantity         INTEGER NOT NULL CHECK (quantity > 0),
    tax_rate         REAL    NOT NULL DEFAULT 0.21,
    line_total_cents INTEGER NOT NULL
);

CREATE INDEX idx_order_lines_order ON order_lines(order_id);

CREATE TABLE payments (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id           INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    reference          TEXT    NOT NULL UNIQUE,
    provider           TEXT    NOT NULL DEFAULT 'simulador-interno',
    method             TEXT    NOT NULL,
    status             TEXT    NOT NULL CHECK (status IN ('autorizado', 'rechazado', 'pendiente')),
    amount_cents       INTEGER NOT NULL,
    currency           TEXT    NOT NULL DEFAULT 'EUR',
    card_brand         TEXT    NOT NULL DEFAULT '',
    card_last4         TEXT    NOT NULL DEFAULT '',
    authorization_code TEXT    NOT NULL DEFAULT '',
    decline_reason     TEXT    NOT NULL DEFAULT '',
    response_json      TEXT    NOT NULL DEFAULT '{}',
    processed_at       TEXT    NOT NULL
);

CREATE INDEX idx_payments_order ON payments(order_id);

CREATE TABLE order_status_history (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id    INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    from_status TEXT,
    to_status   TEXT    NOT NULL,
    changed_by  TEXT    NOT NULL DEFAULT 'sistema',
    note        TEXT    NOT NULL DEFAULT '',
    created_at  TEXT    NOT NULL
);

CREATE INDEX idx_status_history_order ON order_status_history(order_id);

-- Postventa: soporte e incidencias
CREATE TABLE support_tickets (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    reference       TEXT    NOT NULL UNIQUE,
    order_reference TEXT    NOT NULL DEFAULT '',
    customer_name   TEXT    NOT NULL,
    customer_email  TEXT    NOT NULL,
    type            TEXT    NOT NULL,
    subject         TEXT    NOT NULL,
    message         TEXT    NOT NULL,
    status          TEXT    NOT NULL DEFAULT 'abierta',
    created_at      TEXT    NOT NULL,
    locale          TEXT    NOT NULL DEFAULT 'es'   -- idioma en que escribió el cliente
);

-- ---------------------------------------------------------------------
-- FACTURACIÓN Y COMUNICACIONES AL CLIENTE
-- ---------------------------------------------------------------------

-- Factura de un pedido pagado. Es un documento inmutable: al expedirla se
-- congela una copia completa (emisor, cliente, líneas, desglose de IVA y
-- datos del pago) en data_json, de modo que ningún cambio posterior en los
-- datos maestros o en la ficha del cliente puede alterar una factura ya
-- emitida. La numeración es correlativa y sin huecos dentro de cada año
-- (F-2026-000001): por eso el número se asigna al confirmarse el pago y no
-- al crear el pedido (un pedido cuyo pago se rechaza nunca consume número).
CREATE TABLE invoices (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    number      TEXT    NOT NULL UNIQUE,
    series_year INTEGER NOT NULL,
    serial_no   INTEGER NOT NULL,
    order_id    INTEGER NOT NULL UNIQUE REFERENCES orders(id),
    issued_at   TEXT    NOT NULL,
    base_cents  INTEGER NOT NULL,   -- base imponible (sin IVA)
    tax_cents   INTEGER NOT NULL,   -- cuota de IVA
    total_cents INTEGER NOT NULL,
    data_json   TEXT    NOT NULL,
    UNIQUE (series_year, serial_no)
);

CREATE INDEX idx_invoices_issued ON invoices(issued_at);

-- Buzón de correos de prueba. Cada correo transaccional (confirmación con
-- factura, envío, soporte) se guarda completo —cabeceras, HTML y texto
-- plano— para poder consultarlo desde el back-office y descargarlo como
-- fichero .eml. Por defecto ningún mensaje sale de la aplicación; si el
-- equipo activa el envío real por SMTP, delivery_status recoge el resultado
-- (solo_buzon, enviado o fallido) y delivery_detail el motivo. Sin claves
-- ajenas: es un registro de comunicaciones, como la tabla de eventos.
CREATE TABLE mail_outbox (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    message_id TEXT    NOT NULL UNIQUE,
    template   TEXT    NOT NULL,
    to_email   TEXT    NOT NULL,
    to_name    TEXT    NOT NULL DEFAULT '',
    from_email TEXT    NOT NULL,
    from_name  TEXT    NOT NULL DEFAULT '',
    subject    TEXT    NOT NULL,
    body_html  TEXT    NOT NULL,
    body_text  TEXT    NOT NULL,
    order_id   INTEGER,
    invoice_id INTEGER,
    created_at TEXT    NOT NULL,
    read_at    TEXT,
    delivery_status TEXT NOT NULL DEFAULT 'solo_buzon',
    delivery_detail TEXT NOT NULL DEFAULT '',
    delivered_at    TEXT
);

CREATE INDEX idx_mail_created ON mail_outbox(created_at);
CREATE INDEX idx_mail_order   ON mail_outbox(order_id);
CREATE INDEX idx_mail_to      ON mail_outbox(to_email);

-- ---------------------------------------------------------------------
-- INSTRUMENTACIÓN: eventos de negocio
-- ---------------------------------------------------------------------
-- Cada fila es un evento inmutable con un sobre común (envelope) y una
-- carga útil específica en payload_json. Se replica en storage/events/
-- en formato JSON Lines para su consumo por sistemas externos (Tarea 2).
--
-- Decisión de diseño: esta tabla NO declara claves ajenas. Un evento es
-- un hecho pasado que debe sobrevivir aunque el dato maestro al que se
-- refiere se borre después (por ejemplo, un producto eliminado desde el
-- back-office). Por eso la carga útil lleva también el SKU y el nombre.

CREATE TABLE events (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id       TEXT    NOT NULL UNIQUE,
    event_name     TEXT    NOT NULL,
    schema_version TEXT    NOT NULL DEFAULT '1.0',
    source         TEXT    NOT NULL DEFAULT 'kitsune-notes.web',
    occurred_at    TEXT    NOT NULL,
    session_id     TEXT    NOT NULL DEFAULT '',
    actor_type     TEXT    NOT NULL DEFAULT 'invitado',
    customer_id    INTEGER,
    product_id     INTEGER,
    order_id       INTEGER,
    payload_json   TEXT    NOT NULL DEFAULT '{}',
    ip_hash        TEXT    NOT NULL DEFAULT '',
    user_agent     TEXT    NOT NULL DEFAULT '',
    created_at     TEXT    NOT NULL
);

CREATE INDEX idx_events_name       ON events(event_name);
CREATE INDEX idx_events_occurred   ON events(occurred_at);
CREATE INDEX idx_events_session    ON events(session_id);
CREATE INDEX idx_events_order      ON events(order_id);
CREATE INDEX idx_events_product    ON events(product_id);
