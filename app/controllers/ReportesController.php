<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Auditoria;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Gestion;
use App\Models\Pago;
use App\Models\Reporte;
use App\Models\Usuario;
use App\Services\ReporteExcel;

class ReportesController extends Controller
{
    public const TIPOS = [
        'pagos'        => ['Pagos recibidos', 'receipt', 'Cada pago del periodo con recibo, cliente, cobrador y método.'],
        'cartera'      => ['Cartera por cobrador', 'wallet2', 'Clientes, capital, saldo, vencido, efectivo en mano y recaudo.'],
        'mora'         => ['Créditos en mora', 'exclamation-octagon', 'Días de atraso, valor vencido, última gestión y promesa.'],
        'clientes'     => ['Clientes', 'people', 'Datos de contacto, cobrador, calificación, saldo y mora.'],
        'caja'         => ['Caja y cierres', 'cash-stack', 'Cierres diarios, faltantes/sobrantes y gastos de ruta.'],
        'gestiones'    => ['Gestiones de cobro', 'telephone-outbound', 'Visitas sin pago, promesas y efectividad por cobrador.'],
        'rentabilidad' => ['Rentabilidad', 'graph-up-arrow', 'Capital colocado, recaudo, interés ganado, gastos y utilidad por mes.'],
        'completo'     => ['Informe gerencial completo', 'journal-richtext', 'Todos los anteriores en un solo libro de Excel, una hoja por tema.'],
    ];

    public function __construct()
    {
        $this->requireRole('admin');
    }

    public function index(): void
    {
        [$desde, $hasta, $cobrador] = $this->rango();
        $pago = new Pago();
        $this->view('reportes/index', [
            'title'        => 'Reportes',
            'desde'        => $desde,
            'hasta'        => $hasta,
            'cobradorId'   => $cobrador,
            'liquidacion'  => $pago->liquidacion($desde, $hasta, $cobrador),
            'totales'      => $pago->totales(['desde' => $desde, 'hasta' => $hasta, 'cobrador_id' => $cobrador]),
            'cartera'      => (new Usuario())->cartera($cobrador),
            'rentabilidad' => (new Reporte())->rentabilidad($desde, $hasta, $cobrador),
            'efectividad'  => (new Gestion())->efectividad($desde, $hasta, $cobrador),
            'cobradores'   => (new Usuario())->cobradores(false),
        ]);
    }

    /** Descarga el reporte en Excel (.xlsx) con diseño corporativo. */
    public function exportar(string $tipo): void
    {
        if (!isset(self::TIPOS[$tipo])) {
            flash('warning', 'Reporte no disponible.');
            $this->redirect('reportes');
        }
        [$desde, $hasta, $cobrador] = $this->rango();
        $nomCob = $cobrador ? ((new Usuario())->find($cobrador)['nombre'] ?? '') : 'Todos';
        $periodo = 'Periodo: ' . fecha($desde) . ' al ' . fecha($hasta) . ' · Cobrador: ' . $nomCob;
        Auditoria::log('exportacion', 'Reporte ' . self::TIPOS[$tipo][0] . " ({$desde} a {$hasta})" . ($cobrador ? " · {$nomCob}" : ''));

        $xls = new ReporteExcel(self::TIPOS[$tipo][0], $periodo);
        $hojas = $tipo === 'completo' ? ['rentabilidad', 'cartera', 'mora', 'pagos', 'caja', 'gestiones', 'clientes'] : [$tipo];
        foreach ($hojas as $h) {
            $this->{'hoja' . ucfirst($h)}($xls, $desde, $hasta, $cobrador, $tipo === 'completo');
        }
        $xls->descargar(($tipo === 'completo' ? 'informe_gerencial' : $tipo) . '_' . $desde . '_a_' . $hasta);
    }

    // ------------------------------------------------------------------ hojas

