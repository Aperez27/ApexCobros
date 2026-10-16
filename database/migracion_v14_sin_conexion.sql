-- =========================================================
--  APEX · Migración v1.4: trabajo sin conexión en la ruta de cobro
--  Segura de ejecutar varias veces; no borra datos.
-- =========================================================
USE apex_cobros_db;

-- Cada cobro o gestión hecho desde la ruta lleva un identificador único generado en el celular.
-- Si la misma operación llega dos veces (reintento, mala señal, dos pestañas) solo se registra una.
CREATE TABLE IF NOT EXISTS operaciones_offline (
    uuid        CHAR(36)     NOT NULL PRIMARY KEY,
    usuario_id  INT UNSIGNED NULL,
    tipo        ENUM('pago','gestion') NOT NULL,
    entidad_id  INT UNSIGNED NULL COMMENT 'Pago o gestión creada',
    creado_en   DATETIME     NULL COMMENT 'Hora del celular al registrarla',
    sin_conexion TINYINT(1)  NOT NULL DEFAULT 0 COMMENT '1 = se guardó sin internet y llegó al sincronizar',
    created_at  DATETIME     NOT NULL COMMENT 'Hora en que llegó al servidor',
    INDEX idx_opoff_usuario (usuario_id, created_at),
    CONSTRAINT fk_opoff_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;
