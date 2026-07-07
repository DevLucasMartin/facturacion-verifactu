-- ============================================================================
--  Sistema de Gestión de Facturas — ESQUEMA LIMPIO PARA CLIENTE
--  Igual que database.sql pero SIN datos de prueba.
--  Conserva solo los catálogos imprescindibles para que la app arranque:
--    · Tipos_IVA · Tipos_Clientes · Formas_Pago · Canales (series A y S)
--    · Familias (GEN) · un usuario admin
--  Verifactu_Registros queda VACÍA (la cadena de huellas la inicia el cliente).
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `verifactu`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `verifactu`;

SET FOREIGN_KEY_CHECKS = 0;

-- Limpieza previa (orden inverso a dependencias)
DROP TABLE IF EXISTS `Verifactu_Registros`;
DROP TABLE IF EXISTS `Facturas_Sustituidas`;
DROP TABLE IF EXISTS `Pagos_Facturas`;
DROP TABLE IF EXISTS `Lineas_Facturas_Clientes`;
DROP TABLE IF EXISTS `Albaran_Factura`;
DROP TABLE IF EXISTS `Lineas_Albaranes_Clientes`;
DROP TABLE IF EXISTS `Facturas_Clientes`;
DROP TABLE IF EXISTS `Albaranes_Clientes`;
DROP TABLE IF EXISTS `Articulos`;
DROP TABLE IF EXISTS `Familias`;
DROP TABLE IF EXISTS `Telefonos_Clientes`;
DROP TABLE IF EXISTS `Direcciones_Clientes`;
DROP TABLE IF EXISTS `Clientes`;
DROP TABLE IF EXISTS `Canales`;
DROP TABLE IF EXISTS `Formas_Pago`;
DROP TABLE IF EXISTS `Tipos_IVA`;
DROP TABLE IF EXISTS `Paises`;
DROP TABLE IF EXISTS `Tipos_Clientes`;
DROP TABLE IF EXISTS `Usuarios`;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
-- 1. TIPOS DE IVA  (catálogo — se conserva)
-- =============================================================
CREATE TABLE `Tipos_IVA` (
    `Codigo`                  VARCHAR(10)   NOT NULL,
    `Descripcion`             VARCHAR(100)  NOT NULL,
    `IVA`                     DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    `RE`                      DECIMAL(5,2)  NOT NULL DEFAULT 0.00  COMMENT 'Recargo de Equivalencia',
    `Cuenta_IVA_Soportado`    VARCHAR(10)   NULL,
    `Cuenta_IVA_Repercutido`  VARCHAR(10)   NULL,
    `Cuenta_RE_Soportado`     VARCHAR(10)   NULL,
    `Cuenta_RE_Repercutido`   VARCHAR(10)   NULL,
    `Tipo_Territorio`         VARCHAR(20)   NULL  COMMENT 'PENINSULA, CANARIAS, CEUTA_MELILLA',
    `Activo`                  CHAR(1)       NOT NULL DEFAULT 'S',
    `Orden`                   SMALLINT      NOT NULL DEFAULT 0,
    `Codigo_Verifactu`        VARCHAR(10)   NULL,
    `Actualizado`             TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (`Codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tipos impositivos de IVA';

INSERT INTO `Tipos_IVA` (`Codigo`, `Descripcion`, `IVA`, `RE`, `Tipo_Territorio`, `Activo`, `Orden`, `Codigo_Verifactu`, `Actualizado`) VALUES
    -- Península e Islas Baleares (IVA)
    ('01',     'IVA General 21%',         21.00, 5.20, 'PENINSULAR',    'S',  1, 'S1', 1),
    ('02',     'IVA Reducido 10%',        10.00, 1.40, 'PENINSULAR',    'S',  2, 'S1', 1),
    ('03',     'IVA Superreducido 4%',     4.00, 0.50, 'PENINSULAR',    'S',  3, 'S1', 1),
    ('04',     'Exento (IVA)',             0.00, 0.00, 'PENINSULAR',    'S',  4, 'E1', 1),
    -- Islas Canarias (IGIC)
    ('IGIC0',  'IGIC Tipo Cero 0%',       0.00, 0.00, 'CANARIAS',      'S', 10, 'S1', 1),
    ('IGIC3',  'IGIC Reducido 3%',        3.00, 0.00, 'CANARIAS',      'S', 11, 'S1', 1),
    ('IGIC7',  'IGIC General 7%',         7.00, 0.00, 'CANARIAS',      'S', 12, 'S1', 1),
    ('IGIC95', 'IGIC Incrementado 9.5%',  9.50, 0.00, 'CANARIAS',      'S', 13, 'S1', 1),
    ('IGIC15', 'IGIC Especial 15%',      15.00, 0.00, 'CANARIAS',      'S', 14, 'S1', 1),
    ('IGICEX', 'Exento (IGIC)',           0.00, 0.00, 'CANARIAS',      'S', 15, 'E1', 1),
    -- Ceuta y Melilla (IPSI)
    ('IPSI0',  'IPSI Exento 0%',          0.00, 0.00, 'CEUTA_MELILLA', 'S', 20, 'E1', 1),
    ('IPSI05', 'IPSI 0.5%',              0.50, 0.00, 'CEUTA_MELILLA', 'S', 21, 'S1', 1),
    ('IPSI1',  'IPSI 1%',                 1.00, 0.00, 'CEUTA_MELILLA', 'S', 22, 'S1', 1),
    ('IPSI2',  'IPSI 2%',                 2.00, 0.00, 'CEUTA_MELILLA', 'S', 23, 'S1', 1),
    ('IPSI4',  'IPSI 4%',                 4.00, 0.00, 'CEUTA_MELILLA', 'S', 24, 'S1', 1),
    ('IPSI10', 'IPSI 10%',               10.00, 0.00, 'CEUTA_MELILLA', 'S', 25, 'S1', 1);

-- =============================================================
-- 2. PAÍSES
-- =============================================================
CREATE TABLE `Paises` (
    `Codigo`  CHAR(2)      NOT NULL,
    `Nombre`  VARCHAR(100) NOT NULL,
    PRIMARY KEY (`Codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 3. TIPOS DE CLIENTES  (catálogo — se conserva)
-- =============================================================
CREATE TABLE `Tipos_Clientes` (
    `Codigo`  VARCHAR(20) NOT NULL,
    PRIMARY KEY (`Codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `Tipos_Clientes` VALUES ('empresa'), ('persona'), ('autonomo');

-- =============================================================
-- 4. FORMAS DE PAGO  (catálogo — se conserva)
-- =============================================================
CREATE TABLE `Formas_Pago` (
    `Codigo`       VARCHAR(10)  NOT NULL,
    `Descripcion`  VARCHAR(100) NOT NULL,
    `Activo`       CHAR(1)      NOT NULL DEFAULT 'S',
    PRIMARY KEY (`Codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Formas de cobro/pago';

INSERT INTO `Formas_Pago` VALUES
    ('EFE', 'Efectivo',       'S'),
    ('TRF', 'Transferencia',  'S'),
    ('TAR', 'Tarjeta',        'S'),
    ('CHQ', 'Cheque',         'S'),
    ('DOM', 'Domiciliación',  'S');

-- =============================================================
-- 5. CANALES / SERIES DE FACTURACIÓN
--    Solo las series genéricas base (A = completas, S = simplificadas).
--    Las series propias de cada cliente se crean desde la aplicación.
-- =============================================================
CREATE TABLE `Canales` (
    `Codigo`                   VARCHAR(10)   NOT NULL,
    `Descripcion`              VARCHAR(100)  NOT NULL,
    `Color`                    INT           NULL,
    `Id_Cliente_Facturacion`   VARCHAR(30)   NULL  COMMENT 'Cliente genérico para simplificadas',
    `Direccion_Facturacion`    VARCHAR(100)  NULL,
    `Facturacion_Defecto`      CHAR(1)       NOT NULL DEFAULT 'N',
    `Ticket`                   CHAR(1)       NOT NULL DEFAULT 'N'  COMMENT 'S = emite tickets/simplificadas',
    `Porcentaje_Antieconomico` INT           NULL,
    `Prioridad`                INT           NULL,
    `Tipo_Prioridad`           VARCHAR(20)   NULL,
    `Departamento`             VARCHAR(50)   NULL,
    `Cobrar_Franquicia`        TINYINT(1)    NOT NULL DEFAULT 0,
    `Computable`               TINYINT(1)    NOT NULL DEFAULT 0,
    `Activo`                   CHAR(1)       NOT NULL DEFAULT 'S',
    PRIMARY KEY (`Codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Series de numeración / canales de venta';

INSERT INTO `Canales` (`Codigo`, `Descripcion`, `Id_Cliente_Facturacion`, `Facturacion_Defecto`, `Ticket`) VALUES
    ('A', 'Facturas completas',      NULL, 'S', 'N'),
    ('S', 'Simplificadas / Tickets', NULL, 'N', 'S');

-- =============================================================
-- 6. CLIENTES  (VACÍA)
-- =============================================================
CREATE TABLE `Clientes` (
    `Codigo`                      VARCHAR(30)  NOT NULL,
    `NIF`                         VARCHAR(20)  NOT NULL,
    `Nombre`                      VARCHAR(150) NOT NULL  COMMENT 'Nombre o razón social',
    `Apellidos`                   VARCHAR(100) NULL,
    `Id_Tipo_IVA`                 VARCHAR(10)  NULL,
    `RE_Porcentaje`               DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `Aplica_RE`                   CHAR(1)      NOT NULL DEFAULT 'N',
    `Id_Forma_Pago`               VARCHAR(10)  NULL,
    `Id_Zona`                     VARCHAR(10)  NULL,
    `Tarifa`                      TINYINT      NOT NULL DEFAULT 1,
    `Email_Facturacion`           VARCHAR(120) NULL,
    `Descuento_Especial`          DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `Descuento_PP`                DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `Descuento_Comercial`         DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `Activo`                      CHAR(1)      NOT NULL DEFAULT 'S',
    `Fecha_Alta`                  DATETIME     NULL,
    `Usuario_Alta`                VARCHAR(50)  NULL,
    `Ultima_Modificacion`         DATETIME     NULL,
    `Usuario_Ultima_Modificacion` VARCHAR(50)  NULL,
    PRIMARY KEY (`Codigo`),
    UNIQUE KEY `uq_clientes_nif`    (`NIF`),
    INDEX      `idx_clientes_nombre` (`Nombre`),
    CONSTRAINT `fk_cli_tipo_iva`   FOREIGN KEY (`Id_Tipo_IVA`)  REFERENCES `Tipos_IVA`  (`Codigo`) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_cli_forma_pago` FOREIGN KEY (`Id_Forma_Pago`) REFERENCES `Formas_Pago` (`Codigo`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Clientes / destinatarios de facturas';

-- =============================================================
-- 7. DIRECCIONES DE CLIENTES  (VACÍA)
-- =============================================================
CREATE TABLE `Direcciones_Clientes` (
    `Id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `Id_Cliente`           VARCHAR(30)     NOT NULL,
    `Direccion`            VARCHAR(200)    NULL,
    `Ciudad`               VARCHAR(80)     NULL,
    `Codigo_Postal`        VARCHAR(10)     NULL,
    `Pais`                 CHAR(2)         NOT NULL DEFAULT 'ES',
    `Correo_Electronico`   VARCHAR(120)    NULL,
    `Telefono`             VARCHAR(20)     NULL,
    `Predeterminada`       CHAR(1)         NOT NULL DEFAULT 'N',
    PRIMARY KEY (`Id`),
    INDEX `idx_dir_cliente` (`Id_Cliente`),
    CONSTRAINT `fk_dir_cliente` FOREIGN KEY (`Id_Cliente`)
        REFERENCES `Clientes` (`Codigo`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Direcciones postales de clientes';

-- =============================================================
-- 8. TELÉFONOS DE CLIENTES  (VACÍA)
-- =============================================================
CREATE TABLE `Telefonos_Clientes` (
    `Id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `Id_Cliente`  VARCHAR(30)     NOT NULL,
    `Telefono`    VARCHAR(20)     NOT NULL,
    `Tipo`        VARCHAR(20)     NULL  COMMENT 'movil, fijo, fax',
    PRIMARY KEY (`Id`),
    INDEX `idx_tel_cliente` (`Id_Cliente`),
    CONSTRAINT `fk_tel_cliente` FOREIGN KEY (`Id_Cliente`)
        REFERENCES `Clientes` (`Codigo`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Teléfonos de contacto de clientes';

-- =============================================================
-- 9. FAMILIAS DE ARTÍCULOS  (solo la base GEN)
-- =============================================================
CREATE TABLE `Familias` (
    `Codigo`               VARCHAR(10)  NOT NULL,
    `Descripcion`          VARCHAR(100) NOT NULL,
    `Tarifa_1_Porcentaje`  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `Tarifa_2_Porcentaje`  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `Tarifa_3_Porcentaje`  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `Tarifa_4_Porcentaje`  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `Tarifa_5_Porcentaje`  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `Tarifa_6_Porcentaje`  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `Tarifa_7_Porcentaje`  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `Tarifa_8_Porcentaje`  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`Codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Familias / grupos de artículos con descuentos por tarifa';

INSERT INTO `Familias` VALUES ('GEN', 'General', 0, 0, 0, 0, 0, 0, 0, 0);

-- =============================================================
-- 10. ARTÍCULOS / PRODUCTOS  (VACÍA)
-- =============================================================
CREATE TABLE `Articulos` (
    `Codigo`          VARCHAR(30)   NOT NULL,
    `Descripcion`     VARCHAR(150)  NOT NULL,
    `Modelo`          VARCHAR(60)   NULL,
    `Codigo_Barras`   VARCHAR(30)   NULL,
    `Id_Tipo_IVA`     VARCHAR(10)   NOT NULL,
    `Id_Familia`      VARCHAR(10)   NULL,
    `Id_Marca`        VARCHAR(10)   NULL,
    `Stock_Minimo`    DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `Precio_Venta_1`  DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `Precio_Venta_2`  DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `Precio_Venta_3`  DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `Precio_Venta_4`  DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `Precio_Venta_5`  DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `Precio_Venta_6`  DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `Precio_Venta_7`  DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `Precio_Venta_8`  DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `Descuento`       DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    `Activo`          CHAR(1)       NOT NULL DEFAULT 'S',
    PRIMARY KEY (`Codigo`),
    INDEX `idx_art_barras`  (`Codigo_Barras`),
    INDEX `idx_art_familia` (`Id_Familia`),
    CONSTRAINT `fk_art_tipo_iva` FOREIGN KEY (`Id_Tipo_IVA`)
        REFERENCES `Tipos_IVA` (`Codigo`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_art_familia` FOREIGN KEY (`Id_Familia`)
        REFERENCES `Familias` (`Codigo`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de artículos y servicios';

-- =============================================================
-- 11. ALBARANES DE CLIENTES  (VACÍA)
-- =============================================================
CREATE TABLE `Albaranes_Clientes` (
    `Codigo`                      VARCHAR(30)   NOT NULL,
    `Id_Canal`                    VARCHAR(10)   NOT NULL,
    `Numero`                      INT UNSIGNED  NOT NULL,
    `Fecha`                       DATE          NOT NULL,
    `Id_Cliente`                  VARCHAR(30)   NOT NULL,
    `Id_Forma_Pago`               VARCHAR(10)   NULL,
    `Observaciones`               TEXT          NULL,
    `Descuento_Especial`          DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    `Descuento_PP`                DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    `Descuento_Comercial`         DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    `Importe_Bruto`               DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `Importe_Dto_Especial`        DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `Importe_Dto_PP`              DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `Importe_Dto_Comercial`       DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `Base_Imponible`              DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `Importe_IVA`                 DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `Total`                       DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `Cerrado`                     CHAR(1)       NOT NULL DEFAULT 'N',
    `Facturado`                   CHAR(1)       NOT NULL DEFAULT 'N',
    `Cobrado`                     CHAR(1)       NOT NULL DEFAULT 'N',
    `Impreso`                     CHAR(1)       NOT NULL DEFAULT 'N',
    `Numero_Lineas`               SMALLINT      NOT NULL DEFAULT 0,
    `Fecha_Cierre`                DATE          NULL,
    `Fecha_Cobro`                 DATE          NULL,
    `Direccion`                   VARCHAR(200)  NULL,
    `Es_Plantilla`                CHAR(1)       NOT NULL DEFAULT 'N',
    `Nombre_Plantilla`            VARCHAR(100)  NULL,
    `Fecha_Alta`                  DATETIME      NULL,
    `Usuario_Alta`                VARCHAR(50)   NULL,
    `Ultima_Modificacion`         DATETIME      NULL,
    `Usuario_Ultima_Modificacion` VARCHAR(50)   NULL,
    PRIMARY KEY (`Codigo`),
    INDEX `idx_alb_cliente`   (`Id_Cliente`),
    INDEX `idx_alb_canal`     (`Id_Canal`),
    INDEX `idx_alb_cerrado`   (`Cerrado`),
    INDEX `idx_alb_facturado` (`Facturado`),
    CONSTRAINT `fk_alb_cliente`    FOREIGN KEY (`Id_Cliente`)   REFERENCES `Clientes`    (`Codigo`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_alb_canal`      FOREIGN KEY (`Id_Canal`)     REFERENCES `Canales`     (`Codigo`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_alb_forma_pago` FOREIGN KEY (`Id_Forma_Pago`) REFERENCES `Formas_Pago` (`Codigo`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Albaranes de clientes';

-- =============================================================
-- 12. LÍNEAS DE ALBARÁN  (VACÍA)
-- =============================================================
CREATE TABLE `Lineas_Albaranes_Clientes` (
    `Id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `Id_Albaran`     VARCHAR(30)     NOT NULL,
    `Linea`          SMALLINT        NOT NULL DEFAULT 1,
    `Id_Articulo`    VARCHAR(30)     NULL,
    `Descripcion`    VARCHAR(255)    NOT NULL,
    `Cantidad`       DECIMAL(12,4)   NOT NULL DEFAULT 1.0000,
    `Precio`         DECIMAL(12,4)   NOT NULL DEFAULT 0.0000,
    `Descuento`      DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    `Id_Tipo_IVA`    VARCHAR(10)     NOT NULL,
    `Importe_Bruto`  DECIMAL(14,4)   NOT NULL DEFAULT 0.0000,
    `Base_Imponible` DECIMAL(14,4)   NOT NULL DEFAULT 0.0000,
    `RE`             DECIMAL(14,4)   NOT NULL DEFAULT 0.0000,
    `Aplica_RE`      CHAR(1)         NOT NULL DEFAULT 'N',
    `Total`          DECIMAL(14,4)   NOT NULL DEFAULT 0.0000,
    `Facturada`      CHAR(1)         NOT NULL DEFAULT 'N',
    PRIMARY KEY (`Id`),
    INDEX `idx_la_albaran`  (`Id_Albaran`),
    INDEX `idx_la_articulo` (`Id_Articulo`),
    CONSTRAINT `fk_la_albaran`  FOREIGN KEY (`Id_Albaran`)  REFERENCES `Albaranes_Clientes` (`Codigo`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_la_articulo` FOREIGN KEY (`Id_Articulo`) REFERENCES `Articulos`           (`Codigo`) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_la_tipo_iva` FOREIGN KEY (`Id_Tipo_IVA`) REFERENCES `Tipos_IVA`           (`Codigo`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Líneas de detalle de albaranes';

-- =============================================================
-- 13. FACTURAS DE CLIENTES  (VACÍA)
-- =============================================================
CREATE TABLE `Facturas_Clientes` (
    `Codigo`                      VARCHAR(30)   NOT NULL,
    `Id_Canal`                    VARCHAR(10)   NOT NULL,
    `Numero`                      INT UNSIGNED  NOT NULL,
    `Fecha`                       DATE          NOT NULL,
    `Id_Cliente`                  VARCHAR(30)   NULL  COMMENT 'NULL permitido en simplificada',
    `Id_Forma_Pago`               VARCHAR(10)   NULL,
    `Tipo_Documento`              VARCHAR(20)   NOT NULL DEFAULT 'COMPLETA'
                                  COMMENT 'COMPLETA, SIMPLIFICADA, RECTIFICATIVA, RECAPITULATIVA',
    `Observaciones`               TEXT          NULL,
    `Descuento_Especial`          DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    `Descuento_PP`                DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    `Descuento_Comercial`         DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    `Importe_Bruto`               DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `Importe_Dto_Especial`        DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `Importe_Dto_PP`              DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `Base_Imponible`              DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `Cuota_IVA`                   DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `Total`                       DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `Importe_Cobrado`             DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `Cerrada`                     CHAR(1)       NOT NULL DEFAULT 'N',
    `Cobrada`                     CHAR(1)       NOT NULL DEFAULT 'N',
    `Abono`                       CHAR(1)       NOT NULL DEFAULT 'N',
    `Recapitulada`                CHAR(1)       NOT NULL DEFAULT 'N',
    `Fecha_Cierre`                DATE          NULL,
    `Fecha_Cobro`                 DATE          NULL,
    `Fecha_Operacion`             DATE          NULL,
    `Periodo_Desde`               DATE          NULL  COMMENT 'Solo en recapitulativa',
    `Periodo_Hasta`               DATE          NULL  COMMENT 'Solo en recapitulativa',
    `Factura_Rectificada_Id`      VARCHAR(30)   NULL  COMMENT 'Solo en rectificativa',
    `Motivo_Rectificacion`        TEXT          NULL,
    `Direccion`                   VARCHAR(200)  NULL,
    `Hash_Verifactu`              CHAR(64)      NULL  COMMENT 'SHA-256 de este registro',
    `Hash_Anterior`               CHAR(64)      NULL  COMMENT 'SHA-256 del registro anterior',
    `Xml_Verifactu`               LONGTEXT      NULL,
    `Qr_Url`                      VARCHAR(500)  NULL,
    `Pdf_Path`                    VARCHAR(300)  NULL,
    `Fecha_Alta`                  DATETIME      NULL,
    `Usuario_Alta`                VARCHAR(50)   NULL,
    `Ultima_Modificacion`         DATETIME      NULL,
    `Usuario_Ultima_Modificacion` VARCHAR(50)   NULL,
    PRIMARY KEY (`Codigo`),
    INDEX `idx_fact_cliente`  (`Id_Cliente`),
    INDEX `idx_fact_canal`    (`Id_Canal`),
    INDEX `idx_fact_tipo`     (`Tipo_Documento`),
    INDEX `idx_fact_cerrada`  (`Cerrada`),
    INDEX `idx_fact_rectif`   (`Factura_Rectificada_Id`),
    INDEX `idx_fact_fecha`    (`Fecha`),
    CONSTRAINT `fk_fact_cliente`     FOREIGN KEY (`Id_Cliente`)   REFERENCES `Clientes`    (`Codigo`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_fact_canal`       FOREIGN KEY (`Id_Canal`)     REFERENCES `Canales`     (`Codigo`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_fact_forma_pago`  FOREIGN KEY (`Id_Forma_Pago`) REFERENCES `Formas_Pago` (`Codigo`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Facturas de clientes';

-- =============================================================
-- 14. HISTORIAL DE PAGOS DE FACTURAS  (VACÍA)
-- =============================================================
CREATE TABLE `Pagos_Facturas` (
    `Id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `Id_Factura` VARCHAR(30)   NOT NULL,
    `Importe`    DECIMAL(14,4) NOT NULL,
    `Fecha`      DATE          NOT NULL,
    `Fecha_Alta` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`Id`),
    KEY `idx_pagos_factura` (`Id_Factura`),
    CONSTRAINT `fk_pago_factura` FOREIGN KEY (`Id_Factura`)
        REFERENCES `Facturas_Clientes` (`Codigo`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historial de cobros parciales y totales de facturas';

-- =============================================================
-- 16. LÍNEAS DE FACTURA  (VACÍA)
-- =============================================================
CREATE TABLE `Lineas_Facturas_Clientes` (
    `Id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `Id_Factura`       VARCHAR(30)     NOT NULL,
    `Linea`            SMALLINT        NOT NULL DEFAULT 1,
    `Id_Articulo`      VARCHAR(30)     NULL,
    `Descripcion`      VARCHAR(255)    NOT NULL,
    `Cantidad`         DECIMAL(12,4)   NOT NULL DEFAULT 1.0000,
    `Precio`           DECIMAL(12,4)   NOT NULL DEFAULT 0.0000,
    `Descuento`        DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    `Id_Tipo_IVA`      VARCHAR(10)     NOT NULL,
    `Importe_Bruto`    DECIMAL(14,4)   NOT NULL DEFAULT 0.0000,
    `Importe_Descuento` DECIMAL(14,4)  NOT NULL DEFAULT 0.0000,
    `Base_Imponible`   DECIMAL(14,4)   NOT NULL DEFAULT 0.0000,
    `Cuota_IVA`        DECIMAL(14,4)   NOT NULL DEFAULT 0.0000,
    `RE`               DECIMAL(14,4)   NOT NULL DEFAULT 0.0000,
    `Aplica_RE`        CHAR(1)         NOT NULL DEFAULT 'N',
    `Total`            DECIMAL(14,4)   NOT NULL DEFAULT 0.0000,
    `Id_Canal`         VARCHAR(10)     NULL,
    `Id_Ticket`        VARCHAR(30)     NULL,
    `Id_Almacen`       VARCHAR(10)     NULL,
    `Calificacion`     VARCHAR(10)     NULL,
    `Clave_Regimen`    VARCHAR(10)     NULL,
    PRIMARY KEY (`Id`),
    INDEX `idx_lf_factura`  (`Id_Factura`),
    INDEX `idx_lf_articulo` (`Id_Articulo`),
    CONSTRAINT `fk_lf_factura`  FOREIGN KEY (`Id_Factura`)  REFERENCES `Facturas_Clientes` (`Codigo`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_lf_articulo` FOREIGN KEY (`Id_Articulo`) REFERENCES `Articulos`          (`Codigo`) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_lf_tipo_iva` FOREIGN KEY (`Id_Tipo_IVA`) REFERENCES `Tipos_IVA`          (`Codigo`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Líneas de detalle de facturas';

-- =============================================================
-- 17. RELACIÓN ALBARÁN <-> FACTURA  (VACÍA)
-- =============================================================
CREATE TABLE `Albaran_Factura` (
    `Id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `Id_Albaran`  VARCHAR(30)     NOT NULL,
    `Id_Factura`  VARCHAR(30)     NOT NULL,
    `created_at`  TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`Id`),
    UNIQUE KEY `uq_af` (`Id_Albaran`, `Id_Factura`),
    INDEX `idx_af_factura` (`Id_Factura`),
    CONSTRAINT `fk_af_albaran` FOREIGN KEY (`Id_Albaran`) REFERENCES `Albaranes_Clientes` (`Codigo`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_af_factura` FOREIGN KEY (`Id_Factura`) REFERENCES `Facturas_Clientes`  (`Codigo`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Relación N:M entre albaranes y facturas';

-- =============================================================
-- 18. FACTURAS SUSTITUIDAS (para recapitulativas F3)  (VACÍA)
-- =============================================================
CREATE TABLE `Facturas_Sustituidas` (
    `Id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `Id_Recapitulativa` VARCHAR(30)     NOT NULL,
    `Id_Simplificada`   VARCHAR(30)     NOT NULL,
    PRIMARY KEY (`Id`),
    UNIQUE KEY `uq_fs` (`Id_Recapitulativa`, `Id_Simplificada`),
    INDEX `idx_fs_recapitulativa` (`Id_Recapitulativa`),
    CONSTRAINT `fk_fs_recapitulativa` FOREIGN KEY (`Id_Recapitulativa`) REFERENCES `Facturas_Clientes` (`Codigo`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_fs_simplificada`   FOREIGN KEY (`Id_Simplificada`)   REFERENCES `Facturas_Clientes` (`Codigo`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Facturas simplificadas sustituidas por una recapitulativa';

-- =============================================================
-- 19. REGISTROS VERIFACTU  (VACÍA — crítico: la cadena la inicia el cliente)
-- =============================================================
CREATE TABLE `Verifactu_Registros` (
    `Id`                  BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `Tipo_Origen`         VARCHAR(20)      NOT NULL DEFAULT 'FACTURA',
    `Id_Documento`        VARCHAR(30)      NOT NULL,
    `Estado_Envio`        VARCHAR(20)      NOT NULL DEFAULT 'PENDIENTE'
                          COMMENT 'PENDIENTE, GENERADO, ENVIADO, ERROR, ANULADO',
    `Fecha_Generacion`    DATETIME         NULL,
    `Fecha_Envio`         DATETIME         NULL,
    `Reintentos`          TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `Huella_Actual`       VARCHAR(64)      NULL,
    `Huella_Anterior`     VARCHAR(64)      NULL,
    `Codigo_Seguridad`    VARCHAR(16)      NULL     COMMENT 'Primeros 16 chars de la huella (QR)',
    `Cadena_Firma`        TEXT             NULL     COMMENT 'Cadena de firma tal como la spec AEAT',
    `Xml_Enviado`         LONGTEXT         NULL     COMMENT 'Payload XML firmado enviado a AEAT',
    `Respuesta_Hacienda`  TEXT             NULL     COMMENT 'Respuesta XML de la AEAT',
    `CSV_Hacienda`        VARCHAR(40)      NULL     COMMENT 'CSV asignado por la AEAT',
    `Ultimo_Error`        VARCHAR(500)     NULL,
    `URL_Verificacion`    VARCHAR(500)     NULL,
    `Anulado`             CHAR(1)          NOT NULL DEFAULT 'N',
    `Fecha_Anulacion`     DATETIME         NULL,
    `Motivo_Anulacion`    VARCHAR(500)     NULL,
    PRIMARY KEY (`Id`),
    INDEX `idx_vr_documento` (`Id_Documento`),
    INDEX `idx_vr_estado`    (`Estado_Envio`),
    INDEX `idx_vr_tipo`      (`Tipo_Origen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historial de envíos VeriFactu a la AEAT';

-- =============================================================
-- VISTAS
-- =============================================================

CREATE OR REPLACE VIEW `v_panel_facturacion` AS
SELECT
    COUNT(*)                                                       AS total_facturas,
    SUM(CASE WHEN vr.Estado_Envio = 'ENVIADO'   THEN 1 ELSE 0 END) AS enviadas_ok,
    SUM(CASE WHEN vr.Estado_Envio = 'PENDIENTE'
              OR  vr.Id IS NULL                 THEN 1 ELSE 0 END) AS pendientes,
    SUM(CASE WHEN vr.Estado_Envio = 'ERROR'     THEN 1 ELSE 0 END) AS con_error,
    SUM(f.Tipo_Documento = 'COMPLETA')                             AS completas,
    SUM(f.Tipo_Documento = 'SIMPLIFICADA')                         AS simplificadas,
    SUM(f.Tipo_Documento = 'RECTIFICATIVA')                        AS rectificativas,
    SUM(f.Tipo_Documento = 'RECAPITULATIVA')                       AS recapitulativas,
    SUM(f.Total)                                                   AS importe_total,
    MIN(f.Fecha)                                                   AS primera_factura,
    MAX(f.Fecha)                                                   AS ultima_factura
FROM `Facturas_Clientes` f
LEFT JOIN `Verifactu_Registros` vr ON vr.Id_Documento = f.Codigo
    AND vr.Tipo_Origen = 'FACTURA';

CREATE OR REPLACE VIEW `v_albaranes_pendientes` AS
SELECT
    a.`Codigo`,
    a.`Numero`,
    a.`Fecha`,
    c.`Nombre`  AS cliente,
    c.`NIF`,
    a.`Total`
FROM `Albaranes_Clientes` a
JOIN `Clientes` c ON c.`Codigo` = a.`Id_Cliente`
WHERE a.`Facturado` = 'N';

CREATE OR REPLACE VIEW `v_facturas_resumen` AS
SELECT
    f.`Codigo`,
    f.`Tipo_Documento`,
    f.`Fecha`,
    c.`Nombre`  AS cliente,
    c.`NIF`,
    f.`Base_Imponible`,
    f.`Cuota_IVA`,
    f.`Total`,
    vr.`Estado_Envio`  AS estado_verifactu,
    vr.`Fecha_Envio`,
    vr.`CSV_Hacienda`,
    vr.`Reintentos`
FROM `Facturas_Clientes` f
LEFT JOIN `Clientes` c ON c.`Codigo` = f.`Id_Cliente`
LEFT JOIN `Verifactu_Registros` vr ON vr.`Id` = (
    SELECT `Id` FROM `Verifactu_Registros`
    WHERE `Id_Documento` = f.`Codigo`
    ORDER BY `Fecha_Generacion` DESC LIMIT 1
);

-- =============================================================
-- USUARIOS  (un único usuario admin — CAMBIAR la contraseña tras la entrega)
-- =============================================================
CREATE TABLE `Usuarios` (
    `Usuario`    VARCHAR(50)  NOT NULL,
    `Contrasena` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`Usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `Usuarios` (`Usuario`, `Contrasena`)
VALUES ('admin', SHA2('Admin1', 256));
