<?php
/**
 * APEX · Verificación previa a producción (SOLO LECTURA: no modifica nada).
 *
 *   C:\xampp\php\php.exe tests\verificar_produccion.php
 *
 * Revisa configuración, servidor, seguridad, base de datos e integridad de la información.
 * Resultado: OK / ADVERTENCIA (recomendado corregir) / ERROR (corregir antes de publicar).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Solo por línea de comandos.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Database;

$res = ['OK' => 0, 'ADVERTENCIA' => 0, 'ERROR' => 0];
$md = [];
function item(string $nivel, string $texto, string $detalle = ''): void
{
    global $res, $md;
    $res[$nivel]++;
    $icono = ['OK' => '  [OK]   ', 'ADVERTENCIA' => '  [AVISO]', 'ERROR' => '  [ERROR]'][$nivel];
    echo $icono . ' ' . $texto . ($detalle !== '' ? "  → {$detalle}" : '') . "\n";
    $md[] = '| ' . ['OK' => '✅', 'ADVERTENCIA' => '⚠️', 'ERROR' => '❌'][$nivel] . ' | ' . str_replace('|', '/', $texto) . ' | ' . str_replace('|', '/', $detalle) . ' |';
}
function seccion(string $t): void
{
    global $md;
    echo "\n== {$t} ==\n";
    $md[] = "\n### {$t}\n\n| Estado | Verificación | Detalle |\n|---|---|---|";
}
function http(string $url, string $method = 'GET'): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false]);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['code' => $code, 'headers' => strtolower(substr($raw, 0, $hs)), 'body' => substr($raw, $hs)];
}

$base = rtrim((string) Config::get('app.url'), '/');
echo "APEX · Verificación previa a producción · " . date('Y-m-d H:i') . "\nURL: {$base}\n";

// ---------------------------------------------------------------- configuración
seccion('Configuración de la aplicación');
Config::get('app.debug') ? item('ERROR', 'APP_DEBUG debe estar en false (muestra errores internos)') : item('OK', 'APP_DEBUG desactivado');
str_starts_with($base, 'https://') ? item('OK', 'La URL usa HTTPS') : item('ADVERTENCIA', 'La URL no usa HTTPS', 'en internet publícalo con certificado SSL (cookies seguras y HSTS se activan solos)');
date_default_timezone_get() === 'America/Bogota' ? item('OK', 'Zona horaria America/Bogota') : item('ADVERTENCIA', 'Zona horaria', date_default_timezone_get());
$db = Config::get('db');
$db['user'] === 'root' ? item('ADVERTENCIA', 'La app se conecta a MySQL con el usuario root', 'crea un usuario solo para apex_cobros_db') : item('OK', 'Usuario de MySQL dedicado: ' . $db['user']);
$db['pass'] === '' ? item('ADVERTENCIA', 'El usuario de MySQL no tiene contraseña', 'asígnale una y ponla en .env (DB_PASS)') : item('OK', 'MySQL protegido con contraseña');
(int) Config::get('security.session_idle_minutes') <= 60 ? item('OK', 'Cierre de sesión por inactividad: ' . Config::get('security.session_idle_minutes') . ' min') : item('ADVERTENCIA', 'Inactividad de sesión muy larga');

// ---------------------------------------------------------------- servidor PHP
seccion('Servidor y PHP');
version_compare(PHP_VERSION, '8.1', '>=') ? item('OK', 'PHP ' . PHP_VERSION) : item('ERROR', 'Se requiere PHP 8.1 o superior', PHP_VERSION);
foreach (['pdo_mysql', 'mbstring', 'zlib', 'curl'] as $ext) {
    extension_loaded($ext) ? item('OK', "Extensión {$ext}") : item($ext === 'curl' ? 'ADVERTENCIA' : 'ERROR', "Falta la extensión {$ext}");
}
defined('PASSWORD_ARGON2ID') ? item('OK', 'Argon2id disponible para contraseñas') : item('ADVERTENCIA', 'Argon2id no disponible', 'se usará bcrypt');
$logs = BASE_PATH . '/storage/logs';
is_writable($logs) ? item('OK', 'storage/logs tiene permiso de escritura') : item('ERROR', 'storage/logs no tiene permiso de escritura');
$log = $logs . '/php-error.log';
if (is_file($log) && filesize($log) > 0) {
    $ult = array_slice(file($log, FILE_IGNORE_NEW_LINES), -3);
    item('ADVERTENCIA', 'Hay errores registrados en storage/logs/php-error.log', mb_substr(implode(' / ', $ult), 0, 250));
} else {
    item('OK', 'Log de errores de PHP sin registros');
}
$sinErrores = true;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH . '/app', FilesystemIterator::SKIP_DOTS));
$archivos = 0;
foreach ($it as $f) {
    if ($f->getExtension() === 'php') {
        $archivos++;
        $out = shell_exec('"' . PHP_BINARY . '" -l ' . escapeshellarg($f->getPathname()) . ' 2>&1');
        if (!str_contains((string) $out, 'No syntax errors')) {
            $sinErrores = false;
            item('ERROR', 'Error de sintaxis en ' . str_replace(BASE_PATH, '', $f->getPathname()), trim((string) $out));
        }
    }
}
if ($sinErrores) {
    item('OK', "Sintaxis correcta en los {$archivos} archivos PHP de la aplicación");
}

// ---------------------------------------------------------------- servidor web
seccion('Servidor web y seguridad HTTP');
$r = http($base . '/auth/login');
if ($r['code'] !== 200) {
    item('ERROR', 'La aplicación no responde', "HTTP {$r['code']} en {$base}/auth/login (¿Apache encendido?)");
} else {
    item('OK', 'La aplicación responde (HTTP 200)');
    foreach (['content-security-policy', 'x-frame-options', 'x-content-type-options', 'referrer-policy', 'permissions-policy'] as $h) {
        str_contains($r['headers'], $h . ':') ? item('OK', "Cabecera {$h}") : item('ERROR', "Falta la cabecera {$h}");
    }
    str_contains($r['headers'], 'x-powered-by') ? item('ADVERTENCIA', 'Se expone la versión de PHP (X-Powered-By)') : item('OK', 'No se expone la versión de PHP');
    preg_match('/^server: (.*)$/m', $r['headers'], $m);
    isset($m[1]) && preg_match('/\d+\.\d+/', $m[1])
        ? item('ADVERTENCIA', 'Apache muestra su versión en la cabecera Server', trim($m[1]) . ' → en httpd.conf: ServerTokens Prod y ServerSignature Off')
        : item('OK', 'Apache no muestra su versión');
    preg_match('/set-cookie: apexcobrossessid=[^\r\n]*/', $r['headers'], $c);
    $cookie = $c[0] ?? '';
    str_contains($cookie, 'httponly') && str_contains($cookie, 'samesite') ? item('OK', 'Cookie de sesión HttpOnly y SameSite') : item('ERROR', 'Cookie de sesión sin HttpOnly/SameSite', $cookie);
}
$bloqueados = ['.env', 'database/schema.sql', 'database/seed_demo.php', 'storage/logs/php-error.log', 'app/config/config.php', 'tests/seguridad.php', 'README.md', '.htaccess'];
$expuestos = [];
foreach ($bloqueados as $p) {
    $c = http($base . '/' . $p)['code'];
    if (!in_array($c, [403, 404], true)) {
        $expuestos[] = "{$p} ({$c})";
    }
}
$expuestos ? item('ERROR', 'Archivos internos accesibles desde el navegador', implode(', ', $expuestos)) : item('OK', 'Archivos internos bloqueados (' . count($bloqueados) . ' revisados)');
http($base . '/assets/')['code'] === 403 ? item('OK', 'Listado de carpetas deshabilitado') : item('ERROR', 'Se puede listar el contenido de carpetas');
http($base . '/dashboard')['code'] === 302 ? item('OK', 'Las páginas privadas exigen iniciar sesión') : item('ERROR', 'Una página privada respondió sin iniciar sesión');