    private function hojaPagos(ReporteExcel $x, string $desde, string $hasta, ?int $cob, bool $libro): void
    {
        $rows = (new Pago())->listar(['desde' => $desde, 'hasta' => $hasta, 'cobrador_id' => $cob, 'solo_recaudo' => true]);
        $ef = 0.0;
        foreach ($rows as &$r) {
            $r['metodo_txt'] = metodo_label($r['metodo']);
            $ef += $r['metodo'] === 'efectivo' ? (float) $r['monto'] : 0;
        }
        unset($r);
        $total = array_sum(array_column($rows, 'monto'));
        $x->hoja('Pagos', [
            ['label' => 'Recibo', 'key' => 'recibo', 'width' => 12, 'total' => 'TOTAL'],
            ['label' => 'Fecha y hora', 'key' => 'fecha', 'type' => 'datetime', 'width' => 20],
            ['label' => 'Crédito', 'key' => 'codigo', 'width' => 11],
            ['label' => 'Cédula', 'key' => 'cedula', 'width' => 13],
            ['label' => 'Cliente', 'key' => 'cliente', 'width' => 30, 'total' => 'count'],
            ['label' => 'Recibido por', 'key' => 'cobrador', 'width' => 24],
            ['label' => 'Método', 'key' => 'metodo_txt', 'type' => 'badge', 'width' => 14],
            ['label' => 'Nota', 'key' => 'nota', 'width' => 26],
            ['label' => 'Valor', 'key' => 'monto', 'type' => 'money', 'width' => 15, 'total' => 'sum'],
        ], $rows, [
            'titulo' => 'Pagos recibidos',
            'kpis' => [
                ['Total recaudado', $total, 'money', 'success'],
                ['Pagos', count($rows), 'int', 'primary'],
                ['En efectivo', $ef, 'money', 'warning'],
                ['Digital / transferencia', $total - $ef, 'money', 'info'],
            ],
            'badgeTone' => fn ($v) => $v === 'Efectivo' ? 'success' : 'info',
            'nota' => 'No incluye pagos anulados ni renovaciones (que cancelan saldo sin entrada de dinero).',
        ]);
    }

    private function hojaCartera(ReporteExcel $x, string $desde, string $hasta, ?int $cob, bool $libro): void
    {
        $rows = (new Usuario())->cartera($cob);
        $mano = (new Caja())->enMano($cob);
        foreach ($rows as &$r) {
            $r['pct_mora'] = $r['cartera'] > 0 ? $r['vencido'] / $r['cartera'] : 0;
            $r['en_mano'] = (float) ($mano[$r['id']]['en_mano'] ?? 0);
            $r['estado_txt'] = $r['activo'] ? 'Activo' : 'Inactivo';
        }
        unset($r);
        $cartera = array_sum(array_column($rows, 'cartera'));
        $vencido = array_sum(array_column($rows, 'vencido'));
        $x->hoja('Cartera por cobrador', [
            ['label' => 'Cobrador', 'key' => 'nombre', 'width' => 26, 'bold' => true, 'total' => 'TOTAL'],
            ['label' => 'Zona', 'key' => 'zona', 'width' => 12],
            ['label' => 'Clientes', 'key' => 'clientes', 'type' => 'int', 'width' => 10, 'total' => 'sum'],
            ['label' => 'Créditos activos', 'key' => 'creditos_activos', 'type' => 'int', 'width' => 11, 'total' => 'sum'],
            ['label' => 'Capital prestado', 'key' => 'capital', 'type' => 'money', 'width' => 16, 'total' => 'sum'],
            ['label' => 'Saldo por cobrar', 'key' => 'cartera', 'type' => 'money', 'width' => 16, 'total' => 'sum'],
            ['label' => 'Vencido', 'key' => 'vencido', 'type' => 'money', 'width' => 14, 'total' => 'sum'],
            ['label' => '% mora', 'key' => 'pct_mora', 'type' => 'percent', 'width' => 9],
            ['label' => 'Créditos en mora', 'key' => 'creditos_mora', 'type' => 'int', 'width' => 10, 'total' => 'sum'],
            ['label' => 'Efectivo en mano', 'key' => 'en_mano', 'type' => 'money', 'width' => 15, 'total' => 'sum'],
            ['label' => 'Recaudo hoy', 'key' => 'recaudo_hoy', 'type' => 'money', 'width' => 14, 'total' => 'sum'],
            ['label' => 'Recaudo mes', 'key' => 'recaudo_mes', 'type' => 'money', 'width' => 15, 'total' => 'sum'],
            ['label' => 'Estado', 'key' => 'estado_txt', 'type' => 'badge', 'width' => 10],
        ], $rows, [
            'titulo' => 'Cartera por cobrador (al ' . fecha(date('Y-m-d')) . ')',
            'kpis' => [
                ['Cartera total', $cartera, 'money', 'primary'],
                ['Vencido', $vencido, 'money', 'danger'],
                ['% de mora', $cartera > 0 ? $vencido / $cartera : 0, 'percent', 'warning'],
                ['Clientes', array_sum(array_column($rows, 'clientes')), 'int', 'info'],
                ['Efectivo en mano', array_sum(array_column($rows, 'en_mano')), 'money', 'success'],
                ['Recaudo del mes', array_sum(array_column($rows, 'recaudo_mes')), 'money', 'success'],
            ],
            'rowTone' => fn ($r) => $r['activo'] ? null : 'muted',
            'badgeTone' => fn ($v) => $v === 'Activo' ? 'success' : 'muted',
            'nota' => 'Cartera = saldo de los créditos activos de los clientes asignados. Efectivo en mano = efectivo cobrado − gastos − entregado en cierres de caja.',
        ]);
    }

