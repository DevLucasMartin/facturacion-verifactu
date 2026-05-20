CREATE DATABASE IF NOT EXISTS `verifactu`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `verifactu`;

SET FOREIGN_KEY_CHECKS = 0;

-- Limpieza previa (orden inverso a dependencias)
DROP TABLE IF EXISTS `Verifactu_Registros`;
DROP TABLE IF EXISTS `Facturas_Sustituidas`;
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

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================
-- 1. TIPOS DE IVA
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

INSERT INTO `Paises` VALUES
    ('ES', 'España'), ('DE', 'Alemania'), ('FR', 'Francia'),
    ('IT', 'Italia'), ('PT', 'Portugal'), ('GB', 'Reino Unido');

-- =============================================================
-- 3. TIPOS DE CLIENTES
-- =============================================================
CREATE TABLE `Tipos_Clientes` (
    `Codigo`  VARCHAR(20) NOT NULL,
    PRIMARY KEY (`Codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `Tipos_Clientes` VALUES ('empresa'), ('persona'), ('autonomo');

-- =============================================================
-- 4. FORMAS DE PAGO
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
    ('TEKN',  'Teknia Group S.A.',     NULL, 'S', 'N'),
    ('INDRA', 'Indra Sistemas S.A.',   NULL, 'N', 'N'),
    ('CAP',   'Capgemini España S.L.', NULL, 'N', 'N'),
    ('ACCI',  'Accenture Spain S.L.',  NULL, 'N', 'N'),
    ('IBER',  'Ibermática S.A.',       NULL, 'N', 'N'),
    ('EVER',  'Everis Spain S.L.',     NULL, 'N', 'N'),
    ('A',     'Facturas completas',    NULL, 'N', 'N'),
    ('S',     'Simplificadas / Tickets', NULL, 'N', 'S'),
    ('RECT',  'Rectificativas',        NULL, 'N', 'N'),
    ('REC',   'Recapitulativas',       NULL, 'N', 'N');

-- =============================================================
-- 6. CLIENTES
-- =============================================================
CREATE TABLE `Clientes` (
    `Codigo`                      VARCHAR(30)  NOT NULL,
    `NIF`                         VARCHAR(20)  NOT NULL,
    `Archivar_Como`               VARCHAR(150) NOT NULL  COMMENT 'Nombre o razón social',
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
    INDEX      `idx_clientes_nombre` (`Archivar_Como`),
    CONSTRAINT `fk_cli_tipo_iva`   FOREIGN KEY (`Id_Tipo_IVA`)  REFERENCES `Tipos_IVA`  (`Codigo`) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_cli_forma_pago` FOREIGN KEY (`Id_Forma_Pago`) REFERENCES `Formas_Pago` (`Codigo`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Clientes / destinatarios de facturas';

-- =============================================================
-- 7. DIRECCIONES DE CLIENTES
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
-- 8. TELÉFONOS DE CLIENTES
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
-- 9. FAMILIAS DE ARTÍCULOS
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
-- 10. ARTÍCULOS / PRODUCTOS
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
-- 11. ALBARANES DE CLIENTES
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
-- 12. LÍNEAS DE ALBARÁN
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
-- 13. FACTURAS DE CLIENTES
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
-- 14. LÍNEAS DE FACTURA
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
-- 15. RELACIÓN ALBARÁN ↔ FACTURA
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
-- 16. FACTURAS SUSTITUIDAS (para recapitulativas F3)
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
-- 17. REGISTROS VERIFACTU
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
    c.`Archivar_Como`  AS cliente,
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
    c.`Archivar_Como`  AS cliente,
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
-- DATOS DE PRUEBA
-- =============================================================

INSERT INTO `Clientes` (`Codigo`, `NIF`, `Archivar_Como`, `Id_Tipo_IVA`, `Id_Forma_Pago`, `Tarifa`, `Activo`, `Fecha_Alta`, `Usuario_Alta`) VALUES
    ('CLI001', 'B12345674', 'Construcciones Valdemar S.L.', '01', 'TRF', 1, 'S', NOW(), 'sistema'),
    ('CLI002', 'A87654323', 'Servicios Digitales Norte S.A.', '01', 'TAR', 1, 'S', NOW(), 'sistema'),
    ('CLI003', '12345678Z', 'García López, Juan', '01', 'EFE', 1, 'S', NOW(), 'sistema'),
    ('CLI004', 'B99887762', 'Distribuciones Sur S.L.', '02', 'DOM', 2, 'S', NOW(), 'sistema');

INSERT INTO `Articulos` (`Codigo`, `Descripcion`, `Id_Tipo_IVA`, `Id_Familia`, `Precio_Venta_1`, `Precio_Venta_2`, `Activo`) VALUES
    ('ART001', 'Servicio de consultoría hora', '01', 'GEN', 75.0000, 70.0000, 'S'),
    ('ART002', 'Licencia software anual',      '01', 'GEN', 299.0000, 279.0000, 'S'),
    ('ART003', 'Soporte técnico mensual',      '01', 'GEN', 150.0000, 140.0000, 'S'),
    ('ART004', 'Material de oficina',          '02', 'GEN', 25.0000, 22.0000, 'S');

-- -----------------------------------------------------------
-- Familias adicionales
-- -----------------------------------------------------------
INSERT INTO `Familias` (`Codigo`, `Descripcion`, `Tarifa_1_Porcentaje`, `Tarifa_2_Porcentaje`) VALUES
    ('SERV', 'Servicios Profesionales', 0.00, 5.00),
    ('SOFT', 'Software y Licencias',    0.00, 5.00),
    ('HARD', 'Hardware y Equipos',      0.00, 3.00);

-- -----------------------------------------------------------
-- Artículos adicionales (ART005-ART012)
-- -----------------------------------------------------------
INSERT INTO `Articulos`
    (`Codigo`, `Descripcion`, `Modelo`, `Id_Tipo_IVA`, `Id_Familia`,
     `Precio_Venta_1`, `Precio_Venta_2`, `Precio_Venta_3`, `Descuento`, `Activo`)
VALUES
    ('ART005', 'Auditoría de sistemas',          NULL,    '01', 'SERV',  450.0000,  420.0000,  400.0000, 0.00, 'S'),
    ('ART006', 'Formación presencial (día)',      NULL,    '01', 'SERV',  600.0000,  550.0000,  500.0000, 0.00, 'S'),
    ('ART007', 'Suite ofimática - Licencia',     'PRO',   '01', 'SOFT',  180.0000,  165.0000,  150.0000, 0.00, 'S'),
    ('ART008', 'Antivirus empresarial anual',    NULL,    '01', 'SOFT',   49.0000,   45.0000,   40.0000, 0.00, 'S'),
    ('ART009', 'Ordenador portátil 15"',         'LAP15', '01', 'HARD',  899.0000,  849.0000,  799.0000, 5.00, 'S'),
    ('ART010', 'Monitor 27" Full HD',            'MON27', '01', 'HARD',  249.0000,  229.0000,  209.0000, 3.00, 'S'),
    ('ART011', 'Impresora láser multifunción',   'IMP-MF','01', 'HARD',  349.0000,  319.0000,  299.0000, 0.00, 'S'),
    ('ART012', 'Papel A4 500h (caja 5 resmas)',  NULL,    '02', 'GEN',    18.5000,   17.0000,   16.0000, 0.00, 'S');

-- -----------------------------------------------------------
-- Clientes adicionales (CLI005-CLI008)
-- -----------------------------------------------------------
INSERT INTO `Clientes`
    (`Codigo`, `NIF`, `Archivar_Como`, `Apellidos`, `Id_Tipo_IVA`,
     `RE_Porcentaje`, `Aplica_RE`, `Id_Forma_Pago`, `Tarifa`,
     `Email_Facturacion`, `Activo`, `Fecha_Alta`, `Usuario_Alta`)
VALUES
    ('CLI005', 'A34567890', 'Tecnologías Avanzadas S.A.',  NULL,      '01', 0.00, 'N', 'TRF', 2, 'facturacion@tecavanzadas.es',   'S', NOW(), 'sistema'),
    ('CLI006', 'B55443322', 'Logística Express S.L.',      NULL,      '01', 0.00, 'N', 'DOM', 1, 'admin@logexpress.es',            'S', NOW(), 'sistema'),
    ('CLI007', '87654321B', 'Martínez Ruiz',               'Ana',     '01', 5.20, 'S', 'EFE', 1, 'ana.martinez@gmail.com',         'S', NOW(), 'sistema'),
    ('CLI008', '45678901C', 'Fernández García',            'Carlos',  '01', 0.00, 'N', 'TAR', 3, 'carlos.fernandez@outlook.com',   'S', NOW(), 'sistema');

-- -----------------------------------------------------------
-- Direcciones de clientes
-- -----------------------------------------------------------
INSERT INTO `Direcciones_Clientes`
    (`Id_Cliente`, `Direccion`, `Ciudad`, `Codigo_Postal`, `Pais`, `Correo_Electronico`, `Predeterminada`)
VALUES
    ('CLI001', 'Calle Mayor 10, 1ª Planta',       'Madrid',    '28001', 'ES', 'admin@valdemar.es',            'S'),
    ('CLI002', 'Avda. Diagonal 445, 3º B',        'Barcelona', '08036', 'ES', 'info@serviciosdigitales.es',   'S'),
    ('CLI003', 'Calle Ancha 23, 2ª',              'Sevilla',   '41001', 'ES', 'juangarcia@correo.es',         'S'),
    ('CLI004', 'Polígono Ind. Sur, Nave 12',      'Valencia',  '46014', 'ES', 'compras@distribsur.es',        'S'),
    ('CLI005', 'Gran Vía 50, 5ª Planta',          'Madrid',    '28013', 'ES', 'facturacion@tecavanzadas.es',  'S'),
    ('CLI005', 'Calle Serrano 12, oficina 2',     'Madrid',    '28001', 'ES', 'soporte@tecavanzadas.es',      'N'),
    ('CLI006', 'Calle Industria 88, Nave 3',      'Zaragoza',  '50006', 'ES', 'admin@logexpress.es',          'S'),
    ('CLI007', 'Avda. Constitución 5, 3ºA',       'Granada',   '18012', 'ES', 'ana.martinez@gmail.com',       'S'),
    ('CLI008', 'Plaza España 2, 1ºB',             'Málaga',    '29012', 'ES', 'carlos.fernandez@outlook.com', 'S');

-- -----------------------------------------------------------
-- Teléfonos de clientes
-- -----------------------------------------------------------
INSERT INTO `Telefonos_Clientes` (`Id_Cliente`, `Telefono`, `Tipo`) VALUES
    ('CLI001', '916 111 222', 'fijo'),
    ('CLI001', '612 345 678', 'movil'),
    ('CLI002', '932 456 789', 'fijo'),
    ('CLI002', '655 987 654', 'movil'),
    ('CLI003', '954 321 654', 'fijo'),
    ('CLI004', '963 111 000', 'fijo'),
    ('CLI004', '699 888 777', 'movil'),
    ('CLI005', '914 222 333', 'fijo'),
    ('CLI006', '976 543 210', 'fijo'),
    ('CLI007', '671 234 567', 'movil'),
    ('CLI008', '952 111 222', 'fijo');

-- -----------------------------------------------------------
-- Albaranes de clientes (6)
-- Formato Codigo: YYYY + Canal + NNNN  →  LEFT(Codigo,4) = ejercicio
-- -----------------------------------------------------------
INSERT INTO `Albaranes_Clientes`
    (`Codigo`, `Id_Canal`, `Numero`, `Fecha`, `Id_Cliente`, `Id_Forma_Pago`,
     `Importe_Bruto`, `Base_Imponible`, `Total`,
     `Cerrado`, `Facturado`, `Cobrado`, `Fecha_Cierre`,
     `Fecha_Alta`, `Usuario_Alta`, `Ultima_Modificacion`, `Usuario_Ultima_Modificacion`)
VALUES
    -- Cerrados y facturados (vinculados a facturas 2026A0001 y 2026A0002)
    ('2026A0001','A',1,'2026-01-15','CLI001','TRF',  525.0000,  525.0000,  635.2500,'S','S','S','2026-01-20', NOW(),'sistema',NOW(),'sistema'),
    ('2026A0002','A',2,'2026-01-22','CLI002','TAR',  299.0000,  299.0000,  361.7900,'S','S','S','2026-01-25', NOW(),'sistema',NOW(),'sistema'),
    -- Cerrado, pendiente de facturar
    ('2026A0003','A',3,'2026-02-05','CLI003','EFE',  450.0000,  450.0000,  544.5000,'S','N','N','2026-02-10', NOW(),'sistema',NOW(),'sistema'),
    -- Abiertos
    ('2026A0004','A',4,'2026-02-18','CLI001','TRF',  600.0000,  600.0000,  726.0000,'N','N','N', NULL,        NOW(),'sistema',NOW(),'sistema'),
    ('2026A0005','A',5,'2026-03-02','CLI004','DOM',  899.0000,  899.0000, 1087.7900,'N','N','N', NULL,        NOW(),'sistema',NOW(),'sistema'),
    ('2026A0006','A',6,'2026-03-10','CLI006','DOM',  349.0000,  349.0000,  422.2900,'N','N','N', NULL,        NOW(),'sistema',NOW(),'sistema');

-- -----------------------------------------------------------
-- Líneas de albaranes
-- -----------------------------------------------------------
INSERT INTO `Lineas_Albaranes_Clientes`
    (`Id_Albaran`, `Linea`, `Id_Articulo`, `Descripcion`,
     `Cantidad`, `Precio`, `Descuento`, `Id_Tipo_IVA`,
     `Importe_Bruto`, `Base_Imponible`, `RE`, `Aplica_RE`, `Total`)
VALUES
    -- Albarán 2026A0001: consultoría + soporte → base 525, total 635.25
    ('2026A0001',1,'ART001','Servicio de consultoría hora', 5.0000, 75.0000,0.00,'01', 375.0000, 375.0000,0.0000,'N', 453.7500),
    ('2026A0001',2,'ART003','Soporte técnico mensual',      1.0000,150.0000,0.00,'01', 150.0000, 150.0000,0.0000,'N', 181.5000),
    -- Albarán 2026A0002: licencia → base 299, total 361.79
    ('2026A0002',1,'ART002','Licencia software anual',      1.0000,299.0000,0.00,'01', 299.0000, 299.0000,0.0000,'N', 361.7900),
    -- Albarán 2026A0003: auditoría → base 450, total 544.50
    ('2026A0003',1,'ART005','Auditoría de sistemas',        1.0000,450.0000,0.00,'01', 450.0000, 450.0000,0.0000,'N', 544.5000),
    -- Albarán 2026A0004: formación → base 600, total 726
    ('2026A0004',1,'ART006','Formación presencial (día)',   1.0000,600.0000,0.00,'01', 600.0000, 600.0000,0.0000,'N', 726.0000),
    -- Albarán 2026A0005: ordenador → base 899, total 1087.79
    ('2026A0005',1,'ART009','Ordenador portátil 15"',       1.0000,899.0000,0.00,'01', 899.0000, 899.0000,0.0000,'N',1087.7900),
    -- Albarán 2026A0006: impresora → base 349, total 422.29
    ('2026A0006',1,'ART011','Impresora láser multifunción', 1.0000,349.0000,0.00,'01', 349.0000, 349.0000,0.0000,'N', 422.2900);

-- -----------------------------------------------------------
-- Facturas de clientes (8)
-- 4 COMPLETA · 2 SIMPLIFICADA · 1 RECTIFICATIVA · 1 RECAPITULATIVA
-- -----------------------------------------------------------
INSERT INTO `Facturas_Clientes`
    (`Codigo`, `Id_Canal`, `Numero`, `Fecha`, `Id_Cliente`, `Id_Forma_Pago`,
     `Tipo_Documento`, `Importe_Bruto`, `Base_Imponible`, `Cuota_IVA`, `Total`,
     `Cerrada`, `Cobrada`, `Abono`, `Recapitulada`,
     `Factura_Rectificada_Id`, `Motivo_Rectificacion`,
     `Periodo_Desde`, `Periodo_Hasta`,
     `Fecha_Cierre`, `Fecha_Cobro`,
     `Fecha_Alta`, `Usuario_Alta`, `Ultima_Modificacion`, `Usuario_Ultima_Modificacion`)
VALUES
    -- COMPLETA 1: CLI001 - consultoría + soporte - cobrada
    ('2026A0001','A',1,'2026-01-20','CLI001','TRF','COMPLETA',
      525.0000, 525.0000, 110.2500,  635.2500,
      'S','S','N','N', NULL,NULL, NULL,NULL,
      '2026-01-20','2026-01-28', NOW(),'sistema',NOW(),'sistema'),

    -- COMPLETA 2: CLI002 - licencia - cerrada sin cobrar
    ('2026A0002','A',2,'2026-01-25','CLI002','TAR','COMPLETA',
      299.0000, 299.0000,  62.7900,  361.7900,
      'S','N','N','N', NULL,NULL, NULL,NULL,
      '2026-01-25', NULL,        NOW(),'sistema',NOW(),'sistema'),

    -- COMPLETA 3: CLI004 - ordenador - pendiente
    ('2026A0003','A',3,'2026-02-15','CLI004','DOM','COMPLETA',
      899.0000, 899.0000, 188.7900, 1087.7900,
      'N','N','N','N', NULL,NULL, NULL,NULL,
       NULL,NULL,                  NOW(),'sistema',NOW(),'sistema'),

    -- COMPLETA 4: CLI005 - soporte 2 meses - pendiente
    ('2026A0004','A',4,'2026-03-01','CLI005','TRF','COMPLETA',
      300.0000, 300.0000,  63.0000,  363.0000,
      'N','N','N','N', NULL,NULL, NULL,NULL,
       NULL,NULL,                  NOW(),'sistema',NOW(),'sistema'),

    -- SIMPLIFICADA 1: material oficina - cobrada en caja
    ('2026S0001','S',1,'2026-01-10', NULL,'EFE','SIMPLIFICADA',
      100.0000, 100.0000,  10.0000,  110.0000,
      'S','S','N','S', NULL,NULL, NULL,NULL,
      '2026-01-10','2026-01-10', NOW(),'sistema',NOW(),'sistema'),

    -- SIMPLIFICADA 2: antivirus - cobrada en caja
    ('2026S0002','S',2,'2026-01-18', NULL,'EFE','SIMPLIFICADA',
       49.0000,  49.0000,  10.2900,   59.2900,
      'S','S','N','S', NULL,NULL, NULL,NULL,
      '2026-01-18','2026-01-18', NOW(),'sistema',NOW(),'sistema'),

    -- RECTIFICATIVA: anula soporte de 2026A0001
    ('2026R0001','RECT',1,'2026-02-01','CLI001','TRF','RECTIFICATIVA',
     -150.0000,-150.0000, -31.5000, -181.5000,
      'S','S','S','N', '2026A0001','Error en línea de soporte técnico: importe incorrecto',
      NULL,NULL,
      '2026-02-01','2026-02-05', NOW(),'sistema',NOW(),'sistema'),

    -- RECAPITULATIVA: agrupa S0001 + S0002 (enero 2026)
    ('2026C0001','REC',1,'2026-01-31', NULL,'EFE','RECAPITULATIVA',
      149.0000, 149.0000,  20.2900,  169.2900,
      'S','S','N','N', NULL,NULL,
      '2026-01-01','2026-01-31',
      '2026-01-31','2026-01-31', NOW(),'sistema',NOW(),'sistema');

-- -----------------------------------------------------------
-- Líneas de facturas
-- -----------------------------------------------------------
INSERT INTO `Lineas_Facturas_Clientes`
    (`Id_Factura`, `Linea`, `Id_Articulo`, `Descripcion`,
     `Cantidad`, `Precio`, `Descuento`, `Id_Tipo_IVA`,
     `Importe_Bruto`, `Importe_Descuento`, `Base_Imponible`, `Cuota_IVA`, `RE`, `Aplica_RE`, `Total`)
VALUES
    -- 2026A0001: consultoría 5h + soporte 1 mes
    ('2026A0001',1,'ART001','Servicio de consultoría hora', 5.0000, 75.0000,0.00,'01', 375.0000,0.0000, 375.0000, 78.7500,0.0000,'N',  453.7500),
    ('2026A0001',2,'ART003','Soporte técnico mensual',      1.0000,150.0000,0.00,'01', 150.0000,0.0000, 150.0000, 31.5000,0.0000,'N',  181.5000),
    -- 2026A0002: licencia
    ('2026A0002',1,'ART002','Licencia software anual',      1.0000,299.0000,0.00,'01', 299.0000,0.0000, 299.0000, 62.7900,0.0000,'N',  361.7900),
    -- 2026A0003: ordenador
    ('2026A0003',1,'ART009','Ordenador portátil 15"',       1.0000,899.0000,0.00,'01', 899.0000,0.0000, 899.0000,188.7900,0.0000,'N', 1087.7900),
    -- 2026A0004: soporte 2 meses
    ('2026A0004',1,'ART003','Soporte técnico mensual',      2.0000,150.0000,0.00,'01', 300.0000,0.0000, 300.0000, 63.0000,0.0000,'N',  363.0000),
    -- 2026S0001: material oficina (IVA reducido 10%)
    ('2026S0001',1,'ART004','Material de oficina',          4.0000, 25.0000,0.00,'02', 100.0000,0.0000, 100.0000, 10.0000,0.0000,'N',  110.0000),
    -- 2026S0002: antivirus + papel
    ('2026S0002',1,'ART008','Antivirus empresarial anual',  1.0000, 49.0000,0.00,'01',  49.0000,0.0000,  49.0000, 10.2900,0.0000,'N',   59.2900),
    -- 2026R0001: rectificativa (cantidad negativa)
    ('2026R0001',1,'ART003','Soporte técnico mensual (anulación)', -1.0000,150.0000,0.00,'01',-150.0000,0.0000,-150.0000,-31.5000,0.0000,'N',-181.5000),
    -- 2026C0001: recapitulativa (línea resumen)
    ('2026C0001',1, NULL,   'Resumen facturas simplificadas enero 2026', 1.0000,149.0000,0.00,'01', 149.0000,0.0000, 149.0000, 20.2900,0.0000,'N',  169.2900);

-- -----------------------------------------------------------
-- Relación albarán ↔ factura
-- -----------------------------------------------------------
INSERT INTO `Albaran_Factura` (`Id_Albaran`, `Id_Factura`) VALUES
    ('2026A0001', '2026A0001'),
    ('2026A0002', '2026A0002');

-- -----------------------------------------------------------
-- Facturas sustituidas por la recapitulativa
-- -----------------------------------------------------------
INSERT INTO `Facturas_Sustituidas` (`Id_Recapitulativa`, `Id_Simplificada`) VALUES
    ('2026C0001', '2026S0001'),
    ('2026C0001', '2026S0002');

-- -----------------------------------------------------------
-- Registros Verifactu
-- Huellas: 64 caracteres hexadecimales (SHA-256 simulado)
-- CSV: código devuelto por la AEAT tras el envío
-- -----------------------------------------------------------
INSERT INTO `Verifactu_Registros`
    (`Tipo_Origen`, `Id_Documento`, `Estado_Envio`,
     `Fecha_Generacion`, `Fecha_Envio`, `Reintentos`,
     `Huella_Actual`, `Huella_Anterior`,
     `CSV_Hacienda`, `URL_Verificacion`)
VALUES
    -- 2026A0001: ENVIADO (primer registro, sin anterior)
    ('FACTURA','2026A0001','ENVIADO',
     '2026-01-20 09:00:00','2026-01-20 09:02:15', 0,
     'aaaa1111bbbb2222cccc3333dddd4444eeee5555ffff6666aaaa7777bbbb0001',
      NULL,
     'CSVF2026A00010001', 'https://sede.agenciatributaria.gob.es/verifactu/0001'),

    -- 2026A0002: ENVIADO
    ('FACTURA','2026A0002','ENVIADO',
     '2026-01-25 10:15:00','2026-01-25 10:17:30', 0,
     'aaaa1111bbbb2222cccc3333dddd4444eeee5555ffff6666aaaa7777bbbb0002',
     'aaaa1111bbbb2222cccc3333dddd4444eeee5555ffff6666aaaa7777bbbb0001',
     'CSVF2026A00020002', 'https://sede.agenciatributaria.gob.es/verifactu/0002'),

    -- 2026A0003: PENDIENTE (generado, no enviado aún)
    ('FACTURA','2026A0003','PENDIENTE',
     '2026-02-15 08:30:00', NULL, 0,
     'aaaa1111bbbb2222cccc3333dddd4444eeee5555ffff6666aaaa7777bbbb0003',
     'aaaa1111bbbb2222cccc3333dddd4444eeee5555ffff6666aaaa7777bbbb0002',
      NULL, NULL),

    -- 2026A0004: PENDIENTE
    ('FACTURA','2026A0004','PENDIENTE',
     '2026-03-01 11:00:00', NULL, 0,
     'aaaa1111bbbb2222cccc3333dddd4444eeee5555ffff6666aaaa7777bbbb0004',
     'aaaa1111bbbb2222cccc3333dddd4444eeee5555ffff6666aaaa7777bbbb0003',
      NULL, NULL),

    -- 2026S0001: ENVIADO
    ('FACTURA','2026S0001','ENVIADO',
     '2026-01-10 12:00:00','2026-01-10 12:01:45', 0,
     'aaaa1111bbbb2222cccc3333dddd4444eeee5555ffff6666aaaa7777bbbb0005',
     'aaaa1111bbbb2222cccc3333dddd4444eeee5555ffff6666aaaa7777bbbb0004',
     'CSVS2026S00010005', 'https://sede.agenciatributaria.gob.es/verifactu/0005'),

    -- 2026S0002: ENVIADO
    ('FACTURA','2026S0002','ENVIADO',
     '2026-01-18 14:30:00','2026-01-18 14:31:20', 0,
     'aaaa1111bbbb2222cccc3333dddd4444eeee5555ffff6666aaaa7777bbbb0006',
     'aaaa1111bbbb2222cccc3333dddd4444eeee5555ffff6666aaaa7777bbbb0005',
     'CSVS2026S00020006', 'https://sede.agenciatributaria.gob.es/verifactu/0006'),

    -- 2026R0001: ENVIADO (rectificativa)
    ('FACTURA','2026R0001','ENVIADO',
     '2026-02-01 09:45:00','2026-02-01 09:46:55', 0,
     'aaaa1111bbbb2222cccc3333dddd4444eeee5555ffff6666aaaa7777bbbb0007',
     'aaaa1111bbbb2222cccc3333dddd4444eeee5555ffff6666aaaa7777bbbb0006',
     'CSVR2026R00010007', 'https://sede.agenciatributaria.gob.es/verifactu/0007'),

    -- 2026C0001: ENVIADO (recapitulativa, con un reintento previo)
    ('FACTURA','2026C0001','ENVIADO',
     '2026-01-31 16:00:00','2026-01-31 16:05:30', 1,
     'aaaa1111bbbb2222cccc3333dddd4444eeee5555ffff6666aaaa7777bbbb0008',
     'aaaa1111bbbb2222cccc3333dddd4444eeee5555ffff6666aaaa7777bbbb0007',
     'CSVC2026C00010008', 'https://sede.agenciatributaria.gob.es/verifactu/0008'),

    -- 2026A0003: primer intento fallido (ERROR, antes del pendiente actual)
    ('FACTURA','2026A0003','ERROR',
     '2026-02-15 08:00:00', NULL, 1,
     NULL,
     'aaaa1111bbbb2222cccc3333dddd4444eeee5555ffff6666aaaa7777bbbb0002',
      NULL, NULL);