// ---------------------------------------------------------------- base de datos
seccion('Base de datos');
try {
    $pdo = Database::connection();
    item('OK', 'Conexión a MySQL/MariaDB (' . $pdo->query('SELECT VERSION()')->fetchColumn() . ')');
} catch (Throwable $e) {
    item('ERROR', 'No se pudo conectar a la base de datos', $e->getMessage());
    $pdo = null;
}
if ($pdo) {
    $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $faltan = array_diff(['usuarios', 'clientes', 'asignaciones', 'creditos', 'cuotas', 'pagos', 'configuracion', 'intentos_login',
        'auditoria', 'gestiones', 'gastos', 'cierres'], $tablas);
    $faltan ? item('ERROR', 'Faltan tablas (aplica las migraciones)', implode(', ', $faltan)) : item('OK', 'Las 12 tablas existen');
    $cols = $pdo->query("SELECT CONCAT(table_name, '.', column_name) FROM information_schema.columns WHERE table_schema = DATABASE()")->fetchAll(PDO::FETCH_COLUMN);
    $faltanCols = array_diff(['usuarios.debe_cambiar_clave', 'usuarios.sesion_version', 'creditos.renovado_de', 'creditos.neto_entregado'], $cols);
    $faltanCols ? item('ERROR', 'Faltan columnas de las migraciones', implode(', ', $faltanCols)) : item('OK', 'Migraciones de seguridad y v1.2 aplicadas');
    $motor = $pdo->query("SELECT GROUP_CONCAT(table_name) FROM information_schema.tables WHERE table_schema = DATABASE() AND engine <> 'InnoDB'")->fetchColumn();
    $motor ? item('ERROR', 'Tablas sin InnoDB (sin transacciones ni llaves foráneas)', $motor) : item('OK', 'Todas las tablas usan InnoDB');
    $fk = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE()")->fetchColumn();
    $fk >= 15 ? item('OK', "Llaves foráneas activas ({$fk})") : item('ADVERTENCIA', 'Pocas llaves foráneas', (string) $fk);

    seccion('Usuarios y datos');
    $admins = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin' AND activo = 1")->fetchColumn();
    $admins > 0 ? item('OK', "Administradores activos: {$admins}") : item('ERROR', 'No hay ningún administrador activo');
    $admin = $pdo->query("SELECT password, debe_cambiar_clave FROM usuarios WHERE usuario = 'admin'")->fetch();
    if ($admin && password_verify('Admin123*', $admin['password'])) {
        $admin['debe_cambiar_clave']
            ? item('ADVERTENCIA', 'El usuario admin aún tiene la contraseña inicial', 'el sistema obligará a cambiarla en el primer ingreso')
            : item('ERROR', 'El usuario admin tiene la contraseña inicial y no está obligado a cambiarla');
    } else {
        item('OK', 'El administrador ya no usa la contraseña inicial');
    }
    $demo = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE usuario LIKE 'demo.%' OR usuario LIKE 'sectest%'")->fetchColumn();
    $demoCli = (int) $pdo->query("SELECT COUNT(*) FROM clientes WHERE cedula LIKE '91000%' OR cedula LIKE '999000%'")->fetchColumn();
    $demo || $demoCli ? item('ERROR', 'Quedan datos de prueba', "{$demo} usuarios demo/prueba y {$demoCli} clientes demo/prueba")
        : item('OK', 'Sin usuarios ni clientes de prueba');
    $conteo = $pdo->query('SELECT (SELECT COUNT(*) FROM clientes) c, (SELECT COUNT(*) FROM creditos) cr, (SELECT COUNT(*) FROM pagos) p,
        (SELECT COUNT(*) FROM usuarios) u, (SELECT COUNT(*) FROM auditoria) a')->fetch();
    item('OK', 'Contenido actual', "{$conteo['u']} usuarios, {$conteo['c']} clientes, {$conteo['cr']} créditos, {$conteo['p']} pagos, {$conteo['a']} eventos de auditoría");
    $cfg = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);
    ($cfg['empresa_nombre'] ?? '') !== '' && ($cfg['empresa_nombre'] ?? '') !== 'APEX Cobros'
        ? item('OK', 'Nombre de la empresa configurado: ' . $cfg['empresa_nombre'])
        : item('ADVERTENCIA', 'El nombre de la empresa sigue siendo el predeterminado', 'Configuración → Datos de la empresa (sale en recibos y reportes)');
    trim($cfg['empresa_telefono'] ?? '') === '' ? item('ADVERTENCIA', 'Falta el teléfono de la empresa', 'se imprime en recibos y paz y salvo') : item('OK', 'Teléfono de la empresa configurado');

    seccion('Integridad de la información');
    $q = fn (string $sql) => (int) $pdo->query($sql)->fetchColumn();
    $checks = [
        'Créditos cuyo saldo no cuadra con sus pagos' => "SELECT COUNT(*) FROM creditos cr WHERE ABS(cr.total - cr.saldo - (SELECT COALESCE(SUM(monto),0) FROM pagos p WHERE p.credito_id = cr.id AND p.anulado = 0)) > 0.01",
        'Créditos cuyo plan de cuotas no suma el total' => 'SELECT COUNT(*) FROM creditos cr WHERE ABS(cr.total - (SELECT COALESCE(SUM(valor),0) FROM cuotas c WHERE c.credito_id = cr.id)) > 0.01',
        'Cuotas con abono mayor a su valor' => 'SELECT COUNT(*) FROM cuotas WHERE abonado > valor + 0.01',
        'Créditos activos con saldo cero o pagados con saldo' => "SELECT COUNT(*) FROM creditos WHERE (estado = 'activo' AND saldo <= 0) OR (estado = 'pagado' AND saldo > 0)",
        'Pagos con fecha futura' => 'SELECT COUNT(*) FROM pagos WHERE fecha > NOW() + INTERVAL 5 MINUTE',
        'Créditos activos de clientes sin cobrador' => "SELECT COUNT(*) FROM creditos cr JOIN clientes c ON c.id = cr.cliente_id WHERE cr.estado = 'activo' AND c.cobrador_id IS NULL",
        'Clientes asignados a cobradores inactivos' => "SELECT COUNT(*) FROM clientes c JOIN usuarios u ON u.id = c.cobrador_id WHERE u.activo = 0",
    ];
    foreach ($checks as $txt => $sql) {
        $n = $q($sql);
        $n === 0 ? item('OK', 'Sin ' . mb_strtolower(mb_substr($txt, 0, 1)) . mb_substr($txt, 1)) : item(str_contains($txt, 'cobrador') ? 'ADVERTENCIA' : 'ERROR', $txt, (string) $n);
    }
    $bloq = $q("SELECT COUNT(*) FROM intentos_login WHERE exito = 0 AND created_at >= NOW() - INTERVAL 15 MINUTE");
    $bloq ? item('ADVERTENCIA', 'Intentos de ingreso fallidos en los últimos 15 minutos', (string) $bloq) : item('OK', 'Sin intentos de ingreso fallidos recientes');
}