    private function hojaMora(ReporteExcel $x, string $desde, string $hasta, ?int $cob, bool $libro): void
    {
        $rows = (new Credito())->listar(['estado' => 'activo', 'mora' => 1, 'cobrador_id' => $cob]);
        $ult = (new Gestion())->ultimas(array_column($rows, 'id'));
        $cal = (new Cliente())->calificaciones(array_column($rows, 'cliente_id'));
        $res = Gestion::resultados();
        foreach ($rows as &$r) {
            $r['dias'] = dias_entre($r['mora_desde'], date('Y-m-d'));
            $r['rango'] = $r['dias'] > 60 ? 'Más de 60 días' : ($r['dias'] > 30 ? '31 a 60 días' : ($r['dias'] > 7 ? '8 a 30 días' : '1 a 7 días'));
            $g = $ult[$r['id']] ?? null;
            $r['gestion'] = $g ? fecha($g['created_at']) . ' · ' . $res[$g['resultado']] : 'Sin gestión';
            $r['promesa'] = $g && $g['resultado'] === 'promesa'
                ? fecha($g['fecha_promesa']) . ($g['monto_promesa'] ? ' · ' . money($g['monto_promesa']) : '') . ' · ' . ucfirst($g['estado_promesa']) : '';
            $r['calif'] = $cal[$r['cliente_id']]['letra'] ?? 'N';
            $r['cobrador'] ??= 'Sin asignar';
        }
        unset($r);
        usort($rows, fn ($a, $b) => $b['dias'] <=> $a['dias']);
        $vencido = array_sum(array_column($rows, 'vencido'));
        $x->hoja('Mora', [
            ['label' => 'Crédito', 'key' => 'codigo', 'width' => 11, 'total' => 'TOTAL'],
            ['label' => 'Cliente', 'key' => 'cliente', 'width' => 28, 'bold' => true, 'total' => 'count'],
            ['label' => 'Cédula', 'key' => 'cedula', 'width' => 13],
            ['label' => 'Teléfono', 'key' => 'telefono1', 'width' => 13],
            ['label' => 'Dirección', 'key' => 'direccion', 'width' => 24],
            ['label' => 'Cobrador', 'key' => 'cobrador', 'width' => 22],
            ['label' => 'Calif.', 'key' => 'calif', 'type' => 'badge', 'width' => 7],
            ['label' => 'Vencido desde', 'key' => 'mora_desde', 'type' => 'date', 'width' => 13],
            ['label' => 'Días', 'key' => 'dias', 'type' => 'int', 'width' => 7],
            ['label' => 'Rango', 'key' => 'rango', 'type' => 'badge', 'width' => 15],
            ['label' => 'Cuotas vencidas', 'key' => 'cuotas_vencidas', 'type' => 'int', 'width' => 9],
            ['label' => 'Valor vencido', 'key' => 'vencido', 'type' => 'money', 'width' => 15, 'total' => 'sum'],
            ['label' => 'Saldo total', 'key' => 'saldo', 'type' => 'money', 'width' => 15, 'total' => 'sum'],
            ['label' => 'Última gestión', 'key' => 'gestion', 'width' => 28],
            ['label' => 'Promesa de pago', 'key' => 'promesa', 'width' => 28],
        ], $rows, [
            'titulo' => 'Créditos en mora (al ' . fecha(date('Y-m-d')) . ')',
            'kpis' => [
                ['Valor vencido', $vencido, 'money', 'danger'],
                ['Créditos en mora', count($rows), 'int', 'warning'],
                ['Más de 30 días', count(array_filter($rows, fn ($r) => $r['dias'] > 30)), 'int', 'danger'],
                ['Sin gestión', count(array_filter($rows, fn ($r) => $r['gestion'] === 'Sin gestión')), 'int', 'muted'],
                ['Saldo en riesgo', array_sum(array_column($rows, 'saldo')), 'money', 'primary'],
            ],
            'badgeTone' => fn ($v) => match ($v) {
                'Más de 60 días', 'D' => 'danger', '31 a 60 días', 'C' => 'warning', '8 a 30 días' => 'info', 'A' => 'success', 'B' => 'primary', default => 'muted',
            },
            'vacio' => '¡No hay créditos en mora!',
            'nota' => 'Ordenado de mayor a menor atraso. Saldo en riesgo = saldo total de los créditos en mora.',
        ]);
    }

