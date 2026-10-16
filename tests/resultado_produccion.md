# Verificación previa a producción · APEX

Fecha: 2026-10-15 20:22 · URL: http://localhost/ApexCobros

**39 OK · 7 advertencias · 1 errores**

### Configuración de la aplicación

| Estado | Verificación | Detalle |
|---|---|---|
| ✅ | APP_DEBUG desactivado |  |
| ⚠️ | La URL no usa HTTPS | en internet publícalo con certificado SSL (cookies seguras y HSTS se activan solos) |
| ✅ | Zona horaria America/Bogota |  |
| ⚠️ | La app se conecta a MySQL con el usuario root | crea un usuario solo para apex_cobros_db |
| ⚠️ | El usuario de MySQL no tiene contraseña | asígnale una y ponla en .env (DB_PASS) |
| ✅ | Cierre de sesión por inactividad: 30 min |  |

### Servidor y PHP

| Estado | Verificación | Detalle |
|---|---|---|
| ✅ | PHP 8.2.12 |  |
| ✅ | Extensión pdo_mysql |  |
| ✅ | Extensión mbstring |  |
| ✅ | Extensión zlib |  |
| ✅ | Extensión curl |  |
| ✅ | Argon2id disponible para contraseñas |  |
| ✅ | storage/logs tiene permiso de escritura |  |
| ✅ | Log de errores de PHP sin registros |  |
| ✅ | Sintaxis correcta en los 78 archivos PHP de la aplicación |  |

### Servidor web y seguridad HTTP

| Estado | Verificación | Detalle |
|---|---|---|
| ✅ | La aplicación responde (HTTP 200) |  |
| ✅ | Cabecera content-security-policy |  |
| ✅ | Cabecera x-frame-options |  |
| ✅ | Cabecera x-content-type-options |  |
| ✅ | Cabecera referrer-policy |  |
| ✅ | Cabecera permissions-policy |  |
| ✅ | No se expone la versión de PHP |  |
| ⚠️ | Apache muestra su versión en la cabecera Server | apache/2.4.58 (win64) openssl/3.1.3 php/8.2.12 → en httpd.conf: ServerTokens Prod y ServerSignature Off |
| ✅ | Cookie de sesión HttpOnly y SameSite |  |
| ✅ | Archivos internos bloqueados (8 revisados) |  |
| ✅ | Listado de carpetas deshabilitado |  |
| ✅ | Las páginas privadas exigen iniciar sesión |  |

### Base de datos

| Estado | Verificación | Detalle |
|---|---|---|
| ✅ | Conexión a MySQL/MariaDB (10.4.32-MariaDB) |  |
| ✅ | Las 12 tablas existen |  |
| ✅ | Migraciones de seguridad y v1.2 aplicadas |  |
| ✅ | Todas las tablas usan InnoDB |  |
| ✅ | Llaves foráneas activas (20) |  |

### Usuarios y datos

| Estado | Verificación | Detalle |
|---|---|---|
| ✅ | Administradores activos: 1 |  |
| ✅ | El administrador ya no usa la contraseña inicial |  |
| ❌ | Quedan datos de prueba | 5 usuarios demo/prueba y 100 clientes demo/prueba |
| ✅ | Contenido actual | 7 usuarios, 102 clientes, 120 créditos, 1387 pagos, 47 eventos de auditoría |
| ⚠️ | El nombre de la empresa sigue siendo el predeterminado | Configuración → Datos de la empresa (sale en recibos y reportes) |
| ⚠️ | Falta el teléfono de la empresa | se imprime en recibos y paz y salvo |

### Integridad de la información

| Estado | Verificación | Detalle |
|---|---|---|
| ✅ | Sin créditos cuyo saldo no cuadra con sus pagos |  |
| ✅ | Sin créditos cuyo plan de cuotas no suma el total |  |
| ✅ | Sin cuotas con abono mayor a su valor |  |
| ✅ | Sin créditos activos con saldo cero o pagados con saldo |  |
| ✅ | Sin pagos con fecha futura |  |
| ⚠️ | Créditos activos de clientes sin cobrador | 2 |
| ✅ | Sin clientes asignados a cobradores inactivos |  |
| ✅ | Sin intentos de ingreso fallidos recientes |  |

### Respaldos

| Estado | Verificación | Detalle |
|---|---|---|
| ✅ | Último respaldo en el servidor: antes_de_limpiar_2026-10-12_134339.sql | 2026-10-12 13:43 |
