<?php
/**
 * APEX · Prueba funcional de punta a punta (flujo completo del negocio por HTTP).
 *
 *   C:\xampp\php\php.exe tests\prueba_funcional.php
 *
 * Crea datos temporales (usuarios "sectest_func*", cédulas 999000…) y los elimina al terminar.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Solo por línea de comandos.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Password;

$BASE = rtrim($argv[1] ?? App\Core\Config::get('app.url'), '/');
$db = Database::connection();

final class Cliente
{
    private array $cookies = [];
    public function __construct(private string $base) {}
    public function req(string $m, string $path, array $data = []): array
    {
        $ch = curl_init($this->base . '/' . ltrim($path, '/'));
        $h = [];
        if ($this->cookies) {
            $h[] = 'Cookie: ' . implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($this->cookies), $this->cookies));
        }
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_CUSTOMREQUEST => $m,
            CURLOPT_HTTPHEADER => $h, CURLOPT_TIMEOUT => 60]);
        if ($m === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        $raw = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $head = substr($raw, 0, $hs);
        foreach (explode("\r\n", $head) as $l) {
            if (stripos($l, 'set-cookie:') === 0 && preg_match('/^set-cookie:\s*([^=]+)=([^;]*)/i', $l, $mm)) {
                $this->cookies[$mm[1]] = $mm[2];
            }
        }
        preg_match('/^location:\s*(.+)$/mi', $head, $loc);
        preg_match('/^content-type:\s*(.+)$/mi', $head, $ct);
        return ['code' => $code, 'body' => substr($raw, $hs), 'location' => trim($loc[1] ?? ''), 'type' => trim($ct[1] ?? '')];
    }
    public function get(string $p): array { return $this->req('GET', $p); }
    public function post(string $p, array $d): array { return $this->req('POST', $p, $d + ['_csrf' => $this->token()]); }
    public function token(): string
    {
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $this->get('perfil')['body'] . $this->get('auth/login')['body'], $m);
        return $m[1] ?? '';
    }
    public function login(string $u, string $p): array
    {
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $this->get('auth/login')['body'], $m);
        return $this->req('POST', 'auth/login', ['_csrf' => $m[1] ?? '', 'usuario' => $u, 'password' => $p]);
    }
}

$ok = $fail = 0;
function paso(string $txt, bool $cond, string $det = ''): void
{
    global $ok, $fail;
    $cond ? $ok++ : $fail++;
    printf("  [%s] %s%s\n", $cond ? 'OK ' : 'FALLA', $txt, !$cond && $det ? "  → $det" : '');
}
function v(string $sql, array $p = []): mixed { global $db; $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn(); }
function zipOk(string $bin): int
{
    $hojas = 0;
    $p = 0;
    while (($p = strpos($bin, "PK\x03\x04", $p)) !== false) {
        $h = unpack('vver/vflag/vmet/vt/vd/Vcrc/Vcs/Vus/vnl/vel', substr($bin, $p + 4, 26));
        $name = substr($bin, $p + 30, $h['nl']);
        $xml = (string) @gzinflate(substr($bin, $p + 30 + $h['nl'] + $h['el'], $h['cs']));
        if (str_starts_with($name, 'xl/worksheets/sheet')) {
            if (@simplexml_load_string($xml) === false) {
                return -1; // XML mal formado
            }
            $hojas++;
        }
        $p += 30 + $h['nl'] + $h['el'] + $h['cs'];
    }
    return $hojas;
}
function limpiar(): void
{
    global $db;
    foreach ([
        "DELETE p FROM pagos p JOIN creditos c ON c.id = p.credito_id JOIN clientes cl ON cl.id = c.cliente_id WHERE cl.cedula LIKE '999000%'",
        "DELETE g FROM gestiones g JOIN creditos c ON c.id = g.credito_id JOIN clientes cl ON cl.id = c.cliente_id WHERE cl.cedula LIKE '999000%'",
        "DELETE c FROM creditos c JOIN clientes cl ON cl.id = c.cliente_id WHERE cl.cedula LIKE '999000%'",
        "DELETE FROM clientes WHERE cedula LIKE '999000%'",
        "DELETE FROM gastos WHERE cobrador_id IN (SELECT id FROM usuarios WHERE usuario LIKE 'sectest_func%')",
        "DELETE FROM cierres WHERE cobrador_id IN (SELECT id FROM usuarios WHERE usuario LIKE 'sectest_func%')",
        "DELETE FROM auditoria WHERE usuario_id IN (SELECT id FROM usuarios WHERE usuario LIKE 'sectest_func%') OR detalle LIKE '%sectest_func%' OR detalle LIKE '%999000%'",
        "UPDATE pagos SET cobrador_id = NULL WHERE cobrador_id IN (SELECT id FROM usuarios WHERE usuario LIKE 'sectest_func%')",
        "DELETE FROM usuarios WHERE usuario LIKE 'sectest_func%'",
        "DELETE FROM intentos_login WHERE usuario LIKE 'sectest_func%'",
    ] as $sql) {
        try { $db->exec($sql); } catch (Throwable) {}
    }
}

echo "APEX · Prueba funcional de punta a punta contra {$BASE}\n\n";
limpiar();
$db->prepare("INSERT INTO usuarios (nombre, usuario, password, rol, activo, debe_cambiar_clave) VALUES ('Admin Funcional', 'sectest_func_admin', ?, 'admin', 1, 0)")
    ->execute([Password::hash('Funcional#Admin2026')]);
$hoy = date('Y-m-d');

try {
    // 1. Usuarios
    echo "1. Usuarios y acceso\n";
    $a = new Cliente($BASE);
    paso('Administrador inicia sesión', str_contains($a->login('sectest_func_admin', 'Funcional#Admin2026')['location'], 'dashboard'));
    $a->post('usuarios/guardar', ['rol' => 'cobrador', 'nombre' => 'Cobrador Funcional', 'cedula' => '99900501', 'telefono' => '3001112233',
        'usuario' => 'sectest_func_cob', 'password' => 'Ruta.Prueba#2026', 'zona' => 'Norte', 'meta_diaria' => '150.000', 'activo' => '1']);
    $cobId = (int) v("SELECT id FROM usuarios WHERE usuario = 'sectest_func_cob'");
    paso('Crea un cobrador con contraseña segura', $cobId > 0);
    $c = new Cliente($BASE);
    $r = $c->login('sectest_func_cob', 'Ruta.Prueba#2026');
    paso('El cobrador nuevo es obligado a cambiar su contraseña', str_contains($r['location'], 'perfil'));
    $c->post('perfil/clave', ['actual' => 'Ruta.Prueba#2026', 'nueva' => 'Mi.Clave-Propia#77', 'confirmar' => 'Mi.Clave-Propia#77']);
    paso('Cambia la contraseña y entra a su ruta', $c->get('ruta')['code'] === 200);

    // 2. Clientes y créditos
    echo "2. Clientes y créditos\n";
    $a->post('clientes/guardar', ['cedula' => '999000501', 'nombres' => 'carmen', 'apellidos' => 'prueba funcional', 'email' => 'carmen@correo.com',
        'telefono1' => '3101234567', 'telefono2' => '3157654321', 'direccion' => 'Calle 1 # 2-3', 'barrio' => 'Centro', 'ciudad' => 'Bucaramanga',
        'referencia_nombre' => 'Luis', 'referencia_telefono' => '3000000001', 'cobrador_id' => (string) $cobId, 'estado' => 'activo']);
    $cliId = (int) v("SELECT id FROM clientes WHERE cedula = '999000501'");
    paso('Registra un cliente con todos sus datos y lo asigna al cobrador', $cliId > 0 && (int) v('SELECT cobrador_id FROM clientes WHERE id = ?', [$cliId]) === $cobId);
    paso('Nombres guardados con mayúscula inicial', v('SELECT nombres FROM clientes WHERE id = ?', [$cliId]) === 'Carmen');
    $sim = json_decode($a->get('creditos/simular?monto=300.000&interes=20&num_cuotas=10&frecuencia=diaria&excluir_domingos=1&fecha_inicio=' . $hoy . '&fecha_primera_cuota=' . $hoy)['body'], true);
    paso('Simulador: 300.000 al 20 % = 360.000 en 10 cuotas de 36.000', ($sim['plan']['total'] ?? 0) == 360000 && ($sim['plan']['valor_cuota'] ?? 0) == 36000);
    $ini = date('Y-m-d', strtotime('-6 days'));
    $a->post('creditos/guardar', ['cliente_id' => (string) $cliId, 'monto' => '300.000', 'interes' => '20', 'num_cuotas' => '10', 'frecuencia' => 'diaria',
        'excluir_domingos' => '1', 'fecha_inicio' => $ini, 'fecha_primera_cuota' => date('Y-m-d', strtotime('-5 days'))]);
    $crId = (int) v('SELECT id FROM creditos WHERE cliente_id = ? ORDER BY id LIMIT 1', [$cliId]);
    paso('Crea el crédito con su plan de 10 cuotas', $crId > 0 && (int) v('SELECT COUNT(*) FROM cuotas WHERE credito_id = ?', [$crId]) === 10);
    paso('El crédito aparece en la ruta del cobrador con cuotas atrasadas', str_contains($c->get('ruta')['body'], 'Carmen Prueba Funcional'));

    // 3. Cobro
    echo "3. Cobro en la ruta\n";
    $c->post('pagos/guardar', ['credito_id' => (string) $crId, 'monto' => '72.000', 'metodo' => 'efectivo', 'volver' => 'ruta']);
    paso('El cobrador registra un pago de 72.000', abs((float) v('SELECT saldo FROM creditos WHERE id = ?', [$crId]) - 288000) < 1);
    paso('El pago cubre exactamente 2 cuotas', (int) v("SELECT COUNT(*) FROM cuotas WHERE credito_id = ? AND estado = 'pagada'", [$crId]) === 2);
    $pagoId = (int) v('SELECT MAX(id) FROM pagos WHERE credito_id = ?', [$crId]);
    $r = $c->get('pagos/recibo/' . $pagoId);
    paso('Recibo imprimible disponible', $r['code'] === 200 && str_contains($r['body'], 'RECIBO DE PAGO'));
    $c->post('gestiones/guardar', ['credito_id' => (string) $crId, 'tipo' => 'visita', 'resultado' => 'promesa',
        'fecha_promesa' => date('Y-m-d', strtotime('+1 day')), 'monto_promesa' => '36.000', 'nota' => 'Paga mañana', 'volver' => 'ruta']);
    paso('Registra una promesa de pago', (int) v("SELECT COUNT(*) FROM gestiones WHERE credito_id = ? AND resultado = 'promesa'", [$crId]) === 1);
    $c->post('caja/gasto', ['concepto' => 'transporte', 'valor' => '10.000', 'nota' => 'Gasolina']);
    paso('Registra un gasto de ruta de 10.000', (float) v('SELECT SUM(valor) FROM gastos WHERE cobrador_id = ?', [$cobId]) === 10000.0);
    $cajaHtml = $c->get('caja')['body'];
    paso('Su caja muestra 62.000 por entregar', str_contains($cajaHtml, '62.000'));

    // 4. Cierre de caja
    echo "4. Cierre de caja\n";
    $a->post('caja/cerrar', ['fecha' => $hoy, 'cobrador_id' => (string) $cobId, 'entregado' => '60.000', 'observaciones' => 'Faltaron 2.000']);
    $dif = (float) v('SELECT diferencia FROM cierres WHERE cobrador_id = ? AND fecha = ?', [$cobId, $hoy]);
    paso('El administrador cierra la caja y se registra un faltante de 2.000', abs($dif + 2000) < 1, (string) $dif);
    $mano = (new App\Models\Caja())->enMano($cobId)[$cobId]['en_mano'] ?? null;
    paso('El faltante queda como efectivo en mano del cobrador', abs((float) $mano - 2000) < 1, (string) $mano);

    // 5. Renovación
    echo "5. Renovación\n";
    $saldo = (float) v('SELECT saldo FROM creditos WHERE id = ?', [$crId]);
    paso('Formulario de renovación disponible', $a->get('creditos/renovar/' . $crId)['code'] === 200);
    $a->post('creditos/guardar', ['renovar_id' => (string) $crId, 'monto' => '500.000', 'interes' => '20', 'num_cuotas' => '20', 'frecuencia' => 'diaria',
        'excluir_domingos' => '1', 'fecha_inicio' => $hoy, 'fecha_primera_cuota' => date('Y-m-d', strtotime('+1 day'))]);
    $nuevo = (int) v('SELECT id FROM creditos WHERE renovado_de = ?', [$crId]);
    paso('Renueva: el crédito anterior queda pagado', $nuevo > 0 && v('SELECT estado FROM creditos WHERE id = ?', [$crId]) === 'pagado');
    paso('Efectivo entregado = 500.000 − saldo anterior', abs((float) v('SELECT neto_entregado FROM creditos WHERE id = ?', [$nuevo]) - (500000 - $saldo)) < 1);
    $a->post('creditos/anular/' . $nuevo, ['motivo' => 'Prueba funcional']);
    paso('Anular la renovación revive el crédito anterior con su saldo', v('SELECT estado FROM creditos WHERE id = ?', [$crId]) === 'activo'
        && abs((float) v('SELECT saldo FROM creditos WHERE id = ?', [$crId]) - $saldo) < 1);

    // 6. Pago total y documentos
    echo "6. Pago total y documentos\n";
    $a->post('pagos/guardar', ['credito_id' => (string) $crId, 'monto' => number_format($saldo, 0, ',', '.'), 'metodo' => 'transferencia']);
    paso('Al pagar el saldo total el crédito queda pagado', v('SELECT estado FROM creditos WHERE id = ?', [$crId]) === 'pagado');
    paso('Promesa marcada como cumplida automáticamente', str_contains($a->get('creditos/ver/' . $crId)['body'], 'Cumplida'));
    $r = $a->get('creditos/estado/' . $crId);
    paso('Estado de cuenta imprimible', $r['code'] === 200 && str_contains($r['body'], 'ESTADO DE CUENTA'));
    $r = $a->get('creditos/pazysalvo/' . $crId);
    paso('Paz y salvo expedido', $r['code'] === 200 && str_contains($r['body'], 'PAZ Y SALVO'));
    $r = $a->get('creditos/excel/' . $crId);
    paso('Estado de cuenta en Excel (2 hojas, XML válido)', str_contains($r['type'], 'spreadsheetml') && zipOk($r['body']) === 2);
    paso('Calificación del cliente calculada', preg_match('/badge-soft-\w+ calif[^>]*>[ABCDN]/', $a->get('clientes/ver/' . $cliId)['body']) === 1);

    // 7. Reportes
    echo "7. Reportes y respaldo\n";
    $esperadas = ['pagos' => 1, 'cartera' => 1, 'mora' => 1, 'clientes' => 1, 'caja' => 2, 'gestiones' => 2, 'rentabilidad' => 1, 'completo' => 9];
    $malos = [];
    foreach ($esperadas as $t => $h) {
        $r = $a->get("reportes/exportar/{$t}?desde=" . date('Y-m-01') . "&hasta={$hoy}");
        if (!str_contains($r['type'], 'spreadsheetml') || zipOk($r['body']) !== $h) {
            $malos[] = $t;
        }
    }
    paso('Los 8 reportes de Excel se generan con todas sus hojas y XML válido', !$malos, implode(', ', $malos));
    $r = $a->post('respaldo/descargar', []);
    paso('Respaldo de la base de datos descargable', str_contains($r['body'], 'CREATE TABLE `pagos`') && str_contains($r['body'], 'Fin del respaldo'));
    paso('Auditoría registra las operaciones', (int) v("SELECT COUNT(DISTINCT accion) FROM auditoria WHERE usuario_id IN (SELECT id FROM usuarios WHERE usuario LIKE 'sectest_func%')") >= 10);

    // 8. Todas las pantallas
    echo "8. Recorrido por todas las pantallas\n";
    $fallas = [];
    foreach (['dashboard', 'ruta', 'clientes', 'clientes/ver/' . $cliId, 'clientes/editar/' . $cliId, 'clientes/crear', 'creditos', 'creditos/ver/' . $crId,
                 'creditos/crear', 'pagos', 'caja', 'cobradores', 'cobradores/ver/' . $cobId, 'reportes', 'auditoria', 'respaldo', 'usuarios',
                 'usuarios/crear', 'usuarios/editar/' . $cobId, 'configuracion', 'perfil'] as $p) {
        $r = $a->get($p);
        if ($r['code'] !== 200 || preg_match('/(Fatal error|Warning:|Notice:|Deprecated:|Error inesperado|Error de base de datos)/', $r['body'])) {
            $fallas[] = "{$p} ({$r['code']})";
        }
    }
    paso('Administrador: 21 pantallas cargan sin errores', !$fallas, implode(', ', $fallas));
    $fallas = [];
    foreach (['dashboard', 'ruta', 'clientes', 'clientes/ver/' . $cliId, 'creditos', 'creditos/ver/' . $crId, 'pagos', 'caja', 'cobradores/ver/' . $cobId, 'perfil'] as $p) {
        $r = $c->get($p);
        if ($r['code'] !== 200 || preg_match('/(Fatal error|Warning:|Notice:|Error inesperado)/', $r['body'])) {
            $fallas[] = "{$p} ({$r['code']})";
        }
    }
    paso('Cobrador: 10 pantallas cargan sin errores', !$fallas, implode(', ', $fallas));
} finally {
    limpiar();
}

printf("\nResultado: %d de %d pasos correctos%s\n", $ok, $ok + $fail, $fail ? " ({$fail} fallas)" : '');
exit($fail ? 1 : 0);