    private function hojaClientes(ReporteExcel $x, string $desde, string $hasta, ?int $cob, bool $libro): void
    {
        $rows = (new Cliente())->listar(['cobrador_id' => $cob ? (string) $cob : '']);
        $cal = (new Cliente())->calificaciones(array_column($rows, 'id'));
        foreach ($rows as &$r) {
            $c = $cal[$r['id']] ?? null;
            $r['calif'] = $c['letra'] ?? 'N';
            $r['puntualidad'] = $c && $c['evaluadas'] ? $c['puntualidad'] : null;
            $r['estado_txt'] = ucfirst($r['estado']);
            $r['cobrador'] ??= 'Sin asignar';
            $r['situacion'] = $r['vencido'] > 0 ? 'En mora' : ($r['creditos_activos'] > 0 ? 'Al día' : 'Sin crédito');
        }
        unset($r);
        $x->hoja('Clientes', [
            ['label' => 'Cédula', 'key' => 'cedula', 'width' => 13, 'total' => 'TOTAL'],
            ['label' => 'Nombres', 'key' => 'nombres', 'width' => 18, 'total' => 'count'],
            ['label' => 'Apellidos', 'key' => 'apellidos', 'width' => 20],
            ['label' => 'Teléfono 1', 'key' => 'telefono1', 'width' => 13],
            ['label' => 'Teléfono 2', 'key' => 'telefono2', 'width' => 13],
            ['label' => 'Correo', 'key' => 'email', 'width' => 24],
            ['label' => 'Dirección', 'key' => 'direccion', 'width' => 24],
            ['label' => 'Barrio', 'key' => 'barrio', 'width' => 16],
            ['label' => 'Ciudad', 'key' => 'ciudad', 'width' => 14],
            ['label' => 'Ocupación', 'key' => 'ocupacion', 'width' => 18],
            ['label' => 'Referencia', 'key' => 'referencia_nombre', 'width' => 20],
            ['label' => 'Tel. referencia', 'key' => 'referencia_telefono', 'width' => 13],
            ['label' => 'Cobrador', 'key' => 'cobrador', 'width' => 22],
            ['label' => 'Calif.', 'key' => 'calif', 'type' => 'badge', 'width' => 7],
            ['label' => 'Puntualidad', 'key' => 'puntualidad', 'type' => 'percent', 'width' => 11],
            ['label' => 'Situación', 'key' => 'situacion', 'type' => 'badge', 'width' => 12],
            ['label' => 'Saldo', 'key' => 'saldo', 'type' => 'money', 'width' => 14, 'total' => 'sum'],
            ['label' => 'Vencido', 'key' => 'vencido', 'type' => 'money', 'width' => 14, 'total' => 'sum'],
            ['label' => 'Estado', 'key' => 'estado_txt', 'type' => 'badge', 'width' => 11],
        ], $rows, [
            'titulo' => 'Directorio de clientes',
            'kpis' => [
                ['Clientes', count($rows), 'int', 'primary'],
                ['Al día', count(array_filter($rows, fn ($r) => $r['situacion'] === 'Al día')), 'int', 'success'],
                ['En mora', count(array_filter($rows, fn ($r) => $r['situacion'] === 'En mora')), 'int', 'danger'],
                ['Calificación A', count(array_filter($rows, fn ($r) => $r['calif'] === 'A')), 'int', 'success'],
                ['Calificación D', count(array_filter($rows, fn ($r) => $r['calif'] === 'D')), 'int', 'danger'],
            ],
            'rowTone' => fn ($r) => $r['estado'] === 'inactivo' ? 'muted' : null,
            'badgeTone' => fn ($v) => match ($v) {
                'A', 'Al día', 'Activo' => 'success', 'B' => 'primary', 'C' => 'warning', 'D', 'En mora', 'Bloqueado' => 'danger', default => 'muted',
            },
            'nota' => 'Calificación: A excelente (≥90 % de cuotas a tiempo), B bueno (≥75 %), C regular (≥50 %), D riesgoso, N sin historial suficiente.',
        ]);
    }

