<?php
/**
 * APEX · Datos de demostración.
 *
 *   C:\xampp\php\php.exe database\seed_demo.php           Carga los datos de prueba
 *   C:\xampp\php\php.exe database\seed_demo.php --borrar  Elimina SOLO los datos de prueba
 *
 * Los datos de prueba se identifican así:
 *   - Cobradores con usuario "demo.*"
 *   - Clientes con cédula que empieza por 91000
 * Tus datos reales no se modifican.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Solo por línea de comandos.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Password;
use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Pago;
use App\Models\Usuario;

const CLAVE_DEMO = 'Ruta#Segura2026';
$db = Database::connection();

function borrarDemo(PDO $db): void
{
    $db->exec("DELETE p FROM pagos p JOIN creditos c ON c.id = p.credito_id JOIN clientes cl ON cl.id = c.cliente_id WHERE cl.cedula LIKE '91000%'");
    $db->exec("DELETE c FROM creditos c JOIN clientes cl ON cl.id = c.cliente_id WHERE cl.cedula LIKE '91000%'");
    $n = $db->exec("DELETE FROM clientes WHERE cedula LIKE '91000%'");
    // Pagos de clientes reales que hayan quedado a nombre de un cobrador demo: se conservan (cobrador_id pasa a NULL)
    $u = $db->exec("DELETE FROM usuarios WHERE usuario LIKE 'demo.%'");
    echo "Datos de prueba eliminados: {$n} clientes y {$u} cobradores (con sus créditos y pagos).\n";
}

if (in_array('--borrar', $argv, true)) {
    borrarDemo($db);
    Auditoria::log('config_editada', 'Datos de demostración eliminados', null, null, null);
    exit(0);
}

if ((int) $db->query("SELECT COUNT(*) FROM clientes WHERE cedula LIKE '91000%'")->fetchColumn() > 0) {
    echo "Ya hay datos de prueba cargados. Para recargarlos ejecuta primero: php database/seed_demo.php --borrar\n";
    exit(1);
}

if ($e = Password::validar(CLAVE_DEMO)) {
    exit('La clave de demostración no cumple la política: ' . Password::mensaje($e) . "\n");
}

mt_srand(20261012); // resultados reproducibles
$pick = fn (array $a) => $a[mt_rand(0, count($a) - 1)];
$chance = fn (float $p) => mt_rand() / mt_getrandmax() < $p;
$hoy = new DateTimeImmutable('today');
$adminId = (int) $db->query("SELECT id FROM usuarios WHERE rol = 'admin' ORDER BY id LIMIT 1")->fetchColumn();

// ------------------------------------------------------------ cobradores
$cobradores = [
    ['Carlos Andrés Ramírez', '1098456123', '3104567890', 'demo.carlos', 'Norte', 350000],
    ['Laura Marcela Gómez', '1095789456', '3157894561', 'demo.laura', 'Sur', 300000],
    ['Andrés Felipe Torres', '1102345678', '3201234567', 'demo.andres', 'Centro', 280000],
    ['Diana Patricia Vargas', '1098987654', '3009876543', 'demo.diana', 'Oriente', 0],
    ['Jhon Fredy Castillo', '1096321987', '3176543210', 'demo.jhon', 'Occidente', 320000],
];
$usuarioModel = new Usuario();
$cobIds = [];
foreach ($cobradores as [$nombre, $ced, $tel, $user, $zona, $meta]) {
    if ($usuarioModel->existe('usuario', $user) || $usuarioModel->existe('cedula', $ced)) {
        exit("El usuario {$user} o la cédula {$ced} ya existen. Ejecuta --borrar primero.\n");
    }
    $cobIds[$zona] = $usuarioModel->create([
        'nombre' => $nombre, 'cedula' => $ced, 'telefono' => $tel, 'email' => $user . '@apex.demo',
        'usuario' => $user, 'password' => Password::hash(CLAVE_DEMO), 'rol' => 'cobrador', 'zona' => $zona,
        'meta_diaria' => $meta, 'activo' => 1, 'debe_cambiar_clave' => 0,
    ]);
}
echo 'Cobradores creados: ' . count($cobIds) . "\n";

// ------------------------------------------------------------ clientes
$nombres = ['José Luis', 'María Fernanda', 'Juan Carlos', 'Luz Marina', 'Pedro Antonio', 'Ana Milena', 'Jorge Enrique',
    'Claudia Patricia', 'Luis Alberto', 'Sandra Milena', 'Óscar Iván', 'Gloria Inés', 'Edwin Fabián', 'Martha Lucía',
    'Wilson Javier', 'Yolanda', 'Fredy Alexander', 'Nubia Esperanza', 'Hernán Darío', 'Rocío del Pilar', 'Jhon Jairo',
    'Diana Carolina', 'Rafael Antonio', 'Leidy Johana', 'Camilo Andrés', 'Blanca Cecilia', 'Germán Augusto', 'Paola Andrea',
    'Mauricio', 'Adriana Lucía', 'Álvaro José', 'Erika Tatiana', 'Ricardo', 'Stella', 'Nelson Enrique', 'Carmen Rosa',
    'Fabio Hernando', 'Yaneth', 'Rubén Darío', 'Olga Lucía', 'Iván Mauricio', 'Liliana', 'Sergio Andrés', 'Marleny',
    'Héctor Fabio', 'Luz Dary', 'William', 'Esperanza', 'Gustavo Adolfo', 'Yurany', 'Orlando', 'Ludy Marcela', 'Edgar',
    'Natalia', 'Fernando', 'Consuelo', 'Daniel Felipe', 'Margarita', 'Julián David', 'Astrid Carolina'];
$apellidos = ['Rodríguez Pérez', 'Gómez Díaz', 'Martínez Suárez', 'López Rueda', 'González Ardila', 'Hernández Mantilla',
    'Sánchez Cárdenas', 'Ramírez Ortiz', 'Torres Jaimes', 'Flórez Gamboa', 'Rojas Villamizar', 'Díaz Serrano',
    'Moreno Pinzón', 'Castro Duarte', 'Vargas Quintero', 'Jiménez Bautista', 'Rincón Plata', 'Ortiz Delgado',
    'Becerra Acevedo', 'Peña Sierra', 'Carvajal León', 'Mejía Prada', 'Calderón Uribe', 'Niño Barajas', 'Galvis Rangel',
    'Arenas Chacón', 'Pabón Estévez', 'Sepúlveda Navas'];
$zonas = [
    'Norte'   => [['Kennedy', 'Bucaramanga'], ['Villa Rosa', 'Bucaramanga'], ['Colorados', 'Bucaramanga'], ['Café Madrid', 'Bucaramanga'], ['Regadero Norte', 'Bucaramanga']],
    'Sur'     => [['Provenza', 'Bucaramanga'], ['Diamante II', 'Bucaramanga'], ['Lagos del Cacique', 'Bucaramanga'], ['Cañaveral', 'Floridablanca'], ['Bucarica', 'Floridablanca']],
    'Centro'  => [['Centro', 'Bucaramanga'], ['García Rovira', 'Bucaramanga'], ['San Alonso', 'Bucaramanga'], ['Antonia Santos', 'Bucaramanga'], ['Girardot', 'Bucaramanga']],
    'Oriente' => [['Cabecera', 'Bucaramanga'], ['La Concordia', 'Bucaramanga'], ['San Francisco', 'Bucaramanga'], ['Álvarez', 'Bucaramanga'], ['Mejoras Públicas', 'Bucaramanga']],
    'Occidente' => [['El Poblado', 'Girón'], ['Rincón de Girón', 'Girón'], ['Arenales', 'Girón'], ['Santa Cruz', 'Girón'], ['Portal Campestre', 'Girón']],
];
$ocupaciones = [['Tendero', 'Tienda'], ['Vendedora de minutos', null], ['Panadero', 'Panadería'], ['Modista', 'Taller de costura'],
    ['Comerciante', 'Miscelánea'], ['Peluquera', 'Peluquería'], ['Mecánico', 'Taller'], ['Vendedor ambulante', null],
    ['Restaurante', 'Comidas rápidas'], ['Ferretero', 'Ferretería'], ['Empleada', null], ['Droguería', 'Droguería'],
    ['Frutera', 'Puesto de frutas'], ['Zapatero', 'Remontadora'], ['Conductor', null]];
$parentescos = ['Hermano(a)', 'Esposo(a)', 'Vecino(a)', 'Amigo(a)', 'Familiar', 'Compañero de trabajo'];
$observ = ['Cobrar en la mañana, abre el negocio a las 8.', 'Prefiere que lo llamen antes de ir.', 'Paga por Nequi casi siempre.',
    'Casa esquinera de rejas verdes.', 'Al lado de la iglesia.', null, null, null, 'Cliente referido por otro cliente.', 'Trabaja en la plaza de mercado.'];

$clienteModel = new Cliente();
$clientes = [];
$total = 100;
$bloqueados = [37, 81];
$inactivos = [42, 88];
$sinAsignar = [41, 43, 97]; // en espera de asignar cobrador
$sinCredito = [8, 21, 42, 43, 55, 73, 88]; // recién registrados o inactivos
$zonaKeys = array_keys($zonas);
for ($i = 0; $i < $total; $i++) {
    $zona = $zonaKeys[$i % count($zonaKeys)];
    [$barrio, $ciudad] = $pick($zonas[$zona]);
    [$ocup, $negocio] = $pick($ocupaciones);
    $nom = $nombres[$i % count($nombres)];
    $ape = $pick($apellidos);
    $sinCobrador = in_array($i, $sinAsignar, true);
    $estado = in_array($i, $bloqueados, true) ? 'bloqueado' : (in_array($i, $inactivos, true) ? 'inactivo' : 'activo');
    $data = [
        'cedula'                => '91000' . str_pad((string) (10000 + $i * 137), 5, '0', STR_PAD_LEFT),
        'nombres'               => $nom,
        'apellidos'             => $ape,
        'email'                 => $chance(0.35) ? strtolower(strtr(explode(' ', $nom)[0] . '.' . explode(' ', $ape)[0], ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'a', 'Ó' => 'o', 'ñ' => 'n'])) . mt_rand(10, 99) . '@correo.com' : null,
        'telefono1'             => $pick(['300', '301', '310', '311', '312', '313', '315', '316', '317', '318', '320', '321', '350']) . mt_rand(1000000, 9999999),
        'telefono2'             => $chance(0.5) ? '3' . mt_rand(100000000, 209999999) : null,
        'direccion'             => $pick(['Calle', 'Carrera', 'Diagonal', 'Transversal']) . ' ' . mt_rand(1, 110) . ' # ' . mt_rand(1, 60) . '-' . mt_rand(1, 99),
        'barrio'                => $barrio,
        'ciudad'                => $ciudad,
        'fecha_nacimiento'      => $chance(0.7) ? $hoy->modify('-' . mt_rand(22, 65) . ' years -' . mt_rand(0, 360) . ' days')->format('Y-m-d') : null,
        'ocupacion'             => $ocup,
        'negocio_nombre'        => $negocio ? $negocio . ' ' . explode(' ', $nom)[0] : null,
        'negocio_direccion'     => $negocio && $chance(0.6) ? 'Carrera ' . mt_rand(1, 40) . ' # ' . mt_rand(1, 90) . '-' . mt_rand(1, 50) . ', ' . $barrio : null,
        'referencia_nombre'     => $chance(0.8) ? $pick($nombres) . ' ' . explode(' ', $pick($apellidos))[0] : null,
        'referencia_telefono'   => '3' . mt_rand(100000000, 209999999),
        'referencia_parentesco' => $pick($parentescos),
        'observaciones'         => $estado === 'bloqueado' ? 'Bloqueado: no volver a prestar, se perdió contacto varias semanas.' : $pick($observ),
        'cobrador_id'           => $sinCobrador ? null : $cobIds[$zona],
        'estado'                => $estado,
        'created_by'            => $adminId,
    ];
    $id = $clienteModel->create($data);
    // Fecha de registro creíble (entre 4 y 1 mes atrás)
    $db->prepare('UPDATE clientes SET created_at = ? WHERE id = ?')
        ->execute([$hoy->modify('-' . mt_rand(30, 130) . ' days')->format('Y-m-d') . ' ' . sprintf('%02d:%02d:00', mt_rand(8, 17), mt_rand(0, 59)), $id]);
    $clienteModel->registrarAsignacionInicial($id, $data['cobrador_id'], $adminId);
    $clientes[] = ['id' => $id, 'i' => $i, 'cobrador' => $data['cobrador_id'], 'estado' => $estado];
}
echo 'Clientes creados: ' . count($clientes) . "\n";

// ------------------------------------------------------------ créditos y pagos
$creditoModel = new Credito();
$pagoModel = new Pago();
$metodos = fn () => $chance(0.72) ? 'efectivo' : $pick(['nequi', 'nequi', 'daviplata', 'transferencia']);

/**
 * Simula el comportamiento de pago de un cliente sobre su plan de cuotas.
 * Perfiles: puntual, atrasado (salta cuotas y se pone al día a ratos), moroso (dejó de pagar), adelantado.
 */
