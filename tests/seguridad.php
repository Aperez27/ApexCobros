<?php
/**
 * APEX · Pruebas de seguridad automatizadas (caja negra + verificación en BD).
 *
 * Uso:  C:\xampp\php\php.exe tests\seguridad.php [URL_BASE]
 * Requiere Apache y MySQL encendidos. Crea usuarios/clientes temporales con prefijo
 * "sectest_" / cédula "999000…" y los elimina al terminar. No toca otros datos.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Solo por línea de comandos.');
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\Credito;

$BASE = rtrim($argv[1] ?? App\Core\Config::get('app.url'), '/');
$db = Database::connection();

// ---------------------------------------------------------------- cliente HTTP
final class Http
{
    private array $cookies = [];

    public function __construct(private string $base) {}

    public function request(string $method, string $path, array $data = [], array $headers = []): array
    {
        $url = str_starts_with($path, 'http') ? $path : $this->base . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        $cookie = implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($this->cookies), $this->cookies));
        $h = $headers;
        if ($cookie !== '') {
            $h[] = 'Cookie: ' . $cookie;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $h,
            CURLOPT_TIMEOUT        => 20,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        $raw = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hsize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $rawHeaders = substr($raw, 0, $hsize);
        $body = substr($raw, $hsize);
        $hdrs = [];
        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $hdrs[strtolower(trim($k))][] = trim($v);
                if (strtolower(trim($k)) === 'set-cookie' && preg_match('/^([^=]+)=([^;]*)/', trim($v), $m)) {
                    if ($m[2] === '' || $m[2] === 'deleted') {
                        unset($this->cookies[$m[1]]);
                    } else {
                        $this->cookies[$m[1]] = $m[2];
                    }
                }
            }
        }
        return ['code' => $code, 'headers' => $hdrs, 'body' => $body, 'location' => $hdrs['location'][0] ?? ''];
    }

    public function get(string $p, array $h = []): array { return $this->request('GET', $p, [], $h); }
    public function post(string $p, array $d = [], array $h = []): array { return $this->request('POST', $p, $d, $h); }

    public function token(string $page = 'auth/login'): string
    {
        $r = $this->get($page);
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $r['body'], $m);
        return $m[1] ?? '';
    }

    public function login(string $user, string $pass): array
    {
        $t = $this->token('auth/login');
        return $this->post('auth/login', ['_csrf' => $t, 'usuario' => $user, 'password' => $pass]);
    }

    public function sessionId(): string { return $this->cookies['APEXCOBROSSESSID'] ?? ''; }
    public function setSessionId(string $id): void { $this->cookies['APEXCOBROSSESSID'] = $id; }
}

// ---------------------------------------------------------------- utilidades
$results = [];
$section = '';
function section(string $s): void { global $section; $section = $s; echo "\n== $s ==\n"; }
function check(string $id, string $desc, bool $ok, string $detail = ''): void
{
    global $results, $section;
    $results[] = compact('id', 'desc', 'ok', 'detail', 'section');
    printf("  [%s] %-5s %s%s\n", $ok ? 'OK ' : 'FALLA', $id, $desc, (!$ok && $detail) ? "  → $detail" : '');
}
function q(string $sql, array $p = []): array { global $db; $s = $db->prepare($sql); $s->execute($p); return $s->fetchAll(); }
function v(string $sql, array $p = []): mixed { global $db; $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); }
function x(string $sql, array $p = []): void { global $db; try { $db->prepare($sql)->execute($p); } catch (Throwable) {} }
function leaks(string $body): bool
{
    return (bool) preg_match('/(Fatal error|Warning:|Notice:|Deprecated:|Stack trace|PDOException|SQLSTATE|Uncaught|\.php:\d+|on line \d+)/i', $body);
}
function loggedIn(array $r): bool { return $r['code'] === 200 && str_contains($r['body'], 'auth/logout'); }
/** Extrae los archivos de un ZIP (xlsx) generado con método deflate o store. */
function zipEntries(string $bin): array
{
    $out = [];
    $p = 0;
    while (($p = strpos($bin, "PK\x03\x04", $p)) !== false) {
        $h = unpack('vver/vflag/vmet/vt/vd/Vcrc/Vcs/Vus/vnl/vel', substr($bin, $p + 4, 26));
        $name = substr($bin, $p + 30, $h['nl']);
        $data = substr($bin, $p + 30 + $h['nl'] + $h['el'], $h['cs']);
        $out[$name] = $h['met'] === 8 ? (string) @gzinflate($data) : $data;
        $p += 30 + $h['nl'] + $h['el'] + $h['cs'];
    }
    return $out;
}

function cleanup(): void
{
    x("DELETE p FROM pagos p JOIN creditos c ON c.id = p.credito_id JOIN clientes cl ON cl.id = c.cliente_id WHERE cl.cedula LIKE '999000%'");
    x("DELETE g FROM gestiones g JOIN creditos c ON c.id = g.credito_id JOIN clientes cl ON cl.id = c.cliente_id WHERE cl.cedula LIKE '999000%'");
    x("DELETE c FROM creditos c JOIN clientes cl ON cl.id = c.cliente_id WHERE cl.cedula LIKE '999000%'");
    x("DELETE FROM clientes WHERE cedula LIKE '999000%'");
    x("DELETE FROM auditoria WHERE usuario_id IN (SELECT id FROM usuarios WHERE usuario LIKE 'sectest%')");
    x("DELETE FROM auditoria WHERE detalle LIKE '%sectest%' OR detalle LIKE '%noexiste_%' OR detalle LIKE '%999000%' OR (usuario_id IS NULL AND accion = 'login_fallido' AND detalle = 'Usuario: ')");
    x("DELETE FROM gastos WHERE cobrador_id IN (SELECT id FROM usuarios WHERE usuario LIKE 'sectest%')");
    x("DELETE FROM cierres WHERE cobrador_id IN (SELECT id FROM usuarios WHERE usuario LIKE 'sectest%')");
    x("UPDATE pagos SET cobrador_id = NULL WHERE cobrador_id IN (SELECT id FROM usuarios WHERE usuario LIKE 'sectest%')");
    x("DELETE FROM usuarios WHERE usuario LIKE 'sectest%'");
    x("DELETE FROM intentos_login WHERE usuario LIKE 'sectest%' OR usuario LIKE 'noexiste%'");
}

