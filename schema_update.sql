-- Evidencia de cobro
ALTER TABLE sellout_credits
  ADD COLUMN comprobante_file VARCHAR(255) NULL AFTER notas;