$simular = function (int $creditoId, ?int $cobrador, string $perfil, bool $liquidar = false) use ($db, $creditoModel, $pagoModel, $chance, $metodos, $hoy) {
    $cuotas = $creditoModel->cuotas($creditoId);
    $vencidas = array_values(array_filter($cuotas, fn ($c) => $c['fecha_vencimiento'] <= $hoy->format('Y-m-d')));
    $corte = $perfil === 'moroso' ? (int) floor(count($vencidas) * (mt_rand(35, 70) / 100)) : PHP_INT_MAX;
    $deuda = 0.0;
    $pagos = 0;
    foreach ($vencidas as $k => $c) {
        $deuda += (float) $c['valor'];
        $esHoy = $c['fecha_vencimiento'] === $hoy->format('Y-m-d');
        if ($k >= $corte || ($esHoy && !$liquidar && $chance(0.45))) {
            continue; // moroso que ya no paga, o cuota de hoy aún sin cobrar
        }
        $monto = 0.0;
        if ($liquidar || $perfil === 'puntual') {
            $monto = $deuda;
        } elseif ($perfil === 'adelantado') {
            $monto = $chance(0.3) ? $deuda + (float) $c['valor'] : $deuda;
        } elseif ($perfil === 'atrasado') {
            if ($chance(0.35)) {
                continue;
            }
            $monto = $chance(0.6) ? $deuda : round($deuda * 0.6 / 1000) * 1000;
        } else { // moroso antes del corte
            $monto = $chance(0.8) ? $deuda : (float) $c['valor'];
        }
        $saldo = (float) $db->query('SELECT saldo FROM creditos WHERE id = ' . $creditoId)->fetchColumn();
        $monto = min($monto, $saldo);
        if ($monto <= 0) {
            break;
        }
        $hora = sprintf('%02d:%02d:%02d', mt_rand(7, 18), mt_rand(0, 59), mt_rand(0, 59));
        $fecha = $esHoy && $hora > date('H:i:s') ? date('Y-m-d H:i:s') : $c['fecha_vencimiento'] . ' ' . $hora;
        $pagoModel->registrar($creditoId, $monto, $metodos(), $cobrador, $fecha, $chance(0.08) ? 'Abono recibido en el negocio' : null);
        $deuda = max(0, $deuda - $monto);
        $pagos++;
    }
    return $pagos;
};