// ---------------------------------------------------------------- preparación
echo "APEX · pruebas de seguridad contra $BASE\n";
cleanup();
$PASS = 'Prueba#Segura2026';
$hash = password_hash($PASS, PASSWORD_DEFAULT);
$mk = function (string $u, string $rol, string $ced) use ($hash): int {
    x('INSERT INTO usuarios (nombre, cedula, telefono, usuario, password, rol, activo) VALUES (?, ?, ?, ?, ?, ?, 1)',
        ["Test $u", $ced, '3000000000', $u, $hash, $rol]);
    x('UPDATE usuarios SET debe_cambiar_clave = 0 WHERE usuario = ?', [$u]);
    return (int) v('SELECT id FROM usuarios WHERE usuario = ?', [$u]);
};
$adminId = $mk('sectest_admin', 'admin', '99900001');
$cob1 = $mk('sectest_cob1', 'cobrador', '99900002');
$cob2 = $mk('sectest_cob2', 'cobrador', '99900003');
$mk('sectest_brute', 'cobrador', '99900004');
$mk('sectest_inact', 'cobrador', '99900005');
x("UPDATE usuarios SET activo = 0 WHERE usuario = 'sectest_inact'");
$mk('sectest_victim', 'cobrador', '99900006');
$mk('sectest_rol', 'admin', '99900007');

x("INSERT INTO clientes (cedula, nombres, apellidos, telefono1, direccion, cobrador_id) VALUES ('999000101', 'Cliente', 'DeCob1', '3001', 'Calle 1', ?)", [$cob1]);
x("INSERT INTO clientes (cedula, nombres, apellidos, telefono1, direccion, cobrador_id) VALUES ('999000102', 'Cliente', 'DeCob2', '3002', 'Calle 2', ?)", [$cob2]);
$cli1 = (int) v("SELECT id FROM clientes WHERE cedula = '999000101'");
$cli2 = (int) v("SELECT id FROM clientes WHERE cedula = '999000102'");
$cr = new Credito();
$base = ['monto' => 100000.0, 'interes' => 20.0, 'num_cuotas' => 10, 'frecuencia' => 'diaria', 'excluir_domingos' => true,
    'fecha_inicio' => date('Y-m-d'), 'fecha_primera_cuota' => date('Y-m-d', strtotime('+1 day')), 'observaciones' => ''];
$cred1 = $cr->crear(['cliente_id' => $cli1] + $base, $adminId);
$cred2 = $cr->crear(['cliente_id' => $cli2] + $base, $adminId);
$pagoCob2 = (new App\Models\Pago())->registrar($cred2, 12000, 'efectivo', $cob2, date('Y-m-d H:i:s'), null);

$admin = new Http($BASE);
$admin->login('sectest_admin', $PASS);
$c1 = new Http($BASE);
$c1->login('sectest_cob1', $PASS);

// ================================================================ 1. Exposición
section('1. Configuración y exposición de información');
$anon = new Http($BASE);
foreach (['.env' => 'S01', '.env.example' => 'S02', 'database/schema.sql' => 'S03', 'storage/logs/php-error.log' => 'S04',
             'app/config/config.php' => 'S05', 'README.md' => 'S06', 'tests/seguridad.php' => 'S07', '.htaccess' => 'S08'] as $f => $id) {
    $r = $anon->get($f);
    check($id, "Archivo interno no accesible: /$f", in_array($r['code'], [403, 404], true), "HTTP {$r['code']}");
}
$r = $anon->get('assets/');
check('S09', 'Listado de directorios deshabilitado (/assets/)', $r['code'] === 403 || !str_contains($r['body'], 'Index of'), "HTTP {$r['code']}");

$r = $anon->get('auth/login');
$h = $r['headers'];
check('S10', 'No revela versión de PHP (X-Powered-By)', !isset($h['x-powered-by']), $h['x-powered-by'][0] ?? '');
check('S11', 'Cabecera X-Frame-Options (anti-clickjacking)', isset($h['x-frame-options']));
check('S12', 'Cabecera X-Content-Type-Options: nosniff', strtolower($h['x-content-type-options'][0] ?? '') === 'nosniff');
check('S13', 'Cabecera Content-Security-Policy', isset($h['content-security-policy']));
check('S14', 'Cabecera Referrer-Policy', isset($h['referrer-policy']));
check('S15', 'Cabecera Permissions-Policy', isset($h['permissions-policy']));
$sc = implode(' ', $h['set-cookie'] ?? []);
check('S16', 'Cookie de sesión HttpOnly', stripos($sc, 'httponly') !== false, $sc);
check('S17', 'Cookie de sesión SameSite', stripos($sc, 'samesite') !== false, $sc);
$csp = $h['content-security-policy'][0] ?? '';
check('S18', 'CSP no permite scripts inline arbitrarios (sin \'unsafe-inline\' en script-src)',
    $csp !== '' && !preg_match("/script-src[^;]*'unsafe-inline'/", $csp), $csp ?: 'sin CSP');
$r = $admin->get('dashboard');
check('S19', 'Páginas privadas no se guardan en caché (Cache-Control no-store)', str_contains(strtolower(implode(',', $r['headers']['cache-control'] ?? [])), 'no-store'));

// ================================================================ 2. Autenticación
section('2. Autenticación y sesiones');
$a = new Http($BASE);
$r1 = $a->login('sectest_cob1', 'ClaveIncorrecta#1');
$m1 = $a->get($r1['location'] ?: 'auth/login')['body'];
$b = new Http($BASE);
$r2 = $b->login('noexiste_' . bin2hex(random_bytes(3)), 'ClaveIncorrecta#1');
$m2 = $b->get($r2['location'] ?: 'auth/login')['body'];
$msg = fn ($html) => preg_match('/aviso-titulo">[^<]*<\/div>([^<]+)/', $html, $m) ? trim($m[1]) : '';
check('A01', 'Mismo mensaje para usuario inexistente y clave errada (sin enumeración)', $msg($m1) !== '' && $msg($m1) === $msg($m2), $msg($m1) . ' | ' . $msg($m2));

