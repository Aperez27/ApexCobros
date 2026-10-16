-- =========================================================
--  APEX · Migración v1.2: caja del cobrador, gestiones de cobro y renovaciones
--  Segura de ejecutar varias veces; no borra datos.
-- =========================================================
USE apex_cobros_db;

-- Renovación: el saldo del crédito anterior se cancela con un "pago" de tipo renovación
ALTER TABLE pagos MODIFY metodo ENUM('efectivo','nequi','daviplata','transferencia','otro','renovacion') NOT NULL DEFAULT 'efectivo';

ALTER TABLE creditos
    ADD COLUMN IF NOT EXISTS renovado_de    INT UNSIGNED  NULL COMMENT 'Crédito anterior que se refinanció' AFTER cliente_id,
    ADD COLUMN IF NOT EXISTS saldo_renovado DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'Saldo anterior descontado del desembolso' AFTER monto,
    ADD COLUMN IF NOT EXISTS neto_entregado DECIMAL(14,2) NULL COMMENT 'Efectivo realmente entregado al cliente' AFTER saldo_renovado;

-- Gestiones de cobro: qué pasó cuando el cliente no pagó, y promesas de pago
CREATE TABLE IF NOT EXISTS gestiones (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    credito_id     INT UNSIGNED NOT NULL,
    usuario_id     INT UNSIGNED NULL,
    tipo           ENUM('visita','llamada','whatsapp') NOT NULL DEFAULT 'visita',
    resultado      ENUM('no_estaba','sin_dinero','promesa','se_niega','direccion_errada','otro') NOT NULL,
    fecha_promesa  DATE NULL,
    monto_promesa  DECIMAL(14,2) NULL,
    nota           VARCHAR(255) NULL,
    created_at     DATETIME NOT NULL,
    INDEX idx_gestiones_credito (credito_id, created_at),
    INDEX idx_gestiones_promesa (fecha_promesa),
    CONSTRAINT fk_gestiones_credito FOREIGN KEY (credito_id) REFERENCES creditos(id) ON DELETE CASCADE,
    CONSTRAINT fk_gestiones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Gastos del cobrador en la ruta (se descuentan del efectivo a entregar)
CREATE TABLE IF NOT EXISTS gastos (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cobrador_id    INT UNSIGNED NOT NULL,
    fecha          DATE NOT NULL,
    concepto       ENUM('transporte','alimentacion','papeleria','recarga','otro') NOT NULL,
    valor          DECIMAL(14,2) NOT NULL,
    nota           VARCHAR(255) NULL,
    anulado        TINYINT(1) NOT NULL DEFAULT 0,
    registrado_por INT UNSIGNED NULL,
    created_at     DATETIME NOT NULL,
    INDEX idx_gastos_cobrador (cobrador_id, fecha),
    CONSTRAINT fk_gastos_cobrador FOREIGN KEY (cobrador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_gastos_registro FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Cierres de caja: lo que el cobrador entrega físicamente cada día
CREATE TABLE IF NOT EXISTS cierres (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cobrador_id       INT UNSIGNED NOT NULL,
    fecha             DATE NOT NULL,
    recaudo_efectivo  DECIMAL(14,2) NOT NULL,
    recaudo_digital   DECIMAL(14,2) NOT NULL,
    gastos            DECIMAL(14,2) NOT NULL,
    a_entregar        DECIMAL(14,2) NOT NULL,
    entregado         DECIMAL(14,2) NOT NULL,
    diferencia        DECIMAL(14,2) NOT NULL COMMENT 'Negativo = faltante, positivo = sobrante',
    observaciones     VARCHAR(255) NULL,
    recibido_por      INT UNSIGNED NULL,
    created_at        DATETIME NOT NULL,
    UNIQUE KEY uq_cierre (cobrador_id, fecha),
    CONSTRAINT fk_cierres_cobrador FOREIGN KEY (cobrador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_cierres_recibe   FOREIGN KEY (recibido_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Los créditos existentes entregaron el monto completo
UPDATE creditos SET neto_entregado = monto WHERE neto_entregado IS NULL;
