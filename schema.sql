CREATE TABLE IF NOT EXISTS sellout_credits (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sku VARCHAR(100) NOT NULL,
  producto VARCHAR(255) NOT NULL,
  monto_iva DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NOT NULL,
  proveedor VARCHAR(255) NOT NULL,
  reportada TINYINT(1) NOT NULL DEFAULT 0,
  sell_out_pago TINYINT(1) NOT NULL DEFAULT 0,
  notas TEXT,
  PRIMARY KEY (id),
  INDEX idx_sku (sku),
  INDEX idx_proveedor (proveedor),
  INDEX idx_fecha_fin (fecha_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