// Fuerza bruta: 6 intentos fallidos, cada uno con una sesión NUEVA (como hace un atacante)
for ($i = 0; $i < 6; $i++) {
    (new Http($BASE))->login('sectest_brute', 'Mala#Clave' . $i);
}
$t = new Http($BASE);
$t->login('sectest_brute', $PASS);
check('A02', 'Bloqueo por fuerza bruta aunque el atacante cambie de sesión/cookie', !loggedIn($t->get('dashboard')),
    'la clave correcta funcionó tras 6 fallos con sesiones nuevas');
check('A03', 'Los intentos fallidos quedan registrados en BD', (int) (function () { try { return v("SELECT COUNT(*) FROM intentos_login WHERE usuario = 'sectest_brute'"); } catch (Throwable) { return 0; } })() >= 5);

$t = new Http($BASE);
$t->login('sectest_inact', $PASS);
check('A04', 'Usuario inactivo no puede iniciar sesión', !loggedIn($t->get('dashboard')));

$t = new Http($BASE);
$t->get('auth/login');
$antes = $t->sessionId();
$t->login('sectest_cob2', $PASS);
check('A05', 'El ID de sesión cambia al iniciar sesión (anti session-fixation)', $antes !== '' && $t->sessionId() !== $antes);

$t = new Http($BASE);
$t->login('sectest_cob2', $PASS);
$sid = $t->sessionId();
$t->post('auth/logout', ['_csrf' => $t->token('dashboard')]);
$robada = new Http($BASE);
$robada->setSessionId($sid);
check('A06', 'Tras cerrar sesión, la cookie anterior ya no sirve', !loggedIn($robada->get('dashboard')));

$v = new Http($BASE);
$v->login('sectest_victim', $PASS);
$ok1 = loggedIn($v->get('ruta'));
$admin->post('usuarios/estado/' . v("SELECT id FROM usuarios WHERE usuario='sectest_victim'"), ['_csrf' => $admin->token('usuarios')]);
check('A07', 'Al desactivar un usuario se cierra su sesión activa de inmediato', $ok1 && !loggedIn($v->get('ruta')));

$rr = new Http($BASE);
$rr->login('sectest_rol', $PASS);
x("UPDATE usuarios SET rol = 'cobrador' WHERE usuario = 'sectest_rol'");
$r = $rr->get('usuarios');
check('A08', 'Un cambio de rol (admin→cobrador) aplica de inmediato a la sesión abierta', $r['code'] !== 200, "HTTP {$r['code']}");

$weak = ['123456', 'password', 'admin123', 'Abcdefgh', 'abcdefgh1!', 'Corta#1'];
$creados = 0;
foreach ($weak as $i => $w) {
    $admin->post('usuarios/guardar', ['_csrf' => $admin->token('usuarios/crear'), 'rol' => 'cobrador', 'nombre' => 'Debil Prueba',
        'cedula' => '9990009' . $i, 'telefono' => '3000000000', 'usuario' => 'sectest_weak' . $i, 'password' => $w, 'activo' => 1]);
    $creados += (int) v('SELECT COUNT(*) FROM usuarios WHERE usuario = ?', ['sectest_weak' . $i]);
}
check('A09', 'Rechaza contraseñas débiles al crear usuarios (' . implode(', ', $weak) . ')', $creados === 0, "$creados aceptadas");
$admin->post('usuarios/guardar', ['_csrf' => $admin->token('usuarios/crear'), 'rol' => 'cobrador', 'nombre' => 'Igual Usuario',
    'cedula' => '99900099', 'telefono' => '3000000000', 'usuario' => 'sectest_juan', 'password' => 'Sectest_juan#2026', 'activo' => 1]);
check('A10', 'Rechaza contraseñas que contienen el nombre de usuario', (int) v("SELECT COUNT(*) FROM usuarios WHERE usuario='sectest_juan'") === 0);

$p = new Http($BASE);
$p->login('sectest_cob2', $PASS);
$p->post('perfil/clave', ['_csrf' => $p->token('perfil'), 'actual' => $PASS, 'nueva' => 'abc12345', 'confirmar' => 'abc12345']);
check('A11', 'Rechaza contraseña débil al cambiarla desde Mi perfil', password_verify($PASS, (string) v("SELECT password FROM usuarios WHERE usuario='sectest_cob2'")));

$admin->post('usuarios/guardar', ['_csrf' => $admin->token('usuarios/crear'), 'rol' => 'cobrador', 'nombre' => 'Fuerte Prueba',
    'cedula' => '99900098', 'telefono' => '3000000000', 'usuario' => 'sectest_fuerte', 'password' => 'Ruta#Norte_2026x', 'activo' => 1]);
$hf = (string) v("SELECT password FROM usuarios WHERE usuario='sectest_fuerte'");
check('A12', 'Acepta contraseñas fuertes', $hf !== '');
check('A13', 'Hash de contraseña con Argon2id', str_starts_with($hf, '$argon2id$'), substr($hf, 0, 12));
$flag = (function () { try { return (int) v("SELECT debe_cambiar_clave FROM usuarios WHERE usuario='sectest_fuerte'"); } catch (Throwable) { return -1; } })();
check('A14', 'Usuario creado por el admin debe cambiar la clave en su primer ingreso', $flag === 1, "valor: $flag");
$f = new Http($BASE);
$f->login('sectest_fuerte', 'Ruta#Norte_2026x');
$r = $f->get('ruta');
check('A15', 'Con cambio de clave pendiente solo se permite ir a Mi perfil', $r['code'] === 302 && str_contains($r['location'], 'perfil'), "HTTP {$r['code']} {$r['location']}");

// ================================================================ 3. Autorización
section('3. Control de acceso (roles e IDOR)');
$anon = new Http($BASE);
$priv = ['dashboard', 'clientes', 'clientes/ver/' . $cli1, 'creditos', 'creditos/ver/' . $cred1, 'pagos', 'pagos/recibo/' . $pagoCob2,
    'ruta', 'cobradores', 'reportes', 'reportes/exportar/clientes', 'usuarios', 'configuracion', 'perfil', 'creditos/simular?monto=1000'];
$fallos = [];
foreach ($priv as $u) {
    $r = $anon->get($u);
    if (!($r['code'] === 302 && str_contains($r['location'], 'auth/login'))) {
        $fallos[] = "$u ({$r['code']})";
    }
}
check('Z01', 'Sin sesión, todas las rutas privadas redirigen al login', !$fallos, implode(', ', $fallos));