    private function hojaCaja(ReporteExcel $x, string $desde, string $hasta, ?int $cob, bool $libro): void
    {
        $caja = new Caja();
        $cierres = $caja->historial($cob, $desde, $hasta);
        foreach ($cierres as &$c) {
            $c['resultado'] = abs((float) $c['diferencia']) < 0.5 ? 'Cuadrado' : ($c['diferencia'] < 0 ? 'Faltante' : 'Sobrante');
        }
        unset($c);
        $faltantes = array_sum(array_map(fn ($c) => min(0, (float) $c['diferencia']), $cierres));
        $x->hoja('Cierres de caja', [
            ['label' => 'Fecha', 'key' => 'fecha', 'type' => 'date', 'width' => 12, 'total' => 'TOTAL'],
            ['label' => 'Cobrador', 'key' => 'cobrador', 'width' => 24, 'total' => 'count'],
            ['label' => 'Efectivo cobrado', 'key' => 'recaudo_efectivo', 'type' => 'money', 'width' => 15, 'total' => 'sum'],
            ['label' => 'Digital', 'key' => 'recaudo_digital', 'type' => 'money', 'width' => 14, 'total' => 'sum'],
            ['label' => 'Gastos', 'key' => 'gastos', 'type' => 'money', 'width' => 13, 'total' => 'sum'],
            ['label' => 'A entregar', 'key' => 'a_entregar', 'type' => 'money', 'width' => 15, 'total' => 'sum'],
            ['label' => 'Entregado', 'key' => 'entregado', 'type' => 'money', 'width' => 15, 'total' => 'sum'],
            ['label' => 'Diferencia', 'key' => 'diferencia', 'type' => 'money', 'width' => 14, 'total' => 'sum'],
            ['label' => 'Resultado', 'key' => 'resultado', 'type' => 'badge', 'width' => 12],
            ['label' => 'Recibió', 'key' => 'recibido', 'width' => 20],
            ['label' => 'Observaciones', 'key' => 'observaciones', 'width' => 28],
        ], $cierres, [
            'titulo' => 'Cierres de caja',
            'kpis' => [
                ['Entregado', array_sum(array_column($cierres, 'entregado')), 'money', 'success'],
                ['Gastos de ruta', array_sum(array_column($cierres, 'gastos')), 'money', 'warning'],
                ['Faltantes', $faltantes, 'money', 'danger'],
                ['Cierres', count($cierres), 'int', 'primary'],
            ],
            'badgeTone' => fn ($v) => ['Cuadrado' => 'success', 'Faltante' => 'danger', 'Sobrante' => 'info'][$v] ?? null,
            'nota' => 'Diferencia negativa = faltante (el cobrador entregó menos de lo que debía).',
        ]);
        $gastos = $caja->gastos($cob, $desde, $hasta);
        foreach ($gastos as &$g) {
            $g['concepto_txt'] = Caja::conceptos()[$g['concepto']] ?? $g['concepto'];
        }
        unset($g);
        $vig = array_values(array_filter($gastos, fn ($g) => !$g['anulado']));
        $x->hoja('Gastos de ruta', [
            ['label' => 'Fecha', 'key' => 'fecha', 'type' => 'date', 'width' => 12, 'total' => 'TOTAL'],
            ['label' => 'Cobrador', 'key' => 'cobrador', 'width' => 24, 'total' => 'count'],
            ['label' => 'Concepto', 'key' => 'concepto_txt', 'type' => 'badge', 'width' => 20],
            ['label' => 'Detalle', 'key' => 'nota', 'width' => 32],
            ['label' => 'Registrado por', 'key' => 'registrado', 'width' => 22],
            ['label' => 'Valor', 'key' => 'valor', 'type' => 'money', 'width' => 14, 'total' => 'sum'],
        ], $vig, [
            'titulo' => 'Gastos de ruta',
            'badgeTone' => fn () => 'warning',
            'kpis' => [['Total gastos', array_sum(array_column($vig, 'valor')), 'money', 'warning'], ['Registros', count($vig), 'int', 'primary']],
        ]);
    }

