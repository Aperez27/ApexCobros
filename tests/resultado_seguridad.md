# Resultado de pruebas de seguridad · APEX

Fecha: 2026-10-15 20:29 · Objetivo: http://localhost/ApexCobros

**93 de 93 pruebas superadas.**


## 1. Configuración y exposición de información

| ID | Prueba | Resultado |
|---|---|---|
| S01 | Archivo interno no accesible: /.env | ✅ OK |
| S02 | Archivo interno no accesible: /.env.example | ✅ OK |
| S03 | Archivo interno no accesible: /database/schema.sql | ✅ OK |
| S04 | Archivo interno no accesible: /storage/logs/php-error.log | ✅ OK |
| S05 | Archivo interno no accesible: /app/config/config.php | ✅ OK |
| S06 | Archivo interno no accesible: /README.md | ✅ OK |
| S07 | Archivo interno no accesible: /tests/seguridad.php | ✅ OK |
| S08 | Archivo interno no accesible: /.htaccess | ✅ OK |
| S09 | Listado de directorios deshabilitado (/assets/) | ✅ OK |
| S10 | No revela versión de PHP (X-Powered-By) | ✅ OK |
| S11 | Cabecera X-Frame-Options (anti-clickjacking) | ✅ OK |
| S12 | Cabecera X-Content-Type-Options: nosniff | ✅ OK |
| S13 | Cabecera Content-Security-Policy | ✅ OK |
| S14 | Cabecera Referrer-Policy | ✅ OK |
| S15 | Cabecera Permissions-Policy | ✅ OK |
| S16 | Cookie de sesión HttpOnly | ✅ OK |
| S17 | Cookie de sesión SameSite | ✅ OK |
| S18 | CSP no permite scripts inline arbitrarios (sin 'unsafe-inline' en script-src) | ✅ OK |
| S19 | Páginas privadas no se guardan en caché (Cache-Control no-store) | ✅ OK |

## 2. Autenticación y sesiones