$fallos = [];
foreach (['usuarios', 'usuarios/crear', 'reportes', 'reportes/exportar/cartera', 'configuracion', 'cobradores', 'creditos/crear', 'creditos/simular?monto=100000'] as $u) {
    $r = $c1->get($u);
    if ($r['code'] === 200) {
        $fallos[] = $u;
    }
}
check('Z02', 'Cobrador no accede a módulos de administrador', !$fallos, implode(', ', $fallos));

$tok = $c1->token('ruta');
$c1->post('usuarios/guardar', ['_csrf' => $tok, 'rol' => 'admin', 'nombre' => 'Escalada', 'usuario' => 'sectest_escalada', 'password' => 'Escalada#2026x', 'activo' => 1]);
check('Z03', 'Cobrador no puede crearse un usuario administrador (escalada de privilegios)', (int) v("SELECT COUNT(*) FROM usuarios WHERE usuario='sectest_escalada'") === 0);
$c1->post('configuracion/guardar', ['_csrf' => $tok, 'empresa_nombre' => 'HACKEADO']);
check('Z04', 'Cobrador no puede cambiar la configuración', v("SELECT valor FROM configuracion WHERE clave='empresa_nombre'") !== 'HACKEADO');
$c1->post('pagos/anular/' . $pagoCob2, ['_csrf' => $tok, 'motivo' => 'x']);
check('Z05', 'Cobrador no puede anular pagos', (int) v('SELECT anulado FROM pagos WHERE id = ?', [$pagoCob2]) === 0);
$c1->post('clientes/eliminar/' . $cli1, ['_csrf' => $tok]);
check('Z06', 'Cobrador no puede eliminar clientes', (int) v('SELECT COUNT(*) FROM clientes WHERE id = ?', [$cli1]) === 1);

$fallos = [];
foreach (['clientes/ver/' . $cli2, 'clientes/editar/' . $cli2, 'creditos/ver/' . $cred2, 'pagos/recibo/' . $pagoCob2, 'cobradores/ver/' . $cob2] as $u) {
    $r = $c1->get($u);
    if ($r['code'] === 200 && (str_contains($r['body'], 'DeCob2') || str_contains($r['body'], 'Test sectest_cob2'))) {
        $fallos[] = $u;
    }
}
check('Z07', 'IDOR: un cobrador no ve clientes, créditos, recibos ni cartera de otro', !$fallos, implode(', ', $fallos));

$antes = (int) v('SELECT COUNT(*) FROM pagos WHERE credito_id = ?', [$cred2]);
$c1->post('pagos/guardar', ['_csrf' => $tok, 'credito_id' => $cred2, 'monto' => '5000', 'metodo' => 'efectivo']);
check('Z08', 'IDOR: un cobrador no puede registrar pagos en créditos ajenos', (int) v('SELECT COUNT(*) FROM pagos WHERE credito_id = ?', [$cred2]) === $antes);
$c1->post('clientes/actualizar/' . $cli2, ['_csrf' => $tok, 'cedula' => '999000102', 'nombres' => 'Modificado', 'apellidos' => 'X', 'telefono1' => '3001112233', 'direccion' => 'x']);
check('Z09', 'IDOR: un cobrador no puede editar clientes ajenos', v('SELECT nombres FROM clientes WHERE id = ?', [$cli2]) === 'Cliente');

$c1->post('clientes/guardar', ['_csrf' => $tok, 'cedula' => '999000103', 'nombres' => 'Asignacion', 'apellidos' => 'Masiva',
    'telefono1' => '3001234567', 'direccion' => 'Calle 3', 'cobrador_id' => $cob2, 'estado' => 'bloqueado']);
$row = q("SELECT cobrador_id, estado FROM clientes WHERE cedula = '999000103'")[0] ?? null;
check('Z10', 'Asignación masiva de campos: el cobrador no puede asignar clientes a otro ni cambiar su estado',
    $row && (int) $row['cobrador_id'] === $cob1 && $row['estado'] === 'activo', json_encode($row));
$c1->post('pagos/guardar', ['_csrf' => $tok, 'credito_id' => $cred1, 'monto' => '1000', 'metodo' => 'efectivo', 'fecha' => '2020-01-01', 'cobrador_id' => $cob2]);
$pg = q('SELECT DATE(fecha) AS f, cobrador_id FROM pagos WHERE credito_id = ? ORDER BY id DESC LIMIT 1', [$cred1])[0] ?? null;
check('Z11', 'El cobrador no puede antedatar pagos ni registrarlos a nombre de otro', $pg && $pg['f'] === date('Y-m-d') && (int) $pg['cobrador_id'] === $cob1, json_encode($pg));

// ================================================================ 4. Validación de entradas
section('4. Inyección, XSS, CSRF y validación');
$antes = (int) v('SELECT COUNT(*) FROM clientes');
$admin->post('clientes/guardar', ['cedula' => '999000110', 'nombres' => 'Sin', 'apellidos' => 'Token', 'telefono1' => '3001234567', 'direccion' => 'x']);
check('I01', 'CSRF: rechaza formularios sin token', (int) v('SELECT COUNT(*) FROM clientes') === $antes);
$otro = new Http($BASE);
$tokAjeno = $otro->token('auth/login');
$admin->post('clientes/guardar', ['_csrf' => $tokAjeno, 'cedula' => '999000111', 'nombres' => 'Token', 'apellidos' => 'Ajeno', 'telefono1' => '3001234567', 'direccion' => 'x']);
check('I02', 'CSRF: rechaza el token de otra sesión', (int) v('SELECT COUNT(*) FROM clientes') === $antes);
$r = $admin->get('pagos/anular/' . $pagoCob2);
check('I03', 'Acciones que modifican datos no se ejecutan por GET', (int) v('SELECT anulado FROM pagos WHERE id = ?', [$pagoCob2]) === 0 && $r['code'] !== 200, "HTTP {$r['code']}");

