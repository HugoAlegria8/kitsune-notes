-- =====================================================================
--  Kitsune Notes - Esquema equivalente para MySQL / MariaDB
--
--  Se incluye para el despliegue en hosting compartido, donde lo
--  habitual es disponer de MySQL en lugar de SQLite. La aplicación no
--  cambia: toda la persistencia pasa por PDO y basta con ajustar
--  KN_DB_DRIVER=mysql y las credenciales en el fichero .env.
--
--  Las fechas se guardan como cadenas ISO-8601 (igual que en SQLite)
--  para que el formato de los eventos sea idéntico en ambos motores.
-- =====================================================================

CREATE TABLE categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug        VARCHAR(80)  NOT NULL UNIQUE,
    name        VARCHAR(120) NOT NULL,
    tagline     VARCHAR(190) NOT NULL DEFAULT '',
    description TEXT         NOT NULL,
    icon        VARCHAR(40)  NOT NULL DEFAULT '',
    sort_order  INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE design_lines (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug          VARCHAR(80)  NOT NULL UNIQUE,
    name          VARCHAR(120) NOT NULL,
    mascot        VARCHAR(60)  NOT NULL DEFAULT '',
    native_name   VARCHAR(40)  NOT NULL DEFAULT '',
    tagline       VARCHAR(190) NOT NULL DEFAULT '',
    description   TEXT         NOT NULL,
    color_primary VARCHAR(16)  NOT NULL DEFAULT '#ff8fb8',
    color_soft    VARCHAR(16)  NOT NULL DEFAULT '#ffe3ee',
    sort_order    INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku              VARCHAR(40)  NOT NULL UNIQUE,
    slug             VARCHAR(140) NOT NULL UNIQUE,
    name             VARCHAR(190) NOT NULL,
    category_id      INT UNSIGNED NOT NULL,
    design_line_id   INT UNSIGNED NOT NULL,
    brand            VARCHAR(120) NOT NULL,
    origin           VARCHAR(80)  NOT NULL,
    summary          VARCHAR(400) NOT NULL,
    description      TEXT         NOT NULL,
    specs_json       TEXT         NOT NULL,
    price_cents      INT          NOT NULL,
    compare_at_cents INT          NULL,
    tax_rate         DECIMAL(5,4) NOT NULL DEFAULT 0.2100,
    stock            INT          NOT NULL DEFAULT 0,
    weight_grams     INT          NOT NULL DEFAULT 0,
    image_path       VARCHAR(190) NOT NULL,
    is_active        TINYINT(1)   NOT NULL DEFAULT 1,
    is_featured      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at       VARCHAR(40)  NOT NULL,
    updated_at       VARCHAR(40)  NULL,
    CONSTRAINT fk_products_category    FOREIGN KEY (category_id)    REFERENCES categories(id),
    CONSTRAINT fk_products_design_line FOREIGN KEY (design_line_id) REFERENCES design_lines(id),
    INDEX idx_products_category (category_id),
    INDEX idx_products_design_line (design_line_id),
    INDEX idx_products_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupons (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                  VARCHAR(40)  NOT NULL UNIQUE,
    type                  VARCHAR(16)  NOT NULL,
    value                 INT          NOT NULL,
    min_items_total_cents INT          NOT NULL DEFAULT 0,
    description           VARCHAR(255) NOT NULL DEFAULT '',
    valid_until           VARCHAR(40)  NULL,
    is_active             TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(190) NOT NULL UNIQUE,
    full_name    VARCHAR(190) NOT NULL,
    phone        VARCHAR(40)  NOT NULL DEFAULT '',
    address_line VARCHAR(190) NOT NULL DEFAULT '',
    postal_code  VARCHAR(12)  NOT NULL DEFAULT '',
    city         VARCHAR(120) NOT NULL DEFAULT '',
    province     VARCHAR(120) NOT NULL DEFAULT '',
    country      VARCHAR(4)   NOT NULL DEFAULT 'ES',
    is_demo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   VARCHAR(40)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE staff_users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(190) NOT NULL UNIQUE,
    full_name     VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          VARCHAR(40)  NOT NULL DEFAULT 'operador',
    created_at    VARCHAR(40)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference            VARCHAR(40)  NOT NULL UNIQUE,
    customer_id          INT UNSIGNED NOT NULL,
    status               VARCHAR(40)  NOT NULL,
    currency             VARCHAR(4)   NOT NULL DEFAULT 'EUR',
    items_total_cents    INT NOT NULL DEFAULT 0,
    discount_cents       INT NOT NULL DEFAULT 0,
    shipping_cents       INT NOT NULL DEFAULT 0,
    giftwrap_cents       INT NOT NULL DEFAULT 0,
    taxable_base_cents   INT NOT NULL DEFAULT 0,
    tax_cents            INT NOT NULL DEFAULT 0,
    total_cents          INT NOT NULL DEFAULT 0,
    coupon_code          VARCHAR(40)  NULL,
    shipping_method      VARCHAR(40)  NOT NULL DEFAULT 'estandar',
    shipping_name        VARCHAR(190) NOT NULL,
    shipping_address     VARCHAR(190) NOT NULL,
    shipping_postal_code VARCHAR(12)  NOT NULL,
    shipping_city        VARCHAR(120) NOT NULL,
    shipping_province    VARCHAR(120) NOT NULL,
    shipping_country     VARCHAR(4)   NOT NULL DEFAULT 'ES',
    gift_wrap            TINYINT(1)   NOT NULL DEFAULT 0,
    customer_notes       VARCHAR(500) NOT NULL DEFAULT '',
    session_id           VARCHAR(190) NOT NULL DEFAULT '',
    created_at           VARCHAR(40)  NOT NULL,
    updated_at           VARCHAR(40)  NOT NULL,
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    INDEX idx_orders_status (status),
    INDEX idx_orders_customer (customer_id),
    INDEX idx_orders_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_lines (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id         INT UNSIGNED NOT NULL,
    product_id       INT UNSIGNED NULL,
    sku              VARCHAR(40)  NOT NULL,
    name             VARCHAR(190) NOT NULL,
    design_line      VARCHAR(120) NOT NULL DEFAULT '',
    image_path       VARCHAR(190) NOT NULL DEFAULT '',
    unit_price_cents INT          NOT NULL,
    quantity         INT          NOT NULL,
    tax_rate         DECIMAL(5,4) NOT NULL DEFAULT 0.2100,
    line_total_cents INT          NOT NULL,
    CONSTRAINT fk_lines_order   FOREIGN KEY (order_id)   REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_lines_product FOREIGN KEY (product_id) REFERENCES products(id),
    INDEX idx_order_lines_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id           INT UNSIGNED NOT NULL,
    reference          VARCHAR(60)  NOT NULL UNIQUE,
    provider           VARCHAR(60)  NOT NULL DEFAULT 'simulador-interno',
    method             VARCHAR(40)  NOT NULL,
    status             VARCHAR(20)  NOT NULL,
    amount_cents       INT          NOT NULL,
    currency           VARCHAR(4)   NOT NULL DEFAULT 'EUR',
    card_brand         VARCHAR(40)  NOT NULL DEFAULT '',
    card_last4         VARCHAR(4)   NOT NULL DEFAULT '',
    authorization_code VARCHAR(40)  NOT NULL DEFAULT '',
    decline_reason     VARCHAR(190) NOT NULL DEFAULT '',
    response_json      TEXT         NOT NULL,
    processed_at       VARCHAR(40)  NOT NULL,
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_payments_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_status_history (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id    INT UNSIGNED NOT NULL,
    from_status VARCHAR(40)  NULL,
    to_status   VARCHAR(40)  NOT NULL,
    changed_by  VARCHAR(190) NOT NULL DEFAULT 'sistema',
    note        VARCHAR(500) NOT NULL DEFAULT '',
    created_at  VARCHAR(40)  NOT NULL,
    CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_status_history_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE support_tickets (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference       VARCHAR(40)  NOT NULL UNIQUE,
    order_reference VARCHAR(40)  NOT NULL DEFAULT '',
    customer_name   VARCHAR(190) NOT NULL,
    customer_email  VARCHAR(190) NOT NULL,
    type            VARCHAR(40)  NOT NULL,
    subject         VARCHAR(190) NOT NULL,
    message         TEXT         NOT NULL,
    status          VARCHAR(20)  NOT NULL DEFAULT 'abierta',
    created_at      VARCHAR(40)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Factura de un pedido pagado: documento inmutable con copia congelada en
-- data_json y numeración correlativa sin huecos por año (F-2026-000001).
CREATE TABLE invoices (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number      VARCHAR(30)       NOT NULL UNIQUE,
    series_year SMALLINT UNSIGNED NOT NULL,
    serial_no   INT UNSIGNED      NOT NULL,
    order_id    INT UNSIGNED      NOT NULL UNIQUE,
    issued_at   VARCHAR(40)       NOT NULL,
    base_cents  INT               NOT NULL,
    tax_cents   INT               NOT NULL,
    total_cents INT               NOT NULL,
    data_json   MEDIUMTEXT        NOT NULL,
    CONSTRAINT fk_invoices_order FOREIGN KEY (order_id) REFERENCES orders(id),
    UNIQUE KEY uq_invoices_serie (series_year, serial_no),
    INDEX idx_invoices_issued (issued_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Buzón de correos de prueba. Por defecto ningún mensaje sale de la aplicación;
-- con el envío real por SMTP activado, delivery_status guarda el resultado
-- (solo_buzon, enviado o fallido) y delivery_detail el motivo.
CREATE TABLE mail_outbox (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id VARCHAR(120) NOT NULL UNIQUE,
    template   VARCHAR(40)  NOT NULL,
    to_email   VARCHAR(190) NOT NULL,
    to_name    VARCHAR(190) NOT NULL DEFAULT '',
    from_email VARCHAR(190) NOT NULL,
    from_name  VARCHAR(190) NOT NULL DEFAULT '',
    subject    VARCHAR(255) NOT NULL,
    body_html  MEDIUMTEXT   NOT NULL,
    body_text  MEDIUMTEXT   NOT NULL,
    order_id   INT UNSIGNED NULL,
    invoice_id INT UNSIGNED NULL,
    created_at VARCHAR(40)  NOT NULL,
    read_at    VARCHAR(40)  NULL,
    delivery_status VARCHAR(20)  NOT NULL DEFAULT 'solo_buzon',
    delivery_detail VARCHAR(500) NOT NULL DEFAULT '',
    delivered_at    VARCHAR(40)  NULL,
    INDEX idx_mail_created (created_at),
    INDEX idx_mail_order (order_id),
    INDEX idx_mail_to (to_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id       VARCHAR(40)  NOT NULL UNIQUE,
    event_name     VARCHAR(60)  NOT NULL,
    schema_version VARCHAR(10)  NOT NULL DEFAULT '1.0',
    source         VARCHAR(60)  NOT NULL DEFAULT 'kitsune-notes.web',
    occurred_at    VARCHAR(40)  NOT NULL,
    session_id     VARCHAR(190) NOT NULL DEFAULT '',
    actor_type     VARCHAR(40)  NOT NULL DEFAULT 'invitado',
    customer_id    INT UNSIGNED NULL,
    product_id     INT UNSIGNED NULL,
    order_id       INT UNSIGNED NULL,
    payload_json   TEXT         NOT NULL,
    ip_hash        VARCHAR(64)  NOT NULL DEFAULT '',
    user_agent     VARCHAR(255) NOT NULL DEFAULT '',
    created_at     VARCHAR(40)  NOT NULL,
    INDEX idx_events_name (event_name),
    INDEX idx_events_occurred (occurred_at),
    INDEX idx_events_session (session_id),
    INDEX idx_events_order (order_id),
    INDEX idx_events_product (product_id)
    -- Sin claves ajenas a propósito: los eventos sobreviven al borrado del dato maestro.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