| ID | Prueba | Resultado |
|---|---|---|
| A01 | Mismo mensaje para usuario inexistente y clave errada (sin enumeración) | ✅ OK |
| A02 | Bloqueo por fuerza bruta aunque el atacante cambie de sesión/cookie | ✅ OK |
| A03 | Los intentos fallidos quedan registrados en BD | ✅ OK |
| A04 | Usuario inactivo no puede iniciar sesión | ✅ OK |
| A05 | El ID de sesión cambia al iniciar sesión (anti session-fixation) | ✅ OK |
| A06 | Tras cerrar sesión, la cookie anterior ya no sirve | ✅ OK |
| A07 | Al desactivar un usuario se cierra su sesión activa de inmediato | ✅ OK |
| A08 | Un cambio de rol (admin→cobrador) aplica de inmediato a la sesión abierta | ✅ OK |
| A09 | Rechaza contraseñas débiles al crear usuarios (123456, password, admin123, Abcdefgh, abcdefgh1!, Corta#1) | ✅ OK |
| A10 | Rechaza contraseñas que contienen el nombre de usuario | ✅ OK |
| A11 | Rechaza contraseña débil al cambiarla desde Mi perfil | ✅ OK |
| A12 | Acepta contraseñas fuertes | ✅ OK |
| A13 | Hash de contraseña con Argon2id | ✅ OK |
| A14 | Usuario creado por el admin debe cambiar la clave en su primer ingreso | ✅ OK |
| A15 | Con cambio de clave pendiente solo se permite ir a Mi perfil | ✅ OK |

## 3. Control de acceso (roles e IDOR)

| ID | Prueba | Resultado |
|---|---|---|
| Z01 | Sin sesión, todas las rutas privadas redirigen al login | ✅ OK |
| Z02 | Cobrador no accede a módulos de administrador | ✅ OK |
| Z03 | Cobrador no puede crearse un usuario administrador (escalada de privilegios) | ✅ OK |
| Z04 | Cobrador no puede cambiar la configuración | ✅ OK |
| Z05 | Cobrador no puede anular pagos | ✅ OK |
| Z06 | Cobrador no puede eliminar clientes | ✅ OK |
| Z07 | IDOR: un cobrador no ve clientes, créditos, recibos ni cartera de otro | ✅ OK |
| Z08 | IDOR: un cobrador no puede registrar pagos en créditos ajenos | ✅ OK |
| Z09 | IDOR: un cobrador no puede editar clientes ajenos | ✅ OK |
| Z10 | Asignación masiva de campos: el cobrador no puede asignar clientes a otro ni cambiar su estado | ✅ OK |
| Z11 | El cobrador no puede antedatar pagos ni registrarlos a nombre de otro | ✅ OK |

## 4. Inyección, XSS, CSRF y validación

| ID | Prueba | Resultado |
|---|---|---|
| I01 | CSRF: rechaza formularios sin token | ✅ OK |
| I02 | CSRF: rechaza el token de otra sesión | ✅ OK |
| I03 | Acciones que modifican datos no se ejecutan por GET | ✅ OK |
| I04 | Inyección SQL en filtros de búsqueda (24 combinaciones): sin errores ni fuga de datos | ✅ OK |
| I05 | Inyección SQL no amplía lo que ve un cobrador | ✅ OK |
| I06 | Inyección en parámetros de la URL: sin errores 500 ni mensajes internos | ✅ OK |
| I07 | La tabla clientes sigue intacta tras los intentos de DROP | ✅ OK |
| I08 | XSS almacenado: los datos se muestran escapados en todas las vistas | ✅ OK |
| I09 | XSS reflejado en parámetros de búsqueda | ✅ OK |
| I10 | Pagos con valores negativos, cero, texto o mayores al saldo son rechazados | ✅ OK |
| I11 | Integridad: saldo = total − pagos vigentes | ✅ OK |
| I12 | Créditos con interés negativo, cuotas absurdas o fechas inválidas son rechazados | ✅ OK |
| I13 | Excel: un texto como =HYPERLINK(...) se guarda como texto literal, nunca como fórmula | ✅ OK |
| I14 | Entradas muy largas se recortan sin errores | ✅ OK |
| I15 | Sin redirección abierta a dominios externos (cabecera Referer manipulada) | ✅ OK |
| I16 | Sin redirección abierta con Referer de otro host | ✅ OK |
| I17 | Router: no ejecuta métodos internos ni permite recorrer rutas | ✅ OK |
| I18 | Parámetros enviados como arreglo (type juggling) no rompen la aplicación | ✅ OK |

## 6. Caja, gestiones, renovaciones, documentos y respaldo

| ID | Prueba | Resultado |
|---|---|---|
| F01 | Cobrador no puede cerrar su propia caja (solo el administrador recibe el dinero) | ✅ OK |
| F02 | Gasto registrado por un cobrador siempre queda a su nombre (no a nombre de otro) | ✅ OK |
| F03 | Cobrador no puede registrar gastos con fechas pasadas | ✅ OK |
| F04 | Gastos con valores negativos, cero, texto, decimales, exagerados o concepto inválido son rechazados | ✅ OK |
| F05 | IDOR: un cobrador no puede anular gastos de otro | ✅ OK |
| F06 | Cierre con valor entregado inválido o fecha futura es rechazado | ✅ OK |
| F07 | Cierre de caja calcula efectivo − gastos y registra reembolso cuando los gastos superan lo cobrado | ✅ OK |
| F08 | Efectivo en mano queda en cero tras el cierre (libro de caja cuadrado) | ✅ OK |
| F09 | IDOR: un cobrador no puede registrar gestiones en créditos de otro | ✅ OK |
| F10 | Gestiones con promesa en el pasado, valor mayor al saldo o tipos inválidos son rechazadas | ✅ OK |
| F11 | Promesa de pago se marca cumplida automáticamente al recibir el pago | ✅ OK |
| F12 | La nota de la gestión (XSS) se muestra escapada | ✅ OK |
| F13 | Cobrador no puede renovar créditos | ✅ OK |
| F14 | Renovación con monto menor o igual al saldo pendiente es rechazada | ✅ OK |
| F15 | Renovación: cancela el saldo anterior, descuenta el neto y no permite cambiar de cliente | ✅ OK |
| F16 | El movimiento de renovación no se puede anular suelto (evita cobrar dos veces la misma deuda) | ✅ OK |
| F17 | La renovación no se cuenta como dinero recaudado | ✅ OK |
| F18 | Anular la renovación revive el crédito anterior con su saldo exacto | ✅ OK |
| F19 | IDOR: un cobrador no descarga estado de cuenta, Excel ni paz y salvo de créditos ajenos | ✅ OK |
| F20 | Paz y salvo solo se expide con el crédito totalmente pagado | ✅ OK |
| F21 | El estado de cuenta en Excel es un .xlsx válido (contenedor, libro, hojas y estilos) | ✅ OK |
| F22 | Cobrador no accede al respaldo de la base de datos | ✅ OK |
| F23 | El respaldo solo se descarga por POST con token CSRF (no por un enlace) | ✅ OK |
| F24 | Respaldo del administrador contiene la estructura y los datos de todas las tablas | ✅ OK |
| F25 | La descarga del respaldo queda en la auditoría | ✅ OK |
| F26 | Los manuales no se pueden descargar sin iniciar sesión | ✅ OK |
| F27 | El cobrador no puede abrir manuales de administrador (sí el suyo) | ✅ OK |
| F28 | No se pueden obtener otros archivos manipulando la ruta de los manuales | ✅ OK |

## 5. Trazabilidad

| ID | Prueba | Resultado |
|---|---|---|
| T01 | Se registra bitácora de auditoría (ingresos, fallos, pagos, cambios de usuarios) | ✅ OK |
| T02 | Ninguna página muestra errores internos de PHP/SQL | ✅ OK |
