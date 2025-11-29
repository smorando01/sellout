CREATE TABLE IF NOT EXISTS sellout_credits (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sku VARCHAR(100) NOT NULL,
  producto VARCHAR(255) NOT NULL,
  monto_iva DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  moneda ENUM('UYU','USD') NOT NULL DEFAULT 'UYU',
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NOT NULL,
  proveedor VARCHAR(255) NOT NULL,
  reportada TINYINT(1) NOT NULL DEFAULT 0,
  sell_out_pago TINYINT(1) NOT NULL DEFAULT 0,
  notas TEXT,
  PRIMARY KEY (id),
  INDEX idx_sku (sku),
  INDEX idx_proveedor (proveedor),
  INDEX idx_fecha_fin (fecha_fin),
  INDEX idx_moneda (moneda)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migración si ya tienes la tabla sin columna moneda:
-- ALTER TABLE sellout_credits ADD COLUMN moneda ENUM('UYU','USD') NOT NULL DEFAULT 'UYU' AFTER monto_iva;
-- UPDATE sellout_credits SET moneda = 'UYU' WHERE moneda = 'ARS' OR moneda IS NULL;

-- Catálogo de proveedores
CREATE TABLE IF NOT EXISTS catalog_proveedores (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(255) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_proveedores_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catálogo de SKUs
CREATE TABLE IF NOT EXISTS catalog_skus (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sku VARCHAR(100) NOT NULL,
  producto VARCHAR(255) NOT NULL,
  proveedor VARCHAR(255) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_skus_sku (sku),
  KEY idx_catalog_skus_proveedor (proveedor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
