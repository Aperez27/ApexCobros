-- =========================================================
--  APEX · Migración de seguridad (v1.1)
--  Segura de ejecutar varias veces; no borra datos.
-- =========================================================
USE apex_cobros_db;

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS debe_cambiar_clave   TINYINT(1)   NOT NULL DEFAULT 0 AFTER activo,
    ADD COLUMN IF NOT EXISTS clave_actualizada_at DATETIME     NULL AFTER debe_cambiar_clave,
    ADD COLUMN IF NOT EXISTS sesion_version       INT UNSIGNED NOT NULL DEFAULT 1 AFTER clave_actualizada_at;

-- Intentos de inicio de sesión (bloqueo de fuerza bruta por usuario e IP)
CREATE TABLE IF NOT EXISTS intentos_login (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario    VARCHAR(120) NOT NULL,
    ip         VARCHAR(45)  NOT NULL,
    exito      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL,
    INDEX idx_intentos_usuario (usuario, created_at),
    INDEX idx_intentos_ip (ip, created_at)
) ENGINE=InnoDB;

-- Bitácora de auditoría
CREATE TABLE IF NOT EXISTS auditoria (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    accion     VARCHAR(40)  NOT NULL,
    detalle    VARCHAR(500) NULL,
    entidad    VARCHAR(30)  NULL,
    entidad_id INT UNSIGNED NULL,
    ip         VARCHAR(45)  NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME     NOT NULL,
    INDEX idx_auditoria_fecha (created_at),
    INDEX idx_auditoria_usuario (usuario_id),
    INDEX idx_auditoria_accion (accion),
    CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- El administrador inicial debe cambiar la clave por defecto en su primer ingreso
UPDATE usuarios SET debe_cambiar_clave = 1 WHERE usuario = 'admin' AND clave_actualizada_at IS NULL;