$payloads = ["' OR '1'='1", "' OR 1=1 -- -", '" OR ""="', "1' UNION SELECT usuario,password,1,1,1 FROM usuarios -- -", "'; DROP TABLE clientes; --", '%27%20OR%201=1'];
$fallos = [];
$normal = substr_count($c1->get('clientes')['body'], 'clientes/ver/');
foreach (['clientes', 'creditos', 'pagos', 'usuarios'] as $mod) {
    foreach ($payloads as $pl) {
        $who = $mod === 'usuarios' ? $admin : $c1;
        $r = $who->get($mod . '?q=' . rawurlencode($pl) . '&desde=' . rawurlencode($pl) . '&estado=' . rawurlencode($pl));
        if ($r['code'] >= 500 || leaks($r['body']) || str_contains($r['body'], '$2y$') || str_contains($r['body'], '$argon2')) {
            $fallos[] = "$mod:$pl";
        }
    }
}
$sqlRuta = $c1->get('clientes?q=' . rawurlencode("' OR '1'='1"));
check('I04', 'Inyección SQL en filtros de búsqueda (24 combinaciones): sin errores ni fuga de datos', !$fallos, implode(' | ', $fallos));
check('I05', 'Inyección SQL no amplía lo que ve un cobrador', substr_count($sqlRuta['body'], 'clientes/ver/') <= $normal);
$fallos = [];
foreach (["clientes/ver/1'%20OR%20'1'='1", 'creditos/ver/0%20OR%201=1', 'pagos/recibo/-1', 'clientes/ver/99999999999999999999999', "reportes/exportar/pagos?desde=2020-01-01'%20OR%201=1"] as $u) {
    $r = $admin->get($u);
    if ($r['code'] >= 500 || leaks($r['body'])) {
        $fallos[] = $u . " ({$r['code']})";
    }
}
check('I06', 'Inyección en parámetros de la URL: sin errores 500 ni mensajes internos', !$fallos, implode(', ', $fallos));
check('I07', 'La tabla clientes sigue intacta tras los intentos de DROP', (int) v('SELECT COUNT(*) FROM clientes') >= 3);

$xss = '<script>alert(1)</script>';
$admin->post('clientes/guardar', ['_csrf' => $admin->token('clientes/crear'), 'cedula' => '999000120', 'nombres' => $xss, 'apellidos' => '"><img src=x onerror=alert(1)>',
    'telefono1' => '3001234567', 'direccion' => "<svg onload=alert(1)>", 'observaciones' => 'javascript:alert(1)', 'cobrador_id' => $cob1]);
$idx = (int) v("SELECT id FROM clientes WHERE cedula='999000120'");
$fallos = [];
foreach (['clientes', 'clientes/ver/' . $idx, 'clientes/editar/' . $idx, 'cobradores/ver/' . $cob1, 'ruta'] as $u) {
    $body = $admin->get($u)['body'];
    if (preg_match('/<script>alert\(1\)<\/script>|<img src=x onerror|<svg onload/i', $body)) {
        $fallos[] = $u;
    }
}
check('I08', 'XSS almacenado: los datos se muestran escapados en todas las vistas', $idx > 0 && !$fallos, $idx ? implode(', ', $fallos) : 'no se creó el cliente');
$r = $admin->get('clientes?q=' . rawurlencode('"><script>alert(9)</script>'));
check('I09', 'XSS reflejado en parámetros de búsqueda', !str_contains($r['body'], '<script>alert(9)</script>'));

$fallos = [];
foreach (['-5000', '0', 'abc', '0.0001', '99999999999'] as $m) {
    $before = (int) v('SELECT COUNT(*) FROM pagos WHERE credito_id = ?', [$cred1]);
    $admin->post('pagos/guardar', ['_csrf' => $admin->token('creditos/ver/' . $cred1), 'credito_id' => $cred1, 'monto' => $m, 'metodo' => 'efectivo']);
    if ((int) v('SELECT COUNT(*) FROM pagos WHERE credito_id = ?', [$cred1]) !== $before) {
        $fallos[] = $m;
    }
}
check('I10', 'Pagos con valores negativos, cero, texto o mayores al saldo son rechazados', !$fallos, implode(', ', $fallos));
$saldo = (float) v('SELECT saldo FROM creditos WHERE id = ?', [$cred1]);
$pagado = (float) v('SELECT COALESCE(SUM(monto),0) FROM pagos WHERE credito_id = ? AND anulado = 0', [$cred1]);
check('I11', 'Integridad: saldo = total − pagos vigentes', abs((float) v('SELECT total FROM creditos WHERE id=?', [$cred1]) - $pagado - $saldo) < 0.01);

$admin->post('creditos/guardar', ['_csrf' => $admin->token('creditos/crear'), 'cliente_id' => $cli1, 'monto' => '100000', 'interes' => '-50',
    'num_cuotas' => '100000', 'frecuencia' => 'anual', 'fecha_inicio' => '2026-02-30']);
check('I12', 'Créditos con interés negativo, cuotas absurdas o fechas inválidas son rechazados', (int) v('SELECT COUNT(*) FROM creditos WHERE cliente_id = ?', [$cli1]) === 1);

$admin->post('clientes/guardar', ['_csrf' => $admin->token('clientes/crear'), 'cedula' => '999000130', 'nombres' => '=HYPERLINK("http://evil","x")',
    'apellidos' => '+cmd|calc', 'telefono1' => '3001234567', 'direccion' => '@SUM(1)', 'cobrador_id' => $cob1]);
$xr = $admin->get('reportes/exportar/clientes');
$zip = zipEntries($xr['body']);
$sheet = $zip['xl/worksheets/sheet1.xml'] ?? '';
check('I13', 'Excel: un texto como =HYPERLINK(...) se guarda como texto literal, nunca como fórmula',
    $sheet !== '' && stripos($sheet, '=Hyperlink') !== false && !preg_match('/<f>[^<]*HYPERLINK/i', $sheet), $sheet === '' ? 'no es un xlsx válido' : '');

$admin->post('clientes/guardar', ['_csrf' => $admin->token('clientes/crear'), 'cedula' => '999000140', 'nombres' => str_repeat('A', 20000),
    'apellidos' => 'Largo', 'telefono1' => '3001234567', 'direccion' => str_repeat('B', 50000), 'cobrador_id' => $cob1]);
$len = (int) v("SELECT CHAR_LENGTH(nombres) FROM clientes WHERE cedula='999000140'");
check('I14', 'Entradas muy largas se recortan sin errores', $len > 0 && $len <= 80, "longitud: $len");