// ---------------------------------------------------------------- respaldos
seccion('Respaldos');
$dir = BASE_PATH . '/storage/backups';
$ult = is_dir($dir) ? array_values(array_filter(glob($dir . '/*.sql') ?: [], 'is_file')) : [];
usort($ult, fn ($a, $b) => filemtime($b) <=> filemtime($a));
$ult ? item('OK', 'Último respaldo en el servidor: ' . basename($ult[0]), date('Y-m-d H:i', filemtime($ult[0])))
     : item('ADVERTENCIA', 'No hay respaldos en storage/backups', 'descarga uno desde el menú Respaldo y guárdalo fuera del equipo');

// ---------------------------------------------------------------- resumen
printf("\nResultado: %d OK · %d advertencias · %d errores\n", $res['OK'], $res['ADVERTENCIA'], $res['ERROR']);
echo $res['ERROR'] ? "NO está listo para producción: corrige los errores.\n" : ($res['ADVERTENCIA'] ? "Listo para producción, con recomendaciones pendientes.\n" : "Listo para producción.\n");
file_put_contents(__DIR__ . '/resultado_produccion.md', "# Verificación previa a producción · APEX\n\nFecha: " . date('Y-m-d H:i') . " · URL: {$base}\n\n**{$res['OK']} OK · {$res['ADVERTENCIA']} advertencias · {$res['ERROR']} errores**\n" . implode("\n", $md) . "\n");
exit($res['ERROR'] ? 1 : 0);