    private function hojaGestiones(ReporteExcel $x, string $desde, string $hasta, ?int $cob, bool $libro): void
    {
        $g = new Gestion();
        $ef = $g->efectividad($desde, $hasta, $cob);
        $x->hoja('Efectividad promesas', [
            ['label' => 'Cobrador', 'key' => 'cobrador', 'width' => 26, 'bold' => true, 'total' => 'TOTAL'],
            ['label' => 'Gestiones', 'key' => 'gestiones', 'type' => 'int', 'width' => 11, 'total' => 'sum'],
            ['label' => 'Promesas', 'key' => 'promesas', 'type' => 'int', 'width' => 11, 'total' => 'sum'],
            ['label' => 'Cumplidas', 'key' => 'cumplidas', 'type' => 'int', 'width' => 11, 'total' => 'sum'],
            ['label' => 'Incumplidas', 'key' => 'incumplidas', 'type' => 'int', 'width' => 11, 'total' => 'sum'],
            ['label' => 'Pendientes', 'key' => 'pendientes', 'type' => 'int', 'width' => 11, 'total' => 'sum'],
            ['label' => '% cumplimiento', 'key' => 'efectividad', 'type' => 'percent', 'width' => 13],
        ], $ef, [
            'titulo' => 'Efectividad de las promesas de pago',
            'rowTone' => fn ($r) => $r['efectividad'] !== null && $r['efectividad'] < 0.5 ? 'danger' : null,
            'nota' => '% cumplimiento = cumplidas / (cumplidas + incumplidas). Las promesas aún vigentes no se cuentan.',
        ]);
        $rows = $g->listar(['desde' => $desde, 'hasta' => $hasta, 'cobrador_id' => $cob]);
        foreach ($rows as &$r) {
            $r['tipo_txt'] = Gestion::tipos()[$r['tipo']];
            $r['resultado_txt'] = Gestion::resultados()[$r['resultado']];
            $r['estado_txt'] = $r['estado_promesa'] ? ucfirst($r['estado_promesa']) : '';
        }
        unset($r);
        $x->hoja('Gestiones', [
            ['label' => 'Fecha', 'key' => 'created_at', 'type' => 'datetime', 'width' => 19, 'total' => 'TOTAL'],
            ['label' => 'Crédito', 'key' => 'codigo', 'width' => 11],
            ['label' => 'Cliente', 'key' => 'cliente', 'width' => 28, 'total' => 'count'],
            ['label' => 'Teléfono', 'key' => 'telefono1', 'width' => 13],
            ['label' => 'Gestionó', 'key' => 'usuario', 'width' => 22],
            ['label' => 'Tipo', 'key' => 'tipo_txt', 'width' => 10],
            ['label' => 'Resultado', 'key' => 'resultado_txt', 'type' => 'badge', 'width' => 22],
            ['label' => 'Fecha promesa', 'key' => 'fecha_promesa', 'type' => 'date', 'width' => 13],
            ['label' => 'Valor prometido', 'key' => 'monto_promesa', 'type' => 'money', 'width' => 14],
            ['label' => 'Promesa', 'key' => 'estado_txt', 'type' => 'badge', 'width' => 12],
            ['label' => 'Nota', 'key' => 'nota', 'width' => 30],
        ], $rows, [
            'titulo' => 'Detalle de gestiones de cobro',
            'badgeTone' => fn ($v) => match ($v) {
                'Cumplida' => 'success', 'Incumplida', 'Se niega a pagar' => 'danger', 'Pendiente', 'Promesa de pago' => 'info', '' => null, default => 'muted',
            },
        ]);
    }

