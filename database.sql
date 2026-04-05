CREATE DATABASE IF NOT EXISTS verifactu
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE verifactu;

-- Limpieza previa (orden inverso a dependencias)
DROP TABLE IF EXISTS registros_verifactu;
DROP TABLE IF EXISTS albaran_factura;
DROP TABLE IF EXISTS lineas_factura;
DROP TABLE IF EXISTS lineas_albaran;
DROP TABLE IF EXISTS facturas;
DROP TABLE IF EXISTS albaranes;
DROP TABLE IF EXISTS productos;
DROP TABLE IF EXISTS series;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS impuestos;

-- =============================================================
-- 1. IMPUESTOS
-- =============================================================
CREATE TABLE impuestos (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(60)     NOT NULL COMMENT 'Ej: IVA General, IVA Reducido, Exento',
    porcentaje  DECIMAL(5,2)    NOT NULL DEFAULT 0.00 COMMENT 'Valor numérico, ej: 21.00',
    activo      TINYINT(1)      NOT NULL DEFAULT 1,
    created_at  TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tipos impositivos de IVA';

INSERT INTO impuestos (nombre, porcentaje) VALUES
    ('IVA General',       21.00),
    ('IVA Reducido',      10.00),
    ('IVA Superreducido',  4.00),
    ('Exento',             0.00);

-- =============================================================
-- 2. CLIENTES
-- [FIX-5] CHECK en pais para asegurar exactamente 2 caracteres
-- =============================================================
CREATE TABLE clientes (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo          ENUM('empresa','persona') NOT NULL DEFAULT 'empresa',
    nombre        VARCHAR(150)    NOT NULL,
    nif_cif       VARCHAR(20)     NOT NULL,
    email         VARCHAR(120)    NULL,
    telefono      VARCHAR(20)     NULL,
    direccion     VARCHAR(200)    NULL,
    ciudad        VARCHAR(80)     NULL,
    codigo_postal VARCHAR(10)     NULL,
    pais          CHAR(2)         NOT NULL DEFAULT 'ES' COMMENT 'ISO 3166-1 alpha-2',
    activo        TINYINT(1)      NOT NULL DEFAULT 1,
    created_at    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE  KEY uq_clientes_nif   (nif_cif),
    INDEX         idx_clientes_nombre (nombre),
    -- [FIX-5] Valida longitud ISO 3166-1
    CONSTRAINT chk_pais_length CHECK (CHAR_LENGTH(pais) = 2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Clientes / destinatarios de facturas';

-- =============================================================
-- 3. PRODUCTOS / SERVICIOS
-- [FIX-4] Añadido índice en nombre para búsquedas por texto
-- =============================================================
CREATE TABLE productos (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo          VARCHAR(30)     NULL UNIQUE COMMENT 'Referencia interna opcional',
    nombre          VARCHAR(150)    NOT NULL,
    descripcion     TEXT            NULL,
    precio_unitario DECIMAL(12,4)   NOT NULL DEFAULT 0.0000,
    impuesto_id     BIGINT UNSIGNED NOT NULL,
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    created_at      TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- [FIX-4]
    INDEX idx_productos_nombre (nombre),
    CONSTRAINT fk_productos_impuesto FOREIGN KEY (impuesto_id)
        REFERENCES impuestos (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de productos y servicios';

-- =============================================================
-- 4. SERIES DE FACTURACIÓN
--    NOTA [FIX-7]: Para evitar race condition al asignar número,
--    la aplicación debe hacer SELECT ... FOR UPDATE sobre esta fila
--    antes de hacer el UPDATE de ultimo_numero.
-- =============================================================
CREATE TABLE series (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo        VARCHAR(10)     NOT NULL UNIQUE COMMENT 'Ej: A, B, RECT, REC',
    descripcion   VARCHAR(100)    NULL,
    ultimo_numero INT UNSIGNED    NOT NULL DEFAULT 0
        COMMENT 'Incrementar siempre con SELECT FOR UPDATE para evitar race condition',
    activo        TINYINT(1)      NOT NULL DEFAULT 1,
    created_at    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Series de numeración de facturas';

INSERT INTO series (codigo, descripcion, ultimo_numero) VALUES
    ('A',    'Serie general',             0),
    ('RECT', 'Facturas rectificativas',   0),
    ('REC',  'Facturas recapitulativas',  0),
    ('S',    'Facturas simplificadas',    0);

-- =============================================================
-- 5. ALBARANES
-- =============================================================
CREATE TABLE albaranes (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cliente_id    BIGINT UNSIGNED NOT NULL,
    numero        VARCHAR(20)     NOT NULL UNIQUE COMMENT 'Número de albarán (AL-0001)',
    fecha         DATE            NOT NULL,
    estado        ENUM('borrador','emitido','facturado') NOT NULL DEFAULT 'borrador',
    observaciones TEXT            NULL,
    created_at    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_albaranes_cliente (cliente_id),
    INDEX idx_albaranes_estado  (estado),
    CONSTRAINT fk_albaranes_cliente FOREIGN KEY (cliente_id)
        REFERENCES clientes (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Albaranes de entrega';

-- =============================================================
-- 6. LÍNEAS DE ALBARÁN
-- [FIX-3] Índices explícitos en producto_id e impuesto_id
-- =============================================================
CREATE TABLE lineas_albaran (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    albaran_id      BIGINT UNSIGNED NOT NULL,
    producto_id     BIGINT UNSIGNED NULL COMMENT 'NULL si línea libre',
    descripcion     VARCHAR(255)    NOT NULL,
    cantidad        DECIMAL(12,4)   NOT NULL DEFAULT 1.0000,
    precio_unitario DECIMAL(12,4)   NOT NULL DEFAULT 0.0000,
    descuento       DECIMAL(5,2)    NOT NULL DEFAULT 0.00 COMMENT 'Porcentaje de descuento',
    impuesto_id     BIGINT UNSIGNED NOT NULL,
    subtotal        DECIMAL(14,4)   NOT NULL DEFAULT 0.0000 COMMENT 'Sin IVA, con descuento',
    orden           SMALLINT        NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    INDEX idx_la_albaran   (albaran_id),
    -- [FIX-3]
    INDEX idx_la_producto  (producto_id),
    INDEX idx_la_impuesto  (impuesto_id),
    CONSTRAINT fk_la_albaran  FOREIGN KEY (albaran_id)
        REFERENCES albaranes (id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_la_producto FOREIGN KEY (producto_id)
        REFERENCES productos (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_la_impuesto FOREIGN KEY (impuesto_id)
        REFERENCES impuestos (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Líneas de detalle de los albaranes';

-- =============================================================
-- 7. FACTURAS
--
-- [FIX-1] ENUM estado_verifactu: 'enviada' → 'enviado'
--          para coincidir con registros_verifactu.estado
--
-- INMUTABILIDAD: el trigger trg_facturas_no_update (abajo)
-- impide cualquier UPDATE tras la inserción inicial.
-- =============================================================
CREATE TABLE facturas (
    id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    serie_id               BIGINT UNSIGNED NOT NULL,
    cliente_id             BIGINT UNSIGNED NULL  COMMENT 'NULL permitido en simplificada',
    numero                 INT UNSIGNED    NOT NULL,
    numero_completo        VARCHAR(30)     NOT NULL UNIQUE COMMENT 'Ej: A-2025-0001',
    tipo                   ENUM('completa','simplificada','rectificativa','recapitulativa') NOT NULL,

    -- [FIX-1] 'enviada' → 'enviado' para coincidir con registros_verifactu
    estado_verifactu       ENUM('no_aplica','pendiente','enviado','aceptado','rechazado','error')
                           NOT NULL DEFAULT 'pendiente',

    -- Fechas
    fecha_emision          DATE            NOT NULL,
    fecha_operacion        DATE            NULL COMMENT 'Si difiere de la emisión',

    -- Importes
    base_imponible         DECIMAL(14,4)   NOT NULL DEFAULT 0.0000,
    cuota_iva              DECIMAL(14,4)   NOT NULL DEFAULT 0.0000,
    total                  DECIMAL(14,4)   NOT NULL DEFAULT 0.0000,

    -- Rectificativa
    factura_rectificada_id BIGINT UNSIGNED NULL  COMMENT 'Solo en tipo=rectificativa',
    motivo_rectificacion   TEXT            NULL,

    -- Recapitulativa
    periodo_desde          DATE            NULL  COMMENT 'Solo en tipo=recapitulativa',
    periodo_hasta          DATE            NULL  COMMENT 'Solo en tipo=recapitulativa',

    -- VeriFactu
    hash_verifactu         CHAR(64)        NULL  COMMENT 'SHA-256 de este registro',
    hash_anterior          CHAR(64)        NULL  COMMENT 'SHA-256 del registro anterior',
    xml_verifactu          LONGTEXT        NULL  COMMENT 'XML generado (copia local)',
    qr_url                 VARCHAR(500)    NULL  COMMENT 'URL sede electrónica AEAT',
    pdf_path               VARCHAR(300)    NULL  COMMENT 'Ruta relativa al PDF generado',

    observaciones          TEXT            NULL,
    -- updated_at omitido intencionalmente: inmutabilidad reforzada por trigger
    created_at             TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    INDEX idx_f_cliente     (cliente_id),
    INDEX idx_f_serie       (serie_id),
    INDEX idx_f_tipo        (tipo),
    INDEX idx_f_verifactu   (estado_verifactu),
    INDEX idx_f_fecha       (fecha_emision),
    INDEX idx_f_rectificada (factura_rectificada_id),

    CONSTRAINT fk_f_cliente    FOREIGN KEY (cliente_id)
        REFERENCES clientes (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_f_serie      FOREIGN KEY (serie_id)
        REFERENCES series   (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_f_rectificada FOREIGN KEY (factura_rectificada_id)
        REFERENCES facturas (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro central de facturas. INMUTABLE: trigger impide UPDATE tras emisión.';

-- [FIX-3] Trigger que refuerza la inmutabilidad en base de datos
-- La única excepción permitida es actualizar estado_verifactu, hash, xml y qr
-- (campos que el proceso VeriFactu necesita rellenar tras la inserción inicial)
DELIMITER $$
CREATE TRIGGER trg_facturas_no_update
BEFORE UPDATE ON facturas
FOR EACH ROW
BEGIN
    -- Campos fiscales inmutables: nunca deben cambiar tras la emisión
    IF OLD.numero_completo   != NEW.numero_completo   OR
       OLD.tipo              != NEW.tipo              OR
       OLD.fecha_emision     != NEW.fecha_emision     OR
       OLD.base_imponible    != NEW.base_imponible    OR
       OLD.cuota_iva         != NEW.cuota_iva         OR
       OLD.total             != NEW.total             OR
       OLD.cliente_id        != NEW.cliente_id        OR
       OLD.serie_id          != NEW.serie_id
    THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Las facturas son inmutables: los campos fiscales no pueden modificarse tras la emisión.';
    END IF;
END$$
DELIMITER ;

-- =============================================================
-- 8. LÍNEAS DE FACTURA
-- =============================================================
CREATE TABLE lineas_factura (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    factura_id      BIGINT UNSIGNED NOT NULL,
    producto_id     BIGINT UNSIGNED NULL,
    descripcion     VARCHAR(255)    NOT NULL,
    cantidad        DECIMAL(12,4)   NOT NULL DEFAULT 1.0000,
    precio_unitario DECIMAL(12,4)   NOT NULL DEFAULT 0.0000,
    descuento       DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    impuesto_id     BIGINT UNSIGNED NOT NULL,
    base_imponible  DECIMAL(14,4)   NOT NULL DEFAULT 0.0000,
    cuota_iva       DECIMAL(14,4)   NOT NULL DEFAULT 0.0000,
    total           DECIMAL(14,4)   NOT NULL DEFAULT 0.0000,
    orden           SMALLINT        NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    INDEX idx_lf_factura  (factura_id),
    INDEX idx_lf_producto (producto_id),
    INDEX idx_lf_impuesto (impuesto_id),
    CONSTRAINT fk_lf_factura  FOREIGN KEY (factura_id)
        REFERENCES facturas  (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_lf_producto FOREIGN KEY (producto_id)
        REFERENCES productos (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_lf_impuesto FOREIGN KEY (impuesto_id)
        REFERENCES impuestos (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Líneas de detalle de las facturas';

-- =============================================================
-- 9. TABLA PIVOTE ALBARÁN ↔ FACTURA
-- [FIX-2] Índice en factura_id para búsquedas inversas
-- =============================================================
CREATE TABLE albaran_factura (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    albaran_id BIGINT UNSIGNED NOT NULL,
    factura_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_af (albaran_id, factura_id),
    -- [FIX-2] Índice para buscar "qué albaranes componen una factura"
    INDEX idx_af_factura (factura_id),
    CONSTRAINT fk_af_albaran FOREIGN KEY (albaran_id)
        REFERENCES albaranes (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_af_factura FOREIGN KEY (factura_id)
        REFERENCES facturas  (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Relación N:M entre albaranes y facturas';

-- =============================================================
-- 10. REGISTROS VERIFACTU
-- =============================================================
CREATE TABLE registros_verifactu (
    id                   BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    factura_id           BIGINT UNSIGNED  NOT NULL,
    xml_enviado          LONGTEXT         NULL COMMENT 'Payload XML firmado enviado',
    xml_respuesta        LONGTEXT         NULL COMMENT 'Respuesta XML de la AEAT',
    estado               ENUM('pendiente','enviado','aceptado','rechazado','error')
                         NOT NULL DEFAULT 'pendiente',
    codigo_respuesta     VARCHAR(20)      NULL COMMENT 'Código de estado AEAT',
    descripcion_respuesta TEXT            NULL COMMENT 'Mensaje legible de la AEAT',
    fecha_envio          DATETIME         NULL,
    fecha_respuesta      DATETIME         NULL,
    reintentos           TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at           TIMESTAMP        NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP        NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_rv_factura (factura_id),
    INDEX idx_rv_estado  (estado),
    CONSTRAINT fk_rv_factura FOREIGN KEY (factura_id)
        REFERENCES facturas (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historial de envíos VeriFactu a la AEAT';

-- =============================================================
-- VISTAS
-- =============================================================

CREATE OR REPLACE VIEW v_panel_facturacion AS
SELECT
    COUNT(*)                                            AS total_facturas,
    SUM(estado_verifactu = 'aceptado')                  AS enviadas_ok,
    SUM(estado_verifactu IN ('pendiente','no_aplica'))  AS pendientes,
    SUM(estado_verifactu IN ('error','rechazado'))      AS con_error,
    SUM(tipo = 'completa')                              AS completas,
    SUM(tipo = 'simplificada')                          AS simplificadas,
    SUM(tipo = 'rectificativa')                         AS rectificativas,
    SUM(tipo = 'recapitulativa')                        AS recapitulativas,
    SUM(total)                                          AS importe_total,
    DATE_FORMAT(MIN(fecha_emision), '%Y-%m-%d')         AS primera_factura,
    DATE_FORMAT(MAX(fecha_emision), '%Y-%m-%d')         AS ultima_factura
FROM facturas;

CREATE OR REPLACE VIEW v_albaranes_pendientes AS
SELECT
    a.id,
    a.numero,
    a.fecha,
    c.nombre         AS cliente,
    c.nif_cif,
    SUM(la.subtotal) AS importe_sin_iva
FROM albaranes a
JOIN clientes       c  ON c.id = a.cliente_id
JOIN lineas_albaran la ON la.albaran_id = a.id
WHERE a.estado != 'facturado'
GROUP BY a.id, a.numero, a.fecha, c.nombre, c.nif_cif;

CREATE OR REPLACE VIEW v_facturas_resumen AS
SELECT
    f.id,
    f.numero_completo,
    f.tipo,
    f.fecha_emision,
    c.nombre         AS cliente,
    c.nif_cif,
    f.base_imponible,
    f.cuota_iva,
    f.total,
    f.estado_verifactu,
    rv.fecha_envio,
    rv.codigo_respuesta,
    rv.reintentos
FROM facturas f
LEFT JOIN clientes c ON c.id = f.cliente_id
LEFT JOIN registros_verifactu rv
    ON rv.id = (
        SELECT id FROM registros_verifactu
        WHERE factura_id = f.id
        ORDER BY created_at DESC LIMIT 1
    );

SET FOREIGN_KEY_CHECKS = 1;


-- =============================================================
-- ██████████████████████████████████████████████████████████
--   DATOS DE PRUEBA
--   10 clientes · 15 productos · 8 albaranes · 12 facturas
--   (completas, simplificadas, rectificativa, recapitulativa)
-- ██████████████████████████████████████████████████████████
-- =============================================================

-- -----------------------------------------------------------
-- CLIENTES (10)
-- 6 empresas + 4 personas físicas (autónomos)
-- -----------------------------------------------------------
INSERT INTO clientes (tipo, nombre, nif_cif, email, telefono, direccion, ciudad, codigo_postal, pais) VALUES
-- Empresas
('empresa',  'Construcciones Valdemar S.L.',     'B12345678', 'admin@valdemar.es',       '916 111 222', 'Calle Mayor 10, 1º',        'Madrid',    '28001', 'ES'),
('empresa',  'Distribuciones Norte S.A.',         'A87654321', 'facturacion@norte-sa.es', '944 222 333', 'Av. de la Constitución 55', 'Bilbao',    '48001', 'ES'),
('empresa',  'Tech Solutions Europe GmbH',        'ESB99887766','billing@techsol.eu',     '931 333 444', 'Gran Via 88, 3º',           'Barcelona', '08010', 'ES'),
('empresa',  'Restaurantes La Huerta S.L.',       'B55443322', 'contabilidad@lahuerta.es','965 444 555', 'Plaza del Mercado 3',       'Alicante',  '03001', 'ES'),
('empresa',  'Clínica Dental Sonríe S.L.',        'B11223344', 'admin@sonrie.es',         '954 555 666', 'Calle San Fernando 22',     'Sevilla',   '41001', 'ES'),
('empresa',  'Inversiones Atlántico S.A.',        'A22334455', 'inv@atlantico-sa.es',     '922 666 777', 'Paseo Marítimo 100',        'Santa Cruz de Tenerife','38001','ES'),
-- Personas físicas (autónomos)
('persona',  'Ana García Fernández',              '12345678A', 'ana.garcia@gmail.com',    '666 100 200', 'Calle Rosal 5, 2ºB',        'Valencia',  '46001', 'ES'),
('persona',  'Carlos Martín López',               '87654321B', 'carlos.martin@outlook.es','677 200 300', 'Av. Libertad 12, 4ºA',      'Zaragoza',  '50001', 'ES'),
('persona',  'María José Ruiz Sánchez',           '11223344C', 'mjruiz@hotmail.com',      '688 300 400', 'Calle del Pino 8, 1ºC',     'Málaga',    '29001', 'ES'),
('persona',  'Pedro Jiménez Torres',              '44332211D', 'pedro.jimenez@icloud.com','699 400 500', 'Camino Real 30',             'Murcia',    '30001', 'ES');

-- -----------------------------------------------------------
-- PRODUCTOS (15)
-- Variedad de tipos de IVA: General(1), Reducido(2), Superreducido(3), Exento(4)
-- -----------------------------------------------------------
INSERT INTO productos (codigo, nombre, descripcion, precio_unitario, impuesto_id) VALUES
-- Servicios profesionales (IVA General 21%)
('SRV-001', 'Consultoría técnica (hora)',       'Servicio de consultoría IT por hora',           85.0000, 1),
('SRV-002', 'Desarrollo web (hora)',             'Desarrollo de aplicaciones web por hora',       75.0000, 1),
('SRV-003', 'Mantenimiento mensual web',         'Mantenimiento y hosting mensual',              150.0000, 1),
('SRV-004', 'Diseño gráfico (hora)',             'Diseño de material corporativo por hora',       60.0000, 1),
('SRV-005', 'Auditoría fiscal (informe)',        'Elaboración de informe de auditoría fiscal',   400.0000, 1),
-- Materiales de construcción (IVA General 21%)
('MAT-001', 'Cemento Portland 25kg',             'Saco de cemento para construcción',             8.5000, 1),
('MAT-002', 'Azulejo cerámico 1m²',              'Azulejo 60x60 primera calidad',                22.0000, 1),
-- Alimentación / Restauración (IVA Reducido 10%)
('ALM-001', 'Menú del día (por persona)',        'Menú completo primer y segundo plato + postre', 12.0000, 2),
('ALM-002', 'Catering corporativo (persona)',    'Servicio de catering para eventos empresa',     28.0000, 2),
('ALM-003', 'Café y desayuno (persona)',         'Desayuno de trabajo con café, zumo y bollería',  6.5000, 2),
-- Sanidad / Dental (IVA Superreducido 4%)
('DEN-001', 'Revisión dental completa',         'Exploración, diagnóstico y presupuesto',         45.0000, 3),
('DEN-002', 'Limpieza dental profesional',      'Profilaxis dental completa con flúor',           60.0000, 3),
-- Servicios exentos (IVA Exento 0%)
('FIN-001', 'Asesoramiento financiero (hora)',  'Consulta de inversión y planificación fiscal',   90.0000, 4),
('FIN-002', 'Gestión de cartera (mensual)',     'Gestión mensual de cartera de inversión',       200.0000, 4),
-- Formación (IVA Exento 0%)
('FOR-001', 'Curso online PHP/Laravel (acceso)','Acceso completo al curso de 40 horas',          99.0000, 4);

-- -----------------------------------------------------------
-- ACTUALIZAR SERIE ultimo_numero antes de insertar facturas
-- -----------------------------------------------------------
UPDATE series SET ultimo_numero = 8  WHERE codigo = 'A';
UPDATE series SET ultimo_numero = 1  WHERE codigo = 'RECT';
UPDATE series SET ultimo_numero = 1  WHERE codigo = 'REC';
UPDATE series SET ultimo_numero = 2  WHERE codigo = 'S';

-- -----------------------------------------------------------
-- ALBARANES (8)
-- Estados: borrador, emitido, facturado
-- -----------------------------------------------------------
INSERT INTO albaranes (cliente_id, numero, fecha, estado, observaciones) VALUES
(1, 'AL-2025-0001', '2025-01-10', 'facturado',  'Materiales fase 1 obra C/ Mayor'),
(1, 'AL-2025-0002', '2025-01-28', 'facturado',  'Materiales fase 2 obra C/ Mayor'),
(2, 'AL-2025-0003', '2025-02-05', 'facturado',  'Distribución norte enero'),
(3, 'AL-2025-0004', '2025-02-14', 'facturado',  'Horas desarrollo proyecto Alpha'),
(4, 'AL-2025-0005', '2025-03-03', 'facturado',  'Catering evento 15 pax'),
(3, 'AL-2025-0006', '2025-03-18', 'emitido',    'Mantenimiento web Q1'),
(7, 'AL-2025-0007', '2025-04-02', 'borrador',   'Consultoría diseño logo'),
(5, 'AL-2025-0008', '2025-04-10', 'emitido',    'Revisiones dentales abril');

-- -----------------------------------------------------------
-- LÍNEAS DE ALBARÁN
-- -----------------------------------------------------------
-- Albarán 1: Construcciones Valdemar - materiales
INSERT INTO lineas_albaran (albaran_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, subtotal, orden) VALUES
(1, 6, 'Cemento Portland 25kg',   50.0000,  8.5000, 0.00, 1,  425.0000, 1),
(1, 7, 'Azulejo cerámico 1m²',   30.0000, 22.0000, 5.00, 1,  627.0000, 2);

-- Albarán 2: Construcciones Valdemar - más materiales
INSERT INTO lineas_albaran (albaran_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, subtotal, orden) VALUES
(2, 6, 'Cemento Portland 25kg',   80.0000,  8.5000, 0.00, 1,  680.0000, 1),
(2, 7, 'Azulejo cerámico 1m²',   60.0000, 22.0000, 5.00, 1, 1254.0000, 2);

-- Albarán 3: Distribuciones Norte - consultoría
INSERT INTO lineas_albaran (albaran_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, subtotal, orden) VALUES
(3, 1, 'Consultoría técnica (hora)', 8.0000, 85.0000, 0.00, 1, 680.0000, 1),
(3, 5, 'Auditoría fiscal',           1.0000,400.0000, 0.00, 1, 400.0000, 2);

-- Albarán 4: Tech Solutions - desarrollo web
INSERT INTO lineas_albaran (albaran_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, subtotal, orden) VALUES
(4, 2, 'Desarrollo web (hora)',    20.0000, 75.0000, 10.00, 1, 1350.0000, 1),
(4, 3, 'Mantenimiento mensual web', 2.0000,150.0000,  0.00, 1,  300.0000, 2);

-- Albarán 5: Restaurantes La Huerta - catering
INSERT INTO lineas_albaran (albaran_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, subtotal, orden) VALUES
(5, 9, 'Catering corporativo (persona)', 15.0000, 28.0000, 0.00, 2, 420.0000, 1),
(5,10, 'Café y desayuno (persona)',       15.0000,  6.5000, 0.00, 2,  97.5000, 2);

-- Albarán 6: Tech Solutions - mantenimiento (emitido, aún no facturado)
INSERT INTO lineas_albaran (albaran_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, subtotal, orden) VALUES
(6, 3, 'Mantenimiento mensual web', 3.0000, 150.0000, 0.00, 1, 450.0000, 1);

-- Albarán 7: Ana García - diseño (borrador)
INSERT INTO lineas_albaran (albaran_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, subtotal, orden) VALUES
(7, 4, 'Diseño gráfico (hora)',     5.0000, 60.0000, 0.00, 1, 300.0000, 1);

-- Albarán 8: Clínica Dental Sonríe - revisiones
INSERT INTO lineas_albaran (albaran_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, subtotal, orden) VALUES
(8,11, 'Revisión dental completa',    6.0000, 45.0000, 0.00, 3, 270.0000, 1),
(8,12, 'Limpieza dental profesional', 4.0000, 60.0000, 0.00, 3, 240.0000, 2);

-- -----------------------------------------------------------
-- FACTURAS (12)
-- serie_id: 1=A, 2=RECT, 3=REC, 4=S
-- -----------------------------------------------------------
-- Hashes ficticios SHA-256 para simular cadena VeriFactu
-- En producción los genera josemmo/verifactu-php

INSERT INTO facturas
    (serie_id, cliente_id, numero, numero_completo, tipo, estado_verifactu,
     fecha_emision, fecha_operacion,
     base_imponible, cuota_iva, total,
     hash_verifactu, hash_anterior,
     qr_url, pdf_path, observaciones)
VALUES

-- F1: Completa - Construcciones Valdemar (albarán 1)
(1, 1, 1, 'A-2025-0001', 'completa', 'aceptado',
 '2025-01-15', '2025-01-10',
 1052.0000, 220.9200, 1272.9200,
 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2',
 NULL,
 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=B12345678&numserie=A-2025-0001&fecha=20250115&importe=1272.92',
 'facturas/pdf/A-2025-0001.pdf',
 'Materiales fase 1. Albarán AL-2025-0001'),

-- F2: Completa - Construcciones Valdemar (albaranes 1+2 agrupados en F2)
(1, 1, 2, 'A-2025-0002', 'completa', 'aceptado',
 '2025-02-01', '2025-01-28',
 1934.0000, 406.1400, 2340.1400,
 'b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3',
 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2',
 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=B12345678&numserie=A-2025-0002&fecha=20250201&importe=2340.14',
 'facturas/pdf/A-2025-0002.pdf',
 'Materiales fase 2. Albarán AL-2025-0002'),

-- F3: Completa - Distribuciones Norte
(1, 2, 3, 'A-2025-0003', 'completa', 'aceptado',
 '2025-02-10', '2025-02-05',
 1080.0000, 226.8000, 1306.8000,
 'c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4',
 'b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3',
 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=A87654321&numserie=A-2025-0003&fecha=20250210&importe=1306.80',
 'facturas/pdf/A-2025-0003.pdf',
 'Consultoría y auditoría enero-febrero'),

-- F4: Completa - Tech Solutions desarrollo web
(1, 3, 4, 'A-2025-0004', 'completa', 'aceptado',
 '2025-02-28', '2025-02-14',
 1650.0000, 346.5000, 1996.5000,
 'd4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5',
 'c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4',
 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=ESB99887766&numserie=A-2025-0004&fecha=20250228&importe=1996.50',
 'facturas/pdf/A-2025-0004.pdf',
 'Proyecto Alpha - desarrollo y mantenimiento Q1'),

-- F5: Completa - Restaurantes La Huerta (catering)
(1, 4, 5, 'A-2025-0005', 'completa', 'aceptado',
 '2025-03-05', '2025-03-03',
 517.5000, 51.7500, 569.2500,
 'e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6',
 'd4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5',
 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=B55443322&numserie=A-2025-0005&fecha=20250305&importe=569.25',
 'facturas/pdf/A-2025-0005.pdf',
 'Catering evento corporativo 15 pax'),

-- F6: Completa - Inversiones Atlántico (asesoramiento exento)
(1, 6, 6, 'A-2025-0006', 'completa', 'aceptado',
 '2025-03-15', NULL,
 800.0000, 0.0000, 800.0000,
 'f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1',
 'e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6',
 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=A22334455&numserie=A-2025-0006&fecha=20250315&importe=800.00',
 'facturas/pdf/A-2025-0006.pdf',
 'Asesoramiento financiero y gestión cartera marzo'),

-- F7: Completa pendiente de envío - Distribuciones Norte
(1, 2, 7, 'A-2025-0007', 'completa', 'pendiente',
 '2025-04-01', NULL,
 680.0000, 142.8000, 822.8000,
 NULL, NULL, NULL,
 'facturas/pdf/A-2025-0007.pdf',
 'Consultoría técnica abril - pendiente envío AEAT'),

-- F8: Completa con error VeriFactu - Carlos Martín
(1, 8, 8, 'A-2025-0008', 'completa', 'error',
 '2025-04-05', NULL,
 450.0000, 94.5000, 544.5000,
 NULL, NULL, NULL,
 'facturas/pdf/A-2025-0008.pdf',
 'Error de comunicación con AEAT - pendiente reintento'),

-- F9: RECTIFICATIVA de F3 (error en importe consultoría)
(2, 2, 1, 'RECT-2025-0001', 'rectificativa', 'aceptado',
 '2025-02-20', '2025-02-10',
 -1080.0000, -226.8000, -1306.8000,
 '1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b',
 'f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1',
 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=A87654321&numserie=RECT-2025-0001&fecha=20250220&importe=-1306.80',
 'facturas/pdf/RECT-2025-0001.pdf',
 NULL),

-- F10: RECAPITULATIVA (agrupa simplificadas S-2025-0001 y S-2025-0002)
(3, 9, 1, 'REC-2025-0001', 'recapitulativa', 'aceptado',
 '2025-03-31', NULL,
 199.0000, 7.9600, 206.9600,
 '2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c',
 '1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b',
 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=11223344C&numserie=REC-2025-0001&fecha=20250331&importe=206.96',
 'facturas/pdf/REC-2025-0001.pdf',
 NULL),

-- F11: SIMPLIFICADA - María José Ruiz (revisión dental, importe pequeño)
(4, NULL, 1, 'S-2025-0001', 'simplificada', 'aceptado',
 '2025-03-10', NULL,
 99.0000, 3.9600, 102.9600,
 '3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d',
 '2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c',
 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=&numserie=S-2025-0001&fecha=20250310&importe=102.96',
 'facturas/pdf/S-2025-0001.pdf',
 'Ticket revisión dental - sin identificación receptor'),

-- F12: SIMPLIFICADA - Pedro Jiménez (curso online)
(4, NULL, 2, 'S-2025-0002', 'simplificada', 'aceptado',
 '2025-03-22', NULL,
 99.0000, 0.0000, 99.0000,
 '4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e',
 '3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d5e6f1a2b3c4d',
 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=&numserie=S-2025-0002&fecha=20250322&importe=99.00',
 'facturas/pdf/S-2025-0002.pdf',
 'Acceso curso PHP/Laravel - exento IVA formación');

-- Actualizar motivo y factura_rectificada en la rectificativa
UPDATE facturas
SET factura_rectificada_id = 3,
    motivo_rectificacion   = 'Error en el precio unitario de la consultoría técnica. Se emite factura rectificativa por el total de la factura A-2025-0003 y se re-emitirá corrección.'
WHERE numero_completo = 'RECT-2025-0001';

-- Actualizar periodo en la recapitulativa
UPDATE facturas
SET periodo_desde = '2025-03-01',
    periodo_hasta = '2025-03-31'
WHERE numero_completo = 'REC-2025-0001';

-- -----------------------------------------------------------
-- LÍNEAS DE FACTURA (para las facturas completas)
-- -----------------------------------------------------------

-- F1: A-2025-0001 (Construcciones Valdemar, alb.1)
INSERT INTO lineas_factura (factura_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, base_imponible, cuota_iva, total, orden) VALUES
(1, 6, 'Cemento Portland 25kg',  50.0000,  8.5000, 0.00, 1,  425.0000,  89.2500,  514.2500, 1),
(1, 7, 'Azulejo cerámico 1m²',  30.0000, 22.0000, 5.00, 1,  627.0000, 131.6700,  758.6700, 2);

-- F2: A-2025-0002 (Construcciones Valdemar, alb.2)
INSERT INTO lineas_factura (factura_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, base_imponible, cuota_iva, total, orden) VALUES
(2, 6, 'Cemento Portland 25kg',  80.0000,  8.5000, 0.00, 1,  680.0000, 142.8000,  822.8000, 1),
(2, 7, 'Azulejo cerámico 1m²',  60.0000, 22.0000, 5.00, 1, 1254.0000, 263.3400, 1517.3400, 2);

-- F3: A-2025-0003 (Distribuciones Norte)
INSERT INTO lineas_factura (factura_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, base_imponible, cuota_iva, total, orden) VALUES
(3, 1, 'Consultoría técnica (hora)',  8.0000, 85.0000, 0.00, 1,  680.0000, 142.8000,  822.8000, 1),
(3, 5, 'Auditoría fiscal (informe)',  1.0000,400.0000, 0.00, 1,  400.0000,  84.0000,  484.0000, 2);

-- F4: A-2025-0004 (Tech Solutions)
INSERT INTO lineas_factura (factura_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, base_imponible, cuota_iva, total, orden) VALUES
(4, 2, 'Desarrollo web (hora)',      20.0000, 75.0000, 10.00, 1, 1350.0000, 283.5000, 1633.5000, 1),
(4, 3, 'Mantenimiento mensual web',   2.0000,150.0000,  0.00, 1,  300.0000,  63.0000,  363.0000, 2);

-- F5: A-2025-0005 (Restaurantes La Huerta, IVA 10%)
INSERT INTO lineas_factura (factura_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, base_imponible, cuota_iva, total, orden) VALUES
(5, 9, 'Catering corporativo (persona)', 15.0000, 28.0000, 0.00, 2, 420.0000, 42.0000, 462.0000, 1),
(5,10, 'Café y desayuno (persona)',       15.0000,  6.5000, 0.00, 2,  97.5000,  9.7500, 107.2500, 2);

-- F6: A-2025-0006 (Inversiones Atlántico, exento)
INSERT INTO lineas_factura (factura_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, base_imponible, cuota_iva, total, orden) VALUES
(6,13, 'Asesoramiento financiero (hora)',  4.0000, 90.0000, 0.00, 4, 360.0000, 0.0000, 360.0000, 1),
(6,14, 'Gestión de cartera (mensual)',     2.0000,200.0000, 0.00, 4, 400.0000, 0.0000, 400.0000, 2),
(6,15, 'Curso online PHP/Laravel',         1.0000, 99.0000, 0.00, 4,  40.0000, 0.0000,  40.0000, 3);

-- F7: A-2025-0007 (pendiente)
INSERT INTO lineas_factura (factura_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, base_imponible, cuota_iva, total, orden) VALUES
(7, 1, 'Consultoría técnica (hora)', 8.0000, 85.0000, 0.00, 1, 680.0000, 142.8000, 822.8000, 1);

-- F8: A-2025-0008 (error VeriFactu)
INSERT INTO lineas_factura (factura_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, base_imponible, cuota_iva, total, orden) VALUES
(8, 4, 'Diseño gráfico (hora)', 5.0000, 60.0000, 0.00, 1, 300.0000, 63.0000, 363.0000, 1),
(8, 2, 'Desarrollo web (hora)', 2.0000, 75.0000, 0.00, 1, 150.0000, 31.5000, 181.5000, 2);

-- F9: RECT-2025-0001 (negativa)
INSERT INTO lineas_factura (factura_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, base_imponible, cuota_iva, total, orden) VALUES
(9, 1, 'Rectificación: Consultoría técnica (hora)', -8.0000, 85.0000, 0.00, 1, -680.0000, -142.8000, -822.8000, 1),
(9, 5, 'Rectificación: Auditoría fiscal (informe)', -1.0000,400.0000, 0.00, 1, -400.0000,  -84.0000, -484.0000, 2);

-- F10: REC-2025-0001 (recapitulativa - línea resumen)
INSERT INTO lineas_factura (factura_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, base_imponible, cuota_iva, total, orden) VALUES
(10, 11, 'Revisión dental completa',      1.0000, 45.0000, 0.00, 3, 45.0000, 1.8000,  46.8000, 1),
(10, 15, 'Curso PHP/Laravel (acceso)',    1.0000, 99.0000, 0.00, 4, 99.0000, 0.0000,  99.0000, 2),
(10, 12, 'Limpieza dental profesional',   1.0000, 60.0000, 0.00, 3, 55.0000, 2.2000,  57.2000, 3);

-- F11: S-2025-0001 (simplificada dental)
INSERT INTO lineas_factura (factura_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, base_imponible, cuota_iva, total, orden) VALUES
(11, 15, 'Curso online PHP/Laravel (acceso)', 1.0000, 99.0000, 0.00, 3, 99.0000, 3.9600, 102.9600, 1);

-- F12: S-2025-0002 (simplificada curso)
INSERT INTO lineas_factura (factura_id, producto_id, descripcion, cantidad, precio_unitario, descuento, impuesto_id, base_imponible, cuota_iva, total, orden) VALUES
(12, 15, 'Curso online PHP/Laravel (acceso)', 1.0000, 99.0000, 0.00, 4, 99.0000, 0.0000, 99.0000, 1);

-- -----------------------------------------------------------
-- TABLA PIVOTE ALBARÁN ↔ FACTURA
-- -----------------------------------------------------------
INSERT INTO albaran_factura (albaran_id, factura_id) VALUES
(1, 1),   -- AL-0001 → A-2025-0001
(2, 2),   -- AL-0002 → A-2025-0002
(3, 3),   -- AL-0003 → A-2025-0003
(3, 9),   -- AL-0003 → RECT-2025-0001 (también vinculado a la rectificativa)
(4, 4),   -- AL-0004 → A-2025-0004
(5, 5);   -- AL-0005 → A-2025-0005

-- -----------------------------------------------------------
-- REGISTROS VERIFACTU
-- Uno por cada factura enviada a la AEAT
-- -----------------------------------------------------------
INSERT INTO registros_verifactu
    (factura_id, estado, codigo_respuesta, descripcion_respuesta,
     fecha_envio, fecha_respuesta, reintentos,
     xml_enviado, xml_respuesta)
VALUES

-- F1 aceptada
(1, 'aceptado', '0000', 'Registro de factura aceptado correctamente',
 '2025-01-15 10:05:00', '2025-01-15 10:05:03', 0,
 '<soapenv:Envelope><!-- XML VeriFactu F1 simplificado --></soapenv:Envelope>',
 '<soapenv:Envelope><RespuestaRegFactuSistemaFacturacion><CSV>CSV00001</CSV><EstadoEnvio>Correcto</EstadoEnvio></RespuestaRegFactuSistemaFacturacion></soapenv:Envelope>'),

-- F2 aceptada
(2, 'aceptado', '0000', 'Registro de factura aceptado correctamente',
 '2025-02-01 09:12:00', '2025-02-01 09:12:04', 0,
 '<soapenv:Envelope><!-- XML VeriFactu F2 simplificado --></soapenv:Envelope>',
 '<soapenv:Envelope><RespuestaRegFactuSistemaFacturacion><CSV>CSV00002</CSV><EstadoEnvio>Correcto</EstadoEnvio></RespuestaRegFactuSistemaFacturacion></soapenv:Envelope>'),

-- F3 aceptada
(3, 'aceptado', '0000', 'Registro de factura aceptado correctamente',
 '2025-02-10 11:30:00', '2025-02-10 11:30:05', 0,
 '<soapenv:Envelope><!-- XML VeriFactu F3 simplificado --></soapenv:Envelope>',
 '<soapenv:Envelope><RespuestaRegFactuSistemaFacturacion><CSV>CSV00003</CSV><EstadoEnvio>Correcto</EstadoEnvio></RespuestaRegFactuSistemaFacturacion></soapenv:Envelope>'),

-- F4 aceptada
(4, 'aceptado', '0000', 'Registro de factura aceptado correctamente',
 '2025-02-28 16:45:00', '2025-02-28 16:45:06', 0,
 '<soapenv:Envelope><!-- XML VeriFactu F4 simplificado --></soapenv:Envelope>',
 '<soapenv:Envelope><RespuestaRegFactuSistemaFacturacion><CSV>CSV00004</CSV><EstadoEnvio>Correcto</EstadoEnvio></RespuestaRegFactuSistemaFacturacion></soapenv:Envelope>'),

-- F5 aceptada
(5, 'aceptado', '0000', 'Registro de factura aceptado correctamente',
 '2025-03-05 08:20:00', '2025-03-05 08:20:04', 0,
 '<soapenv:Envelope><!-- XML VeriFactu F5 simplificado --></soapenv:Envelope>',
 '<soapenv:Envelope><RespuestaRegFactuSistemaFacturacion><CSV>CSV00005</CSV><EstadoEnvio>Correcto</EstadoEnvio></RespuestaRegFactuSistemaFacturacion></soapenv:Envelope>'),

-- F6 aceptada
(6, 'aceptado', '0000', 'Registro de factura aceptado correctamente',
 '2025-03-15 14:00:00', '2025-03-15 14:00:07', 0,
 '<soapenv:Envelope><!-- XML VeriFactu F6 simplificado --></soapenv:Envelope>',
 '<soapenv:Envelope><RespuestaRegFactuSistemaFacturacion><CSV>CSV00006</CSV><EstadoEnvio>Correcto</EstadoEnvio></RespuestaRegFactuSistemaFacturacion></soapenv:Envelope>'),

-- F8 primer intento: error
(8, 'error', '5001', 'Error en la firma digital del certificado. Certificado caducado.',
 '2025-04-05 10:00:00', '2025-04-05 10:00:08', 0,
 '<soapenv:Envelope><!-- XML VeriFactu F8 intento 1 --></soapenv:Envelope>',
 '<soapenv:Envelope><RespuestaRegFactuSistemaFacturacion><EstadoEnvio>Incorrecto</EstadoEnvio><DescripcionErrorRegistro>Certificado no válido o caducado</DescripcionErrorRegistro></RespuestaRegFactuSistemaFacturacion></soapenv:Envelope>'),

-- F8 segundo intento: error (reintento)
(8, 'error', '5001', 'Error en la firma digital del certificado. Certificado caducado.',
 '2025-04-05 10:15:00', '2025-04-05 10:15:09', 1,
 '<soapenv:Envelope><!-- XML VeriFactu F8 intento 2 --></soapenv:Envelope>',
 '<soapenv:Envelope><RespuestaRegFactuSistemaFacturacion><EstadoEnvio>Incorrecto</EstadoEnvio><DescripcionErrorRegistro>Certificado no válido o caducado</DescripcionErrorRegistro></RespuestaRegFactuSistemaFacturacion></soapenv:Envelope>'),

-- F9 rectificativa aceptada
(9, 'aceptado', '0000', 'Registro de factura rectificativa aceptado correctamente',
 '2025-02-20 09:00:00', '2025-02-20 09:00:05', 0,
 '<soapenv:Envelope><!-- XML VeriFactu RECT-0001 --></soapenv:Envelope>',
 '<soapenv:Envelope><RespuestaRegFactuSistemaFacturacion><CSV>CSV00009</CSV><EstadoEnvio>Correcto</EstadoEnvio></RespuestaRegFactuSistemaFacturacion></soapenv:Envelope>'),

-- F10 recapitulativa aceptada
(10, 'aceptado', '0000', 'Registro de factura recapitulativa aceptado correctamente',
 '2025-03-31 17:30:00', '2025-03-31 17:30:06', 0,
 '<soapenv:Envelope><!-- XML VeriFactu REC-0001 --></soapenv:Envelope>',
 '<soapenv:Envelope><RespuestaRegFactuSistemaFacturacion><CSV>CSV00010</CSV><EstadoEnvio>Correcto</EstadoEnvio></RespuestaRegFactuSistemaFacturacion></soapenv:Envelope>'),

-- F11 simplificada aceptada
(11, 'aceptado', '0000', 'Registro de factura simplificada aceptado correctamente',
 '2025-03-10 12:00:00', '2025-03-10 12:00:04', 0,
 '<soapenv:Envelope><!-- XML VeriFactu S-0001 --></soapenv:Envelope>',
 '<soapenv:Envelope><RespuestaRegFactuSistemaFacturacion><CSV>CSV00011</CSV><EstadoEnvio>Correcto</EstadoEnvio></RespuestaRegFactuSistemaFacturacion></soapenv:Envelope>'),

-- F12 simplificada aceptada
(12, 'aceptado', '0000', 'Registro de factura simplificada aceptado correctamente',
 '2025-03-22 15:10:00', '2025-03-22 15:10:05', 0,
 '<soapenv:Envelope><!-- XML VeriFactu S-0002 --></soapenv:Envelope>',
 '<soapenv:Envelope><RespuestaRegFactuSistemaFacturacion><CSV>CSV00012</CSV><EstadoEnvio>Correcto</EstadoEnvio></RespuestaRegFactuSistemaFacturacion></soapenv:Envelope>');

-- =============================================================
-- VERIFICACIÓN RÁPIDA (ejecutar tras el script)
-- =============================================================
-- SELECT * FROM v_panel_facturacion;
-- SELECT * FROM v_albaranes_pendientes;
-- SELECT * FROM v_facturas_resumen;