$planes = [
    'diaria'    => [[20, 20], [24, 20], [30, 20], [40, 25], [60, 30]],
    'semanal'   => [[4, 15], [8, 20], [10, 20], [12, 25]],
    'quincenal' => [[4, 15], [6, 20], [8, 25]],
    'mensual'   => [[3, 15], [4, 18], [6, 25]],
];
$nCred = $nPagos = 0;
foreach ($clientes as $c) {
    if (in_array($c['i'], $sinCredito, true)) {
        continue;
    }
    $r = mt_rand(1, 100);
    $frec = $r <= 62 ? 'diaria' : ($r <= 85 ? 'semanal' : ($r <= 95 ? 'quincenal' : 'mensual'));
    [$num, $interes] = $pick($planes[$frec]);
    $dias = ['diaria' => 1, 'semanal' => 7, 'quincenal' => 15, 'mensual' => 30][$frec];
    $monto = $frec === 'diaria' ? mt_rand(4, 30) * 50000 : mt_rand(6, 60) * 50000;

    $r = mt_rand(1, 100);
    $perfil = $c['estado'] === 'bloqueado' ? 'moroso' : ($r <= 55 ? 'puntual' : ($r <= 80 ? 'atrasado' : ($r <= 92 ? 'moroso' : 'adelantado')));

    // Algunos clientes buenos ya tuvieron un crédito anterior pagado (renovación)
    if ($perfil === 'puntual' && $chance(0.3) && $c['cobrador']) {
        $iniViejo = $hoy->modify('-' . mt_rand(95, 130) . ' days');
        $idViejo = $creditoModel->crear([
            'cliente_id' => $c['id'], 'monto' => (float) (mt_rand(4, 12) * 50000), 'interes' => 20.0, 'num_cuotas' => 30,
            'frecuencia' => 'diaria', 'excluir_domingos' => true, 'fecha_inicio' => $iniViejo->format('Y-m-d'),
            'fecha_primera_cuota' => $iniViejo->modify('+1 day')->format('Y-m-d'), 'observaciones' => '',
        ], $adminId);
        $db->prepare('UPDATE creditos SET created_at = ? WHERE id = ?')->execute([$iniViejo->format('Y-m-d 09:00:00'), $idViejo]);
        $nPagos += $simular($idViejo, $c['cobrador'], 'puntual', true);
        $nCred++;
    }

    // Duración del plan en días para decidir cuándo empezó (la mayoría sigue vigente)
    $duracion = (int) ($num * $dias * ($frec === 'diaria' ? 7 / 6 : 1));
    $haceDias = mt_rand(1, max(2, min(80, (int) ($duracion * 0.85))));
    if ($chance(0.08)) {
        $haceDias = $duracion + mt_rand(3, 15); // ya debería haber terminado
    }
    $inicio = $hoy->modify("-{$haceDias} days");
    $id = $creditoModel->crear([
        'cliente_id' => $c['id'], 'monto' => (float) $monto, 'interes' => (float) $interes, 'num_cuotas' => $num,
        'frecuencia' => $frec, 'excluir_domingos' => true, 'fecha_inicio' => $inicio->format('Y-m-d'),
        'fecha_primera_cuota' => $inicio->modify("+{$dias} days")->format('Y-m-d'),
        'observaciones' => $chance(0.15) ? 'Préstamo para surtir el negocio.' : '',
    ], $adminId);
    $db->prepare('UPDATE creditos SET created_at = ? WHERE id = ?')->execute([$inicio->format('Y-m-d 10:00:00'), $id]);
    $nPagos += $simular($id, $c['cobrador'] ?: $adminId, $perfil);
    $nCred++;
}

