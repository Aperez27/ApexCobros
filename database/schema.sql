-- =========================================================
--  APEX · Sistema de control de cobros y cartera
--  Desarrollado por Ing. Antony Pérez
--  Base de datos: apex_cobros_db (MySQL / MariaDB)
-- =========================================================
CREATE DATABASE IF NOT EXISTS apex_cobros_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE apex_cobros_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS cierres;
DROP TABLE IF EXISTS gastos;
DROP TABLE IF EXISTS gestiones;
DROP TABLE IF EXISTS auditoria;
DROP TABLE IF EXISTS intentos_login;
DROP TABLE IF EXISTS pagos;
DROP TABLE IF EXISTS cuotas;
DROP TABLE IF EXISTS creditos;
DROP TABLE IF EXISTS asignaciones;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS configuracion;
SET FOREIGN_KEY_CHECKS = 1;

-- Usuarios del sistema: administradores y cobradores
CREATE TABLE usuarios (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(120) NOT NULL,
    cedula         VARCHAR(20)  NULL UNIQUE,
    telefono       VARCHAR(20)  NULL,
    email          VARCHAR(120) NULL,
    usuario        VARCHAR(50)  NOT NULL UNIQUE,
    password       VARCHAR(255) NOT NULL,
    rol            ENUM('admin','cobrador') NOT NULL DEFAULT 'cobrador',
    zona           VARCHAR(100) NULL COMMENT 'Ruta o sector que cubre el cobrador',
    meta_diaria    DECIMAL(14,2) NOT NULL DEFAULT 0,
    activo         TINYINT(1)   NOT NULL DEFAULT 1,
    debe_cambiar_clave   TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Obliga a cambiar la clave en el próximo ingreso',
    clave_actualizada_at DATETIME   NULL,
    sesion_version       INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Al incrementarse invalida las sesiones abiertas',
    ultimo_acceso  DATETIME     NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Clientes (deudores)
CREATE TABLE clientes (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cedula              VARCHAR(20)  NOT NULL UNIQUE,
    nombres             VARCHAR(80)  NOT NULL,
    apellidos           VARCHAR(80)  NOT NULL,
    email               VARCHAR(120) NULL,
    telefono1           VARCHAR(20)  NOT NULL,
    telefono2           VARCHAR(20)  NULL,
    direccion           VARCHAR(180) NOT NULL,
    barrio              VARCHAR(100) NULL,
    ciudad              VARCHAR(80)  NULL,
    fecha_nacimiento    DATE         NULL,
    ocupacion           VARCHAR(100) NULL,
    negocio_nombre      VARCHAR(120) NULL,
    negocio_direccion   VARCHAR(180) NULL,
    referencia_nombre   VARCHAR(120) NULL,
    referencia_telefono VARCHAR(20)  NULL,
    referencia_parentesco VARCHAR(50) NULL,
    observaciones       TEXT         NULL,
    cobrador_id         INT UNSIGNED NULL,
    orden_ruta          INT UNSIGNED NULL COMMENT 'Posición en la ruta del cobrador',
    estado              ENUM('activo','inactivo','bloqueado') NOT NULL DEFAULT 'activo',
    created_by          INT UNSIGNED NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP    NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_clientes_cobrador (cobrador_id),
    INDEX idx_clientes_orden_ruta (cobrador_id, orden_ruta),
    INDEX idx_clientes_nombre (nombres, apellidos),
    CONSTRAINT fk_clientes_cobrador FOREIGN KEY (cobrador_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_clientes_creador  FOREIGN KEY (created_by)  REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Historial de asignaciones / traslados de cartera
CREATE TABLE asignaciones (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id           INT UNSIGNED NOT NULL,
    cobrador_anterior_id INT UNSIGNED NULL,
    cobrador_nuevo_id    INT UNSIGNED NULL,
    usuario_id           INT UNSIGNED NULL,
    motivo               VARCHAR(255) NULL,
    created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_asig_cliente (cliente_id),
    CONSTRAINT fk_asig_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    CONSTRAINT fk_asig_ant     FOREIGN KEY (cobrador_anterior_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_asig_nuevo   FOREIGN KEY (cobrador_nuevo_id)    REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_asig_usuario FOREIGN KEY (usuario_id)           REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Créditos / obligaciones a cobrar
CREATE TABLE creditos (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo            VARCHAR(20)  NOT NULL UNIQUE,
    cliente_id        INT UNSIGNED NOT NULL,
    monto             DECIMAL(14,2) NOT NULL COMMENT 'Capital entregado',
    interes           DECIMAL(6,2)  NOT NULL DEFAULT 0 COMMENT 'Porcentaje total sobre el capital',
    total             DECIMAL(14,2) NOT NULL COMMENT 'Capital + interés',
    num_cuotas        SMALLINT UNSIGNED NOT NULL,
    valor_cuota       DECIMAL(14,2) NOT NULL,
    frecuencia        ENUM('diaria','semanal','quincenal','mensual') NOT NULL DEFAULT 'diaria',
    excluir_domingos  TINYINT(1) NOT NULL DEFAULT 1,
    fecha_inicio      DATE NOT NULL COMMENT 'Fecha de desembolso',
    fecha_primera_cuota DATE NOT NULL,
    fecha_fin         DATE NOT NULL,
    total_pagado      DECIMAL(14,2) NOT NULL DEFAULT 0,
    saldo             DECIMAL(14,2) NOT NULL,
    estado            ENUM('activo','pagado','anulado') NOT NULL DEFAULT 'activo',
    observaciones     TEXT NULL,
    motivo_anulacion  VARCHAR(255) NULL,
    created_by        INT UNSIGNED NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_creditos_cliente (cliente_id),
    INDEX idx_creditos_estado (estado),
    CONSTRAINT fk_creditos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
    CONSTRAINT fk_creditos_creador FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Plan de cuotas
CREATE TABLE cuotas (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    credito_id        INT UNSIGNED NOT NULL,
    numero            SMALLINT UNSIGNED NOT NULL,
    fecha_vencimiento DATE NOT NULL,
    valor             DECIMAL(14,2) NOT NULL,
    abonado           DECIMAL(14,2) NOT NULL DEFAULT 0,
    estado            ENUM('pendiente','parcial','pagada') NOT NULL DEFAULT 'pendiente',
    fecha_pago        DATE NULL,
    UNIQUE KEY uq_cuota (credito_id, numero),
    INDEX idx_cuotas_venc (fecha_vencimiento, estado),
    CONSTRAINT fk_cuotas_credito FOREIGN KEY (credito_id) REFERENCES creditos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Pagos / abonos recibidos
CREATE TABLE pagos (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recibo        VARCHAR(20) NOT NULL UNIQUE,
    credito_id    INT UNSIGNED NOT NULL,
    cobrador_id   INT UNSIGNED NULL COMMENT 'Quién recibió el dinero',
    monto         DECIMAL(14,2) NOT NULL,
    metodo        ENUM('efectivo','nequi','daviplata','transferencia','otro') NOT NULL DEFAULT 'efectivo',
    fecha         DATETIME NOT NULL,
    nota          VARCHAR(255) NULL,
    anulado       TINYINT(1) NOT NULL DEFAULT 0,
    motivo_anulacion VARCHAR(255) NULL,
    anulado_por   INT UNSIGNED NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pagos_fecha (fecha),
    INDEX idx_pagos_credito (credito_id),
    INDEX idx_pagos_cobrador (cobrador_id),
    CONSTRAINT fk_pagos_credito  FOREIGN KEY (credito_id)  REFERENCES creditos(id) ON DELETE CASCADE,
    CONSTRAINT fk_pagos_cobrador FOREIGN KEY (cobrador_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_pagos_anulador FOREIGN KEY (anulado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

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

-- Configuración general editable
CREATE TABLE configuracion (
    clave VARCHAR(60) PRIMARY KEY,
    valor TEXT NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Datos iniciales
-- ---------------------------------------------------------
-- Administrador: admin / Admin123*
-- Debe cambiarla en el primer ingreso (política de contraseñas seguras)
INSERT INTO usuarios (nombre, cedula, telefono, email, usuario, password, rol, zona, debe_cambiar_clave) VALUES
('Administrador', NULL, NULL, 'admin@apex.local', 'admin',
 '$2y$10$6b4G7oigFIGu7uhAaLVjyuwK3Flg5hqmLyDVdXPINwHh7mF5Mrp4u', 'admin', NULL, 1);

INSERT INTO configuracion (clave, valor) VALUES
('empresa_nombre', 'APEX Cobros'),
('empresa_nit', ''),
('empresa_telefono', ''),
('empresa_direccion', ''),
('interes_defecto', '20'),
('cuotas_defecto', '30'),
('frecuencia_defecto', 'diaria'),
('recibo_mensaje', 'Gracias por su pago puntual.');

-- ---------------------------------------------------------
-- v1.2: caja del cobrador, gestiones de cobro y renovaciones
-- ---------------------------------------------------------
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

-- Operaciones de la ruta (cobros y gestiones) con su identificador único: evitan duplicados al sincronizar
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

-- Los créditos existentes entregaron el monto completo
UPDATE creditos SET neto_entregado = monto WHERE neto_entregado IS NULL;