$r = $admin->post('clientes/asignar', ['_csrf' => $admin->token('clientes')], ['Referer: http://localhost/ApexCobros.evil.com/robar']);
check('I15', 'Sin redirección abierta a dominios externos (cabecera Referer manipulada)', !str_contains($r['location'], 'evil'), $r['location']);
$r2 = $admin->post('clientes/asignar', ['_csrf' => $admin->token('clientes')], ['Referer: http://evil.com/ApexCobros']);
check('I16', 'Sin redirección abierta con Referer de otro host', !str_contains($r2['location'], 'evil'), $r2['location']);

$fallos = [];
foreach (['clientes/cargar/1', 'clientes/datos', 'clientes/validar', 'clientes/__construct', 'usuarios/fallo/x/y', '..%2f..%2fapp%2fconfig', 'index.php?url=../../app/config/config', 'Clientes/VER/1%00'] as $u) {
    $r = $admin->get($u);
    if ($r['code'] === 200 && !str_contains($r['body'], 'auth/logout') || $r['code'] >= 500 || leaks($r['body'])) {
        $fallos[] = "$u ({$r['code']})";
    }
}
check('I17', 'Router: no ejecuta métodos internos ni permite recorrer rutas', !$fallos, implode(', ', $fallos));

$fallos = [];
foreach (['clientes?q[]=x&estado[]=1', 'pagos?desde[]=1&hasta[x]=1', 'creditos?mora[]=1&q[a]=b', 'ruta?fecha[]=2026-01-01', 'auditoria?accion[]=x'] as $u) {
    $r = $admin->get($u);
    if ($r['code'] >= 500 || leaks($r['body']) || str_contains($r['body'], 'Error inesperado')) {
        $fallos[] = $u;
    }
}
$admin->post('auth/login', ['_csrf' => ['x'], 'usuario' => ['admin'], 'password' => ['x']]);
check('I18', 'Parámetros enviados como arreglo (type juggling) no rompen la aplicación', !$fallos, implode(', ', $fallos));

// ================================================================ 6. Funciones v1.2
section('6. Caja, gestiones, renovaciones, documentos y respaldo');
$c2 = new Http($BASE);
$c2->login('sectest_cob2', $PASS);
$tok1 = $c1->token('caja');

// Caja
$c1->post('caja/cerrar', ['_csrf' => $tok1, 'fecha' => date('Y-m-d'), 'cobrador_id' => $cob1, 'entregado' => '0']);
check('F01', 'Cobrador no puede cerrar su propia caja (solo el administrador recibe el dinero)', (int) v('SELECT COUNT(*) FROM cierres WHERE cobrador_id = ?', [$cob1]) === 0);
$c1->post('caja/gasto', ['_csrf' => $tok1, 'cobrador_id' => $cob2, 'concepto' => 'transporte', 'valor' => '12.000', 'fecha' => date('Y-m-d')]);
check('F02', 'Gasto registrado por un cobrador siempre queda a su nombre (no a nombre de otro)',
    (int) v('SELECT COUNT(*) FROM gastos WHERE cobrador_id = ?', [$cob2]) === 0 && (int) v('SELECT COUNT(*) FROM gastos WHERE cobrador_id = ?', [$cob1]) === 1);
$c1->post('caja/gasto', ['_csrf' => $tok1, 'concepto' => 'transporte', 'valor' => '5000', 'fecha' => '2020-01-01']);
check('F03', 'Cobrador no puede registrar gastos con fechas pasadas', (int) v("SELECT COUNT(*) FROM gastos WHERE cobrador_id = ? AND fecha = '2020-01-01'", [$cob1]) === 0);
$antes = (int) v('SELECT COUNT(*) FROM gastos');
foreach (['-5000', '0', 'abc', '1.5', '99999999'] as $val) {
    $c1->post('caja/gasto', ['_csrf' => $tok1, 'concepto' => 'transporte', 'valor' => $val]);
}
$c1->post('caja/gasto', ['_csrf' => $tok1, 'concepto' => 'hackeo', 'valor' => '1000']);
check('F04', 'Gastos con valores negativos, cero, texto, decimales, exagerados o concepto inválido son rechazados', (int) v('SELECT COUNT(*) FROM gastos') === $antes);
x('INSERT INTO gastos (cobrador_id, fecha, concepto, valor, registrado_por, created_at) VALUES (?, CURDATE(), \'otro\', 7000, ?, NOW())', [$cob2, $cob2]);
$gasto2 = (int) v('SELECT id FROM gastos WHERE cobrador_id = ?', [$cob2]);
$c1->post('caja/anular-gasto/' . $gasto2, ['_csrf' => $tok1]);
check('F05', 'IDOR: un cobrador no puede anular gastos de otro', (int) v('SELECT anulado FROM gastos WHERE id = ?', [$gasto2]) === 0);
$admin->post('caja/cerrar', ['_csrf' => $admin->token('caja'), 'fecha' => date('Y-m-d'), 'cobrador_id' => $cob1, 'entregado' => '-500']);
$admin->post('caja/cerrar', ['_csrf' => $admin->token('caja'), 'fecha' => date('Y-m-d', strtotime('+1 day')), 'cobrador_id' => $cob1, 'entregado' => '1000']);
check('F06', 'Cierre con valor entregado inválido o fecha futura es rechazado', (int) v('SELECT COUNT(*) FROM cierres WHERE cobrador_id = ?', [$cob1]) === 0);
$esperado = 1000.0 - 12000.0; // pago Z11 en efectivo − gasto F02
$admin->post('caja/cerrar', ['_csrf' => $admin->token('caja'), 'fecha' => date('Y-m-d'), 'cobrador_id' => $cob1, 'entregado' => '0']);
$ci = q('SELECT a_entregar, entregado, diferencia FROM cierres WHERE cobrador_id = ?', [$cob1])[0] ?? null;
check('F07', 'Cierre de caja calcula efectivo − gastos y registra reembolso cuando los gastos superan lo cobrado',
    $ci && abs((float) $ci['a_entregar'] - $esperado) < 1 && abs((float) $ci['diferencia']) < 1, json_encode($ci));
$mano = (new App\Models\Caja())->enMano($cob1)[$cob1]['en_mano'] ?? null;
check('F08', 'Efectivo en mano queda en cero tras el cierre (libro de caja cuadrado)', $mano !== null && abs((float) $mano) < 1, 'en mano: ' . $mano);