// ------------------------------------------------------------ renovaciones (clientes puntuales con más de la mitad pagada)
$renovables = $db->query("SELECT cr.id, cr.saldo, cr.monto FROM creditos cr JOIN clientes cl ON cl.id = cr.cliente_id
    WHERE cl.cedula LIKE '91000%' AND cr.estado = 'activo' AND cr.frecuencia = 'diaria' AND cr.total_pagado >= cr.total * 0.6
    AND NOT EXISTS (SELECT 1 FROM cuotas cu WHERE cu.credito_id = cr.id AND cu.estado <> 'pagada' AND cu.fecha_vencimiento < CURDATE())
    ORDER BY cr.id LIMIT 5")->fetchAll();
$nRenov = 0;
foreach ($renovables as $rv) {
    $nuevo = (float) (ceil(((float) $rv['monto'] * 1.5) / 50000) * 50000);
    $creditoModel->renovar((int) $rv['id'], [
        'monto' => $nuevo, 'interes' => 20.0, 'num_cuotas' => 30, 'frecuencia' => 'diaria', 'excluir_domingos' => true,
        'fecha_inicio' => $hoy->format('Y-m-d'), 'fecha_primera_cuota' => $hoy->modify('+1 day')->format('Y-m-d'),
        'observaciones' => 'Renovación: cliente puntual, se aumenta el monto.',
    ], $adminId);
    $nRenov++;
}

// ------------------------------------------------------------ gestiones de cobro y promesas
$resultadosSinPago = ['no_estaba', 'no_estaba', 'sin_dinero', 'sin_dinero', 'se_niega', 'otro'];
$notas = ['no_estaba' => ['Local cerrado', 'No abrió, la vecina dice que salió', 'No contesta en la casa'],
    'sin_dinero' => ['Dice que las ventas están flojas', 'Le pagan el viernes', 'Pide que se le espere'],
    'se_niega' => ['Dice que ya no va a pagar', 'Discute el valor de la cuota'], 'otro' => ['Está enfermo', 'Viajó al pueblo'],
    'promesa' => ['Promete ponerse al día', 'Paga cuando le llegue el giro', 'Se compromete a abonar']];
$insG = $db->prepare('INSERT INTO gestiones (credito_id, usuario_id, tipo, resultado, fecha_promesa, monto_promesa, nota, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$nGest = 0;
$enMora = $db->query("SELECT cr.id, cr.valor_cuota, cl.cobrador_id, MIN(cu.fecha_vencimiento) AS desde, SUM(cu.valor - cu.abonado) AS vencido
    FROM creditos cr JOIN clientes cl ON cl.id = cr.cliente_id JOIN cuotas cu ON cu.credito_id = cr.id
    WHERE cl.cedula LIKE '91000%' AND cr.estado = 'activo' AND cu.estado <> 'pagada' AND cu.fecha_vencimiento < CURDATE()
    GROUP BY cr.id")->fetchAll();
foreach ($enMora as $m) {
    $dias = max(1, (int) (new DateTime($m['desde']))->diff(new DateTime('today'))->days);
    $n = min(4, max(1, intdiv($dias, 4)));
    for ($k = 0; $k < $n; $k++) {
        $dia = $hoy->modify('-' . mt_rand(1, $dias) . ' days');
        $res = $pick($resultadosSinPago);
        $insG->execute([$m['id'], $m['cobrador_id'], $chance(0.75) ? 'visita' : $pick(['llamada', 'whatsapp']), $res, null, null,
            $pick($notas[$res]), $dia->format('Y-m-d') . sprintf(' %02d:%02d:00', mt_rand(8, 17), mt_rand(0, 59))]);
        $nGest++;
    }
    // Promesa: unas para hoy/mañana (pendientes) y otras ya vencidas (incumplidas)
    if ($chance(0.7)) {
        $vence = $chance(0.6) ? $hoy->modify('+' . mt_rand(0, 2) . ' days') : $hoy->modify('-' . mt_rand(1, 3) . ' days');
        $creada = min($vence->modify('-1 day'), $hoy->modify('-1 day'));
        $insG->execute([$m['id'], $m['cobrador_id'], 'visita', 'promesa', $vence->format('Y-m-d'),
            min((float) $m['vencido'], (float) $m['valor_cuota'] * mt_rand(1, 3)), $pick($notas['promesa']),
            $creada->format('Y-m-d') . sprintf(' %02d:%02d:00', mt_rand(8, 17), mt_rand(0, 59))]);
        $nGest++;
    }
}
// Promesas cumplidas: un día antes de algunos pagos de clientes atrasados, el cliente prometió ese pago
$pagosAtrasados = $db->query("SELECT p.credito_id, p.monto, p.fecha, p.cobrador_id FROM pagos p JOIN creditos cr ON cr.id = p.credito_id
    JOIN clientes cl ON cl.id = cr.cliente_id JOIN cuotas cu ON cu.credito_id = cr.id AND cu.fecha_pago = DATE(p.fecha) AND cu.fecha_pago > cu.fecha_vencimiento
    WHERE cl.cedula LIKE '91000%' AND p.anulado = 0 AND p.metodo <> 'renovacion' AND p.fecha >= CURDATE() - INTERVAL 25 DAY
    GROUP BY p.id ORDER BY p.id LIMIT 40")->fetchAll();
foreach ($pagosAtrasados as $pa) {
    $f = new DateTimeImmutable(substr($pa['fecha'], 0, 10));
    $insG->execute([$pa['credito_id'], $pa['cobrador_id'], 'visita', 'promesa', $f->format('Y-m-d'), (float) $pa['monto'],
        $pick($notas['promesa']), $f->modify('-1 day')->format('Y-m-d') . ' 16:30:00']);
    $nGest++;
}

// ------------------------------------------------------------ gastos de ruta (últimos 21 días, sin domingos)
$caja = new App\Models\Caja();
$nGastos = 0;
foreach ($cobIds as $cid) {
    for ($k = 0; $k <= 21; $k++) {
        $dia = $hoy->modify("-{$k} days");
        if ($dia->format('w') === '0') {
            continue;
        }
        $caja->registrarGasto($cid, $dia->format('Y-m-d'), 'transporte', (float) (mt_rand(8, 15) * 1000), 'Gasolina moto', $cid);
        $nGastos++;
        if ($chance(0.35)) {
            $caja->registrarGasto($cid, $dia->format('Y-m-d'), 'alimentacion', (float) (mt_rand(9, 14) * 1000), 'Almuerzo', $cid);
            $nGastos++;
        }
        if ($chance(0.08)) {
            $caja->registrarGasto($cid, $dia->format('Y-m-d'), 'recarga', 10000.0, 'Recarga para llamadas', $cid);
            $nGastos++;
        }
    }
}

// ------------------------------------------------------------ cierres de caja de los días anteriores
$nCierres = 0;
$diasCaja = $db->query("SELECT DISTINCT DATE(p.fecha) AS d FROM pagos p JOIN creditos cr ON cr.id = p.credito_id
    JOIN clientes cl ON cl.id = cr.cliente_id WHERE cl.cedula LIKE '91000%' AND p.fecha < CURDATE()
    UNION SELECT DISTINCT g.fecha FROM gastos g JOIN usuarios u ON u.id = g.cobrador_id WHERE u.usuario LIKE 'demo.%' AND g.fecha < CURDATE()
    ORDER BY d")->fetchAll(PDO::FETCH_COLUMN);
foreach ($diasCaja as $d) {
    foreach ($cobIds as $cid) {
        $r = $caja->resumenDia($d, $cid)[0] ?? null;
        if (!$r || ($r['efectivo'] <= 0 && $r['gastos'] <= 0)) {
            continue;
        }
        $entregado = $r['a_entregar'];
        $obs = null;
        if ($chance(0.05) && $entregado > 50000) {
            $entregado -= mt_rand(1, 4) * 5000;
            $obs = 'Faltante: dice que dio mal un vuelto';
        } elseif ($chance(0.03)) {
            $entregado += 2000;
            $obs = 'Sobrante de vueltos';
        }
        $caja->cerrar($cid, $d, max(0, $entregado), $obs, $adminId);
        $db->prepare('UPDATE cierres SET created_at = ? WHERE cobrador_id = ? AND fecha = ?')
            ->execute([$d . ' 18:' . sprintf('%02d', mt_rand(10, 55)) . ':00', $cid, $d]);
        $nCierres++;
    }
}

// Un pago anulado de ejemplo (error de digitación)
$p = $db->query("SELECT p.id FROM pagos p JOIN creditos c ON c.id = p.credito_id JOIN clientes cl ON cl.id = c.cliente_id
    WHERE cl.cedula LIKE '91000%' ORDER BY p.id DESC LIMIT 1 OFFSET 7")->fetchColumn();
if ($p) {
    $pagoModel->anular((int) $p, 'Ejemplo: se digitó dos veces el mismo pago', $adminId);
}

$res = $db->query("SELECT COUNT(*) AS creditos, SUM(cr.estado = 'activo') AS activos, SUM(cr.estado = 'pagado') AS pagados,
        SUM(CASE WHEN cr.estado = 'activo' THEN cr.saldo ELSE 0 END) AS cartera
    FROM creditos cr JOIN clientes cl ON cl.id = cr.cliente_id WHERE cl.cedula LIKE '91000%'")->fetch();
Auditoria::log('config_editada', "Datos de demostración cargados: {$total} clientes, {$nCred} créditos, {$nPagos} pagos", null, null, $adminId);

echo "Créditos creados: {$res['creditos']} ({$res['activos']} activos, {$res['pagados']} pagados)\n";
echo "Pagos registrados: {$nPagos}\n";
echo "Renovaciones: {$nRenov} · Gestiones de cobro: {$nGest} · Gastos de ruta: {$nGastos} · Cierres de caja: {$nCierres}\n";
echo 'Cartera de prueba por cobrar: ' . money($res['cartera']) . "\n\n";
echo "Acceso de los cobradores de prueba (contraseña para todos: " . CLAVE_DEMO . "):\n";
foreach ($cobradores as [$nombre, , , $user, $zona]) {
    echo "  - {$user}  ({$nombre}, zona {$zona})\n";
}
