-- =========================================================
-- SysTec v1.2 — Schema base (mejorado)
-- SCHEMA_VERSION: 2026-01-03-02
-- Ubicación: /_cores/systec/v1.2/_db/schema.sql
-- Nota: No incluye CREATE DATABASE / USE
-- =========================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `schema_version` VARCHAR(60) NOT NULL,
  `schema_hash` CHAR(64) NOT NULL,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `applied_by` VARCHAR(60) NULL DEFAULT NULL,
  `notes` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schema_version_hash` (`schema_version`, `schema_hash`),
  KEY `idx_schema_version` (`schema_version`),
  KEY `idx_schema_hash` (`schema_hash`),
  KEY `idx_schema_applied_at` (`applied_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(180) NOT NULL,
  `usuario` VARCHAR(60) NOT NULL,
  `email` VARCHAR(180) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `rol` VARCHAR(30) NOT NULL DEFAULT 'RECEPCION',
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `is_super_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_usuario` (`usuario`),
  UNIQUE KEY `uq_usuarios_email` (`email`),
  KEY `idx_usuarios_rol` (`rol`),
  KEY `idx_usuarios_activo` (`activo`),
  KEY `idx_usuarios_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clientes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(180) NOT NULL,
  `rut` VARCHAR(20) NULL DEFAULT NULL,
  `telefono` VARCHAR(40) NULL DEFAULT NULL,
  `email` VARCHAR(180) NULL DEFAULT NULL,
  `direccion` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clientes_rut` (`rut`),
  KEY `idx_clientes_nombre` (`nombre`),
  KEY `idx_clientes_rut` (`rut`),
  KEY `idx_clientes_email` (`email`),
  KEY `idx_clientes_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `equipos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cliente_id` INT UNSIGNED NULL DEFAULT NULL,
  `tipo` VARCHAR(60) NULL DEFAULT NULL,
  `marca` VARCHAR(60) NULL DEFAULT NULL,
  `modelo` VARCHAR(80) NULL DEFAULT NULL,
  `serie` VARCHAR(80) NULL DEFAULT NULL,
  `imei` VARCHAR(30) NULL DEFAULT NULL,
  `color` VARCHAR(40) NULL DEFAULT NULL,
  `notas` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_equipos_cliente` (`cliente_id`),
  KEY `idx_equipos_imei` (`imei`),
  KEY `idx_equipos_serie` (`serie`),
  KEY `idx_equipos_marca_modelo` (`marca`, `modelo`),
  CONSTRAINT `fk_equipos_cliente`
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ordenes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cliente_id` INT UNSIGNED NULL DEFAULT NULL,
  `equipo_id` INT UNSIGNED NULL DEFAULT NULL,

  `estado_actual` VARCHAR(30) NOT NULL DEFAULT 'INGRESADA',
  `prioridad` VARCHAR(15) NOT NULL DEFAULT 'NORMAL',

  `fecha_ingreso` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_entrega_estimada` DATETIME NULL DEFAULT NULL,
  `fecha_entrega_real` DATETIME NULL DEFAULT NULL,

  `detalle_falla` TEXT NULL,
  `diagnostico` TEXT NULL,
  `solucion` TEXT NULL,
  `observaciones` TEXT NULL,

  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `abono` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `saldo` DECIMAL(12,2) NOT NULL DEFAULT 0.00,

  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `updated_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL,

  PRIMARY KEY (`id`),
  KEY `idx_ordenes_estado_actual` (`estado_actual`),
  KEY `idx_ordenes_prioridad` (`prioridad`),
  KEY `idx_ordenes_fecha_ingreso` (`fecha_ingreso`),
  KEY `idx_ordenes_cliente` (`cliente_id`),
  KEY `idx_ordenes_equipo` (`equipo_id`),
  KEY `idx_ordenes_created_at` (`created_at`),
  KEY `idx_ordenes_cliente_fecha` (`cliente_id`, `fecha_ingreso`),

  CONSTRAINT `fk_ordenes_cliente`
    FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,

  CONSTRAINT `fk_ordenes_equipo`
    FOREIGN KEY (`equipo_id`) REFERENCES `equipos`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,

  CONSTRAINT `fk_ordenes_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `usuarios`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,

  CONSTRAINT `fk_ordenes_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `usuarios`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ordenes_eventos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `orden_id` INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NULL DEFAULT NULL,
  `evento` VARCHAR(60) NOT NULL,
  `detalle` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_eventos_orden` (`orden_id`),
  KEY `idx_eventos_usuario` (`usuario_id`),
  KEY `idx_eventos_created_at` (`created_at`),
  CONSTRAINT `fk_eventos_orden`
    FOREIGN KEY (`orden_id`) REFERENCES `ordenes`(`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_eventos_usuario`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `evidencias` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `orden_id` INT UNSIGNED NOT NULL,
  `tipo` VARCHAR(30) NOT NULL DEFAULT 'IMG',
  `ruta` VARCHAR(255) NOT NULL,
  `descripcion` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_evidencias_orden` (`orden_id`),
  KEY `idx_evidencias_tipo` (`tipo`),
  KEY `idx_evidencias_created_at` (`created_at`),
  CONSTRAINT `fk_evidencias_orden`
    FOREIGN KEY (`orden_id`) REFERENCES `ordenes`(`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `firmas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `orden_id` INT UNSIGNED NOT NULL,
  `ruta` VARCHAR(255) NOT NULL,
  `nombre` VARCHAR(180) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_firmas_orden` (`orden_id`),
  KEY `idx_firmas_created_at` (`created_at`),
  CONSTRAINT `fk_firmas_orden`
    FOREIGN KEY (`orden_id`) REFERENCES `ordenes`(`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `parametros` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clave` VARCHAR(80) NOT NULL,
  `valor` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parametros_clave` (`clave`),
  KEY `idx_parametros_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compat dashboard: lo pide como tabla/vista
CREATE OR REPLACE VIEW `ordenes_evidencias` AS
SELECT `id`,`orden_id`,`tipo`,`ruta`,`descripcion`,`created_at`
FROM `evidencias`;

SET FOREIGN_KEY_CHECKS = 1;
