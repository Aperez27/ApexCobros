-- =========================================================
--  APEX · Migración v1.3: orden personalizado de la ruta de cobro
--  Segura de ejecutar varias veces; no borra datos.
-- =========================================================
USE apex_cobros_db;

-- Posición del cliente dentro de la ruta de su cobrador (NULL = al final, en orden de creación)
ALTER TABLE clientes
    ADD COLUMN IF NOT EXISTS orden_ruta INT UNSIGNED NULL COMMENT 'Posición en la ruta del cobrador' AFTER cobrador_id;

CREATE INDEX IF NOT EXISTS idx_clientes_orden_ruta ON clientes (cobrador_id, orden_ruta);