    private function hojaRentabilidad(ReporteExcel $x, string $desde, string $hasta, ?int $cob, bool $libro): void
    {
        $rows = (new Reporte())->rentabilidad($desde, $hasta, $cob);
        $sum = fn ($k) => array_sum(array_column($rows, $k));
        $x->hoja('Rentabilidad', [
            ['label' => 'Mes', 'key' => 'mes_txt', 'width' => 16, 'bold' => true, 'total' => 'TOTAL'],
            ['label' => 'Créditos nuevos', 'key' => 'creditos', 'type' => 'int', 'width' => 10, 'total' => 'sum'],
            ['label' => 'Renovaciones', 'key' => 'renovaciones', 'type' => 'int', 'width' => 11, 'total' => 'sum'],
            ['label' => 'Capital colocado', 'key' => 'capital', 'type' => 'money', 'width' => 16, 'total' => 'sum'],
            ['label' => 'Efectivo entregado', 'key' => 'neto', 'type' => 'money', 'width' => 16, 'total' => 'sum'],
            ['label' => 'Interés pactado', 'key' => 'interes_pactado', 'type' => 'money', 'width' => 15, 'total' => 'sum'],
            ['label' => 'Recaudo', 'key' => 'recaudo', 'type' => 'money', 'width' => 15, 'total' => 'sum'],
            ['label' => 'Capital recuperado', 'key' => 'capital_recuperado', 'type' => 'money', 'width' => 16, 'total' => 'sum'],
            ['label' => 'Interés ganado', 'key' => 'interes_cobrado', 'type' => 'money', 'width' => 15, 'total' => 'sum'],
            ['label' => 'Gastos de ruta', 'key' => 'gastos', 'type' => 'money', 'width' => 14, 'total' => 'sum'],
            ['label' => 'Utilidad', 'key' => 'utilidad', 'type' => 'money', 'width' => 15, 'total' => 'sum', 'bold' => true],
            ['label' => 'Margen', 'key' => 'margen', 'type' => 'percent', 'width' => 9],
        ], $rows, [
            'titulo' => 'Rentabilidad del negocio por mes',
            'kpis' => [
                ['Capital colocado', $sum('capital'), 'money', 'primary'],
                ['Recaudo', $sum('recaudo'), 'money', 'info'],
                ['Interés ganado', $sum('interes_cobrado'), 'money', 'success'],
                ['Gastos de ruta', $sum('gastos'), 'money', 'warning'],
                ['Utilidad', $sum('utilidad'), 'money', $sum('utilidad') >= 0 ? 'success' : 'danger'],
            ],
            'rowTone' => fn ($r) => $r['utilidad'] < 0 ? 'danger' : null,
            'nota' => 'Cada pago se divide en capital e interés en la misma proporción de su crédito; la utilidad se reconoce a medida que se cobra. '
                . 'Efectivo entregado descuenta el saldo refinanciado en las renovaciones. Utilidad = interés ganado − gastos de ruta.',
        ]);
    }

    // ------------------------------------------------------------------

    private function rango(): array
    {
        $desde = (string) $this->input('desde', date('Y-m-01'));
        $hasta = (string) $this->input('hasta', date('Y-m-d'));
        $valid = fn ($d) => ($x = \DateTime::createFromFormat('Y-m-d', $d)) && $x->format('Y-m-d') === $d;
        if (!$valid($desde)) {
            $desde = date('Y-m-01');
        }
        if (!$valid($hasta)) {
            $hasta = date('Y-m-d');
        }
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        // Máximo 2 años por consulta
        if (strtotime($hasta) - strtotime($desde) > 731 * 86400) {
            $desde = date('Y-m-d', strtotime($hasta . ' -2 years'));
        }
        return [$desde, $hasta, (int) $this->input('cobrador_id', 0) ?: null];
    }
}