// Gestiones
$c1->post('gestiones/guardar', ['_csrf' => $tok1, 'credito_id' => $cred2, 'tipo' => 'visita', 'resultado' => 'no_estaba']);
check('F09', 'IDOR: un cobrador no puede registrar gestiones en créditos de otro', (int) v('SELECT COUNT(*) FROM gestiones WHERE credito_id = ?', [$cred2]) === 0);
$c1->post('gestiones/guardar', ['_csrf' => $tok1, 'credito_id' => $cred1, 'tipo' => 'visita', 'resultado' => 'promesa', 'fecha_promesa' => '2020-01-01', 'monto_promesa' => '1000']);
$c1->post('gestiones/guardar', ['_csrf' => $tok1, 'credito_id' => $cred1, 'tipo' => 'visita', 'resultado' => 'promesa', 'fecha_promesa' => date('Y-m-d', strtotime('+3 days')), 'monto_promesa' => '999.999.999']);
$c1->post('gestiones/guardar', ['_csrf' => $tok1, 'credito_id' => $cred1, 'tipo' => 'hack', 'resultado' => 'inventado']);
check('F10', 'Gestiones con promesa en el pasado, valor mayor al saldo o tipos inválidos son rechazadas', (int) v('SELECT COUNT(*) FROM gestiones WHERE credito_id = ?', [$cred1]) === 0);
$c1->post('gestiones/guardar', ['_csrf' => $tok1, 'credito_id' => $cred1, 'tipo' => 'llamada', 'resultado' => 'promesa', 'fecha_promesa' => date('Y-m-d'), 'monto_promesa' => '2.000', 'nota' => '<script>alert(5)</script>']);
$c1->post('pagos/guardar', ['_csrf' => $tok1, 'credito_id' => $cred1, 'monto' => '2.000', 'metodo' => 'efectivo']);
$est = q("SELECT estado_promesa FROM (SELECT g.id, CASE WHEN (SELECT COALESCE(SUM(p.monto),0) FROM pagos p WHERE p.credito_id = g.credito_id AND p.anulado = 0 AND p.metodo <> 'renovacion' AND p.fecha >= g.created_at AND p.fecha < DATE_ADD(g.fecha_promesa, INTERVAL 1 DAY)) >= COALESCE(NULLIF(g.monto_promesa,0),1) THEN 'cumplida' ELSE 'pendiente' END AS estado_promesa FROM gestiones g WHERE g.credito_id = ?) t", [$cred1])[0]['estado_promesa'] ?? '';
check('F11', 'Promesa de pago se marca cumplida automáticamente al recibir el pago', $est === 'cumplida', $est);
check('F12', 'La nota de la gestión (XSS) se muestra escapada', !str_contains($admin->get('creditos/ver/' . $cred1)['body'], '<script>alert(5)</script>'));

// Renovaciones
$c1->get('creditos/renovar/' . $cred1);
$antes = (int) v('SELECT COUNT(*) FROM creditos WHERE cliente_id = ?', [$cli1]);
$c1->post('creditos/guardar', ['_csrf' => $tok1, 'renovar_id' => $cred1, 'monto' => '500000', 'interes' => '20', 'num_cuotas' => '10', 'frecuencia' => 'diaria',
    'fecha_inicio' => date('Y-m-d'), 'fecha_primera_cuota' => date('Y-m-d', strtotime('+1 day'))]);
check('F13', 'Cobrador no puede renovar créditos', (int) v('SELECT COUNT(*) FROM creditos WHERE cliente_id = ?', [$cli1]) === $antes);
$saldo1 = (float) v('SELECT saldo FROM creditos WHERE id = ?', [$cred1]);
$admin->post('creditos/guardar', ['_csrf' => $admin->token('creditos/renovar/' . $cred1), 'renovar_id' => $cred1, 'monto' => (string) (int) ($saldo1 - 1000),
    'interes' => '20', 'num_cuotas' => '10', 'frecuencia' => 'diaria', 'fecha_inicio' => date('Y-m-d'), 'fecha_primera_cuota' => date('Y-m-d', strtotime('+1 day'))]);
check('F14', 'Renovación con monto menor o igual al saldo pendiente es rechazada', (int) v('SELECT COUNT(*) FROM creditos WHERE cliente_id = ?', [$cli1]) === $antes);
$admin->post('creditos/guardar', ['_csrf' => $admin->token('creditos/renovar/' . $cred1), 'renovar_id' => $cred1, 'cliente_id' => $cli2, 'monto' => '300000',
    'interes' => '20', 'num_cuotas' => '10', 'frecuencia' => 'diaria', 'fecha_inicio' => date('Y-m-d'), 'fecha_primera_cuota' => date('Y-m-d', strtotime('+1 day'))]);
$nuevo = q('SELECT id, cliente_id, saldo_renovado, neto_entregado FROM creditos WHERE renovado_de = ?', [$cred1])[0] ?? null;
$ant = q('SELECT estado, saldo FROM creditos WHERE id = ?', [$cred1])[0];
check('F15', 'Renovación: cancela el saldo anterior, descuenta el neto y no permite cambiar de cliente',
    $nuevo && (int) $nuevo['cliente_id'] === $cli1 && $ant['estado'] === 'pagado' && abs((float) $nuevo['saldo_renovado'] - $saldo1) < 1
    && abs((float) $nuevo['neto_entregado'] - (300000 - $saldo1)) < 1, json_encode([$nuevo, $ant]));
$pRen = (int) v("SELECT id FROM pagos WHERE credito_id = ? AND metodo = 'renovacion'", [$cred1]);
$admin->post('pagos/anular/' . $pRen, ['_csrf' => $admin->token('creditos/ver/' . $cred1), 'motivo' => 'prueba']);
check('F16', 'El movimiento de renovación no se puede anular suelto (evita cobrar dos veces la misma deuda)', (int) v('SELECT anulado FROM pagos WHERE id = ?', [$pRen]) === 0);
$recaudo = (new App\Models\Pago())->totales(['desde' => date('Y-m-d'), 'hasta' => date('Y-m-d'), 'cobrador_id' => $adminId]);
check('F17', 'La renovación no se cuenta como dinero recaudado', (float) $recaudo['total'] === 0.0, json_encode($recaudo));
if ($nuevo) {
    $admin->post('creditos/anular/' . $nuevo['id'], ['_csrf' => $admin->token('creditos/ver/' . $nuevo['id']), 'motivo' => 'Renovación por error']);
}
$ant2 = q('SELECT estado, saldo FROM creditos WHERE id = ?', [$cred1])[0];
check('F18', 'Anular la renovación revive el crédito anterior con su saldo exacto', $ant2['estado'] === 'activo' && abs((float) $ant2['saldo'] - $saldo1) < 1, json_encode($ant2));

// Documentos e IDOR
$fallos = [];
foreach (['creditos/estado/' . $cred2, 'creditos/excel/' . $cred2, 'creditos/pazysalvo/' . $cred2, 'creditos/renovar/' . $cred2] as $u) {
    $r = $c1->get($u);
    if ($r['code'] === 200 && (str_contains($r['body'], 'DeCob2') || str_starts_with($r['body'], 'PK'))) {
        $fallos[] = $u;
    }
}
check('F19', 'IDOR: un cobrador no descarga estado de cuenta, Excel ni paz y salvo de créditos ajenos', !$fallos, implode(', ', $fallos));
$r = $admin->get('creditos/pazysalvo/' . $cred2);
check('F20', 'Paz y salvo solo se expide con el crédito totalmente pagado', $r['code'] === 302);
$r = $c2->get('creditos/excel/' . $cred2);
$z = zipEntries($r['body']);
check('F21', 'El estado de cuenta en Excel es un .xlsx válido (contenedor, libro, hojas y estilos)',
    isset($z['[Content_Types].xml'], $z['xl/workbook.xml'], $z['xl/styles.xml'], $z['xl/worksheets/sheet1.xml'], $z['xl/worksheets/sheet2.xml'])
    && str_contains($r['headers']['content-type'][0] ?? '', 'spreadsheetml'));

// Respaldo
$r1 = $c1->get('respaldo');
$r2 = $c1->post('respaldo/descargar', ['_csrf' => $tok1]);
check('F22', 'Cobrador no accede al respaldo de la base de datos', $r1['code'] === 302 && !str_contains($r2['body'], 'CREATE TABLE'));
$r = $admin->get('respaldo/descargar');
check('F23', 'El respaldo solo se descarga por POST con token CSRF (no por un enlace)', !str_contains($r['body'], 'CREATE TABLE'), "HTTP {$r['code']}");
$r = $admin->post('respaldo/descargar', ['_csrf' => $admin->token('respaldo')]);
check('F24', 'Respaldo del administrador contiene la estructura y los datos de todas las tablas',
    str_contains($r['body'], 'CREATE TABLE `pagos`') && str_contains($r['body'], 'CREATE TABLE `cierres`') && str_contains($r['body'], 'INSERT INTO `usuarios`'));
check('F25', 'La descarga del respaldo queda en la auditoría', (int) v("SELECT COUNT(*) FROM auditoria WHERE accion = 'respaldo' AND usuario_id = ?", [$adminId]) === 1);

// Ayuda (manuales)
$r = (new Http($BASE))->get('ayuda/ver/administrador');
check('F26', 'Los manuales no se pueden descargar sin iniciar sesión', $r['code'] === 302 && !str_starts_with($r['body'], '%PDF'));
$r1 = $c1->get('ayuda/ver/tecnico');
$r2 = $c1->get('ayuda/ver/cobrador');
check('F27', 'El cobrador no puede abrir manuales de administrador (sí el suyo)', !str_starts_with($r1['body'], '%PDF')
    && (str_starts_with($r2['body'], '%PDF') || !is_file(BASE_PATH . '/docs/APEX_02_Manual_del_Cobrador.pdf')));
$fallos = [];
foreach (['ayuda/ver/..%2f..%2f.env', 'ayuda/ver/../../app/config/config.php', 'docs/APEX_01_Manual_del_Administrador.pdf', 'ayuda/ver/ADMINISTRADOR'] as $u) {
    $r = $admin->get($u);
    if (str_contains($r['body'], 'DB_PASS') || str_contains($r['body'], '<?php') || ($u[0] === 'd' && $r['code'] === 200)) {
        $fallos[] = $u;
    }
}
check('F28', 'No se pueden obtener otros archivos manipulando la ruta de los manuales', !$fallos, implode(', ', $fallos));

// ================================================================ 5. Auditoría
section('5. Trazabilidad');
$aud = (function () { try { return (int) v("SELECT COUNT(*) FROM auditoria WHERE accion IN ('login_ok','login_fallido','usuario_estado','pago_registrado')"); } catch (Throwable) { return -1; } })();
check('T01', 'Se registra bitácora de auditoría (ingresos, fallos, pagos, cambios de usuarios)', $aud > 0, "registros: $aud");
$fuga = false;
foreach (['dashboard', 'clientes', 'creditos/ver/' . $cred1, 'cobradores', 'reportes', 'usuarios'] as $u) {
    $fuga = $fuga || leaks($admin->get($u)['body']);
}
check('T02', 'Ninguna página muestra errores internos de PHP/SQL', !$fuga);

// ---------------------------------------------------------------- resumen
cleanup();
$ok = count(array_filter($results, fn ($r) => $r['ok']));
$total = count($results);
printf("\nResultado: %d de %d pruebas superadas (%d fallas)\n", $ok, $total, $total - $ok);

$md = "# Resultado de pruebas de seguridad · APEX\n\nFecha: " . date('Y-m-d H:i') . " · Objetivo: $BASE\n\n**$ok de $total pruebas superadas.**\n\n";
$sec = '';
foreach ($results as $r) {
    if ($r['section'] !== $sec) {
        $sec = $r['section'];
        $md .= "\n## $sec\n\n| ID | Prueba | Resultado |\n|---|---|---|\n";
    }
    $md .= "| {$r['id']} | " . str_replace('|', '/', $r['desc']) . ' | ' . ($r['ok'] ? '✅ OK' : '❌ FALLA' . ($r['detail'] ? ' (' . str_replace('|', '/', $r['detail']) . ')' : '')) . " |\n";
}
file_put_contents(__DIR__ . '/resultado_seguridad.md', $md);
exit($ok === $total ? 0 : 1);
