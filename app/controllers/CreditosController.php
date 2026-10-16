<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Gestion;
use App\Models\Pago;
use App\Models\Usuario;
use App\Services\ReporteExcel;

class CreditosController extends Controller
{
    private Credito $model;

    public function __construct()
    {
        $this->requireLogin();
        $this->model = new Credito();
    }

    public function index(): void
    {
        $f = [
            'q'           => (string) $this->input('q', ''),
            'estado'      => (string) $this->input('estado', 'activo'),
            'cobrador_id' => (string) $this->input('cobrador_id', ''),
            'mora'        => (string) $this->input('mora', ''),
        ];
        $this->view('creditos/index', [
            'title'      => 'Créditos',
            'creditos'   => $this->model->listar($f, Auth::scopeCobrador()),
            'cobradores' => (new Usuario())->cobradores(false),
            'f'          => $f,
        ]);
    }

    public function crear(): void
    {
        $this->requireRole('admin');
        $clientes = (new Cliente())->paraSelect();
        $this->view('creditos/crear', [
            'title'          => 'Nuevo crédito',
            'clientes'       => $clientes,
            'clienteId'      => (int) $this->input('cliente', 0),
            'calificaciones' => (new Cliente())->calificaciones(array_column($clientes, 'id')),
            'renovar'        => null,
        ]);
    }

    /** Formulario de renovación: el saldo del crédito actual se descuenta del nuevo desembolso. */
    public function renovar(string $id): void
    {
        $this->requireRole('admin');
        $cr = $this->cargar((int) $id);
        if ($cr['estado'] !== 'activo') {
            flash('warning', 'Solo se pueden renovar créditos activos.');
            $this->redirect('creditos/ver/' . $id);
        }
        $this->view('creditos/crear', [
            'title'          => 'Renovar crédito ' . $cr['codigo'],
            'clientes'       => [],
            'clienteId'      => (int) $cr['cliente_id'],
            'calificaciones' => (new Cliente())->calificaciones([(int) $cr['cliente_id']]),
            'renovar'        => $cr,
        ]);
    }

    /** Vista previa del plan de cuotas (AJAX). */
    public function simular(): void
    {
        $this->requireRole('admin');
        $d = $this->datos();
        if ($err = $this->validar($d, false)) {
            $this->json(['ok' => false, 'error' => $err], 422);
        }
        $plan = Credito::calcularPlan($d['monto'], $d['interes'], $d['num_cuotas'], $d['frecuencia'],
            $d['fecha_primera_cuota'], $d['excluir_domingos']);
        $this->json(['ok' => true, 'plan' => $plan]);
    }

    public function guardar(): void
    {
        $this->requireRole('admin');
        $this->requirePost();
        $d = $this->datos();
        $renovarId = (int) $this->input('renovar_id', 0);
        if ($renovarId) {
            $ant = $this->model->detalle($renovarId);
            if (!$ant || $ant['estado'] !== 'activo') {
                flash('danger', 'El crédito a renovar no existe o ya no está activo.');
                $this->redirect('creditos');
            }
            $d['cliente_id'] = (int) $ant['cliente_id'];
            $err = $this->validar($d, true) ?? ($d['monto'] <= (float) $ant['saldo']
                ? 'El nuevo monto debe ser mayor al saldo pendiente (' . money($ant['saldo']) . ').' : null);
            if ($err) {
                $this->withOld();
                flash('danger', $err);
                $this->redirect('creditos/renovar/' . $renovarId);
            }
            try {
                $id = $this->model->renovar($renovarId, $d, Auth::id());
            } catch (\DomainException $e) {
                flash('danger', $e->getMessage());
                $this->redirect('creditos/ver/' . $renovarId);
            }
            Auditoria::log('credito_renovado', $ant['codigo'] . ' → ' . sprintf('CR-%05d', $id) . ' · nuevo monto ' . money($d['monto'])
                . ' · saldo descontado ' . money($ant['saldo']) . ' · neto entregado ' . money($d['monto'] - $ant['saldo']), 'credito', $id);
            flash('success', 'Crédito renovado. Entrega al cliente ' . money($d['monto'] - $ant['saldo'])
                . ' (monto ' . money($d['monto']) . ' menos saldo anterior ' . money($ant['saldo']) . ').');
            $this->redirect('creditos/ver/' . $id);
        }
        if ($err = $this->validar($d, true)) {
            $this->withOld();
            flash('danger', $err);
            $this->redirect('creditos/crear');
        }
        $id = $this->model->crear($d, Auth::id());
        Auditoria::log('credito_creado', 'Cliente #' . $d['cliente_id'] . ' · ' . money($d['monto']) . ' al ' . $d['interes'] . ' % en ' . $d['num_cuotas'] . ' cuotas ' . $d['frecuencia'], 'credito', $id);
        flash('success', 'Crédito creado. Plan de ' . $d['num_cuotas'] . ' cuotas generado.');
        $this->redirect('creditos/ver/' . $id);
    }

    public function ver(string $id): void
    {
        $cr = $this->cargar((int) $id);
        $this->view('creditos/ver', [
            'title'      => 'Crédito ' . $cr['codigo'],
            'cr'         => $cr,
            'cuotas'     => $this->model->cuotas((int) $id),
            'pagos'      => (new Pago())->porCredito((int) $id),
            'cobradores' => (new Usuario())->cobradores(),
            'gestiones'  => (new Gestion())->porCredito((int) $id),
            'calificacion' => (new Cliente())->calificaciones([(int) $cr['cliente_id']])[(int) $cr['cliente_id']] ?? null,
        ]);
    }

    /** Estado de cuenta imprimible (carta). */
    public function estado(string $id): void
    {
        $cr = $this->cargar((int) $id);
        $this->view('creditos/estado', [
            'title'   => 'Estado de cuenta ' . $cr['codigo'],
            'cr'      => $cr,
            'cliente' => (new Cliente())->detalle((int) $cr['cliente_id']),
            'cuotas'  => $this->model->cuotas((int) $id),
            'pagos'   => (new Pago())->porCredito((int) $id),
        ], 'documento');
    }

    /** Certificado de paz y salvo (solo créditos pagados). */
    public function pazysalvo(string $id): void
    {
        $cr = $this->cargar((int) $id);
        if ($cr['estado'] !== 'pagado') {
            flash('warning', 'El paz y salvo solo se expide cuando el crédito está totalmente pagado.');
            $this->redirect('creditos/ver/' . $id);
        }
        $pagos = (new Pago())->porCredito((int) $id);
        $vigentes = array_filter($pagos, fn ($p) => !$p['anulado']);
        $this->view('creditos/pazysalvo', [
            'title'      => 'Paz y salvo ' . $cr['codigo'],
            'cr'         => $cr,
            'cliente'    => (new Cliente())->detalle((int) $cr['cliente_id']),
            'ultimoPago' => $vigentes ? max(array_column($vigentes, 'fecha')) : null,
        ], 'documento');
    }

    /** Estado de cuenta en Excel: resumen, plan de cuotas y pagos. */
    public function excel(string $id): void
    {
        $cr = $this->cargar((int) $id);
        $cuotas = $this->model->cuotas((int) $id);
        $pagos = (new Pago())->porCredito((int) $id);
        $hoy = date('Y-m-d');
        foreach ($cuotas as &$c) {
            $c['pendiente'] = (float) $c['valor'] - (float) $c['abonado'];
            $c['estado_txt'] = $c['estado'] !== 'pagada' && $c['fecha_vencimiento'] < $hoy && $cr['estado'] === 'activo' ? 'Vencida' : ucfirst($c['estado']);
            $c['atraso'] = $c['fecha_pago'] ? max(0, dias_entre($c['fecha_vencimiento'], $c['fecha_pago']))
                : ($c['fecha_vencimiento'] < $hoy ? dias_entre($c['fecha_vencimiento'], $hoy) : null);
        }
        unset($c);
        foreach ($pagos as &$p) {
            $p['metodo_txt'] = metodo_label($p['metodo']);
            $p['estado_txt'] = $p['anulado'] ? 'Anulado' : 'Vigente';
        }
        unset($p);
        $tono = ['Pagada' => 'success', 'Parcial' => 'info', 'Pendiente' => 'muted', 'Vencida' => 'danger'];
        Auditoria::log('exportacion', 'Estado de cuenta ' . $cr['codigo'] . ' (Excel)', 'credito', (int) $id);
        (new ReporteExcel('Estado de cuenta · Crédito ' . $cr['codigo'],
            $cr['cliente'] . ' · C.C. ' . $cr['cedula'] . ' · Cobrador: ' . ($cr['cobrador'] ?? 'Sin asignar')))
            ->hoja('Plan de cuotas', [
                ['label' => 'Cuota', 'key' => 'numero', 'type' => 'int', 'width' => 8, 'total' => 'TOTAL'],
                ['label' => 'Vencimiento', 'key' => 'fecha_vencimiento', 'type' => 'date', 'width' => 14],
                ['label' => 'Valor', 'key' => 'valor', 'type' => 'money', 'width' => 15, 'total' => 'sum'],
                ['label' => 'Abonado', 'key' => 'abonado', 'type' => 'money', 'width' => 15, 'total' => 'sum'],
                ['label' => 'Pendiente', 'key' => 'pendiente', 'type' => 'money', 'width' => 15, 'total' => 'sum'],
                ['label' => 'Pagada el', 'key' => 'fecha_pago', 'type' => 'date', 'width' => 14],
                ['label' => 'Días de atraso', 'key' => 'atraso', 'type' => 'int', 'width' => 12],
                ['label' => 'Estado', 'key' => 'estado_txt', 'type' => 'badge', 'width' => 13],
            ], $cuotas, [
                'kpis' => [
                    ['Prestado', (float) $cr['monto'], 'money', 'primary'],
                    ['Total a pagar', (float) $cr['total'], 'money', 'info'],
                    ['Pagado', (float) $cr['total_pagado'], 'money', 'success'],
                    ['Saldo', (float) $cr['saldo'], 'money', $cr['vencido'] > 0 ? 'danger' : 'warning'],
                ],
                'badgeTone' => fn ($v) => $tono[$v] ?? null,
                'nota' => 'Crédito de ' . money($cr['monto']) . ' al ' . (float) $cr['interes'] . ' % en ' . $cr['num_cuotas'] . ' cuotas '
                    . frecuencia_plural($cr['frecuencia']) . ' de ' . money($cr['valor_cuota']) . '. Desembolso: ' . fecha($cr['fecha_inicio'])
                    . '. Estado del crédito: ' . ucfirst($cr['estado']) . '.',
            ])
            ->hoja('Pagos', [
                ['label' => 'Recibo', 'key' => 'recibo', 'width' => 12, 'total' => 'TOTAL'],
                ['label' => 'Fecha', 'key' => 'fecha', 'type' => 'datetime', 'width' => 20],
                ['label' => 'Método', 'key' => 'metodo_txt', 'width' => 15],
                ['label' => 'Recibido por', 'key' => 'cobrador', 'width' => 24],
                ['label' => 'Nota', 'key' => 'nota', 'width' => 32, 'wrap' => true],
                ['label' => 'Estado', 'key' => 'estado_txt', 'type' => 'badge', 'width' => 11],
                ['label' => 'Valor', 'key' => 'monto', 'type' => 'money', 'width' => 15, 'total' => 'sum'],
            ], array_values(array_filter($pagos, fn ($p) => !$p['anulado'])) ?: [], [
                'titulo' => 'Pagos vigentes del crédito ' . $cr['codigo'],
                'badgeTone' => fn ($v) => $v === 'Anulado' ? 'danger' : 'success',
            ])
            ->descargar('estado_cuenta_' . $cr['codigo']);
    }

    public function anular(string $id): void
    {
        $this->requireRole('admin');
        $this->requirePost();
        $cr = $this->cargar((int) $id);
        $motivo = trim((string) $this->input('motivo', ''));
        if ($cr['estado'] !== 'activo') {
            flash('warning', 'Solo se pueden anular créditos activos.');
        } elseif ($this->model->tienePagos((int) $id)) {
            flash('warning', 'El crédito tiene pagos vigentes. Anula primero los pagos.');
        } elseif ($motivo === '') {
            flash('warning', 'Indica el motivo de la anulación.');
        } else {
            $this->model->anular((int) $id, mb_substr($motivo, 0, 255));
            Auditoria::log('credito_anulado', $cr['codigo'] . ' · motivo: ' . $motivo, 'credito', (int) $id);
            $extra = '';
            if ($cr['renovado_de']) {
                // Si era una renovación, el crédito anterior vuelve a quedar activo con su saldo
                $pagoModel = new Pago();
                foreach ($pagoModel->porCredito((int) $cr['renovado_de']) as $p) {
                    if ($p['metodo'] === 'renovacion' && !$p['anulado']) {
                        $pagoModel->anular((int) $p['id'], 'Se anuló la renovación ' . $cr['codigo'], Auth::id());
                    }
                }
                $extra = ' El crédito ' . $cr['renovado_de_codigo'] . ' volvió a quedar activo con su saldo.';
            }
            flash('success', 'Crédito ' . $cr['codigo'] . ' anulado.' . $extra);
        }
        $this->redirect('creditos/ver/' . $id);
    }

    // ------------------------------------------------------------------

    private function cargar(int $id): array
    {
        $cr = $this->model->detalle($id);
        if (!$cr) {
            flash('danger', 'Crédito no encontrado.');
            $this->redirect('creditos');
        }
        if (!Auth::isAdmin() && (int) $cr['cobrador_id'] !== Auth::id()) {
            flash('danger', 'Este crédito no pertenece a tu cartera.');
            $this->redirect('creditos');
        }
        return $cr;
    }

    private function datos(): array
    {
        $fechaInicio = (string) $this->input('fecha_inicio', date('Y-m-d'));
        return [
            'cliente_id'          => (int) $this->input('cliente_id', 0),
            'monto'               => parse_money($this->input('monto', 0)) ?? 0.0,
            'interes'             => (float) str_replace(',', '.', (string) $this->input('interes', 0)),
            'num_cuotas'          => (int) $this->input('num_cuotas', 0),
            'frecuencia'          => (string) $this->input('frecuencia', 'diaria'),
            'excluir_domingos'    => (bool) $this->input('excluir_domingos', false),
            'fecha_inicio'        => $fechaInicio,
            'fecha_primera_cuota' => (string) $this->input('fecha_primera_cuota', '') ?: date('Y-m-d', strtotime($fechaInicio . ' +1 day')),
            'observaciones'       => mb_substr((string) $this->input('observaciones', ''), 0, 2000),
        ];
    }

    private function validar(array $d, bool $conCliente): ?string
    {
        if ($conCliente) {
            $c = (new Cliente())->find($d['cliente_id']);
            if (!$c) {
                return 'Selecciona un cliente.';
            }
            if ($c['estado'] === 'bloqueado') {
                return 'El cliente está bloqueado; no se le puede prestar.';
            }
            if ($c['estado'] === 'inactivo') {
                return 'El cliente está inactivo. Actívalo antes de crear un crédito.';
            }
            if (!$c['cobrador_id']) {
                return 'El cliente no tiene cobrador asignado. Asígnale uno antes de prestar.';
            }
        }
        if ($d['monto'] < 1000) {
            return 'El monto debe ser de al menos $ 1.000.';
        }
        if ($d['interes'] < 0 || $d['interes'] > 500) {
            return 'El interés debe estar entre 0 % y 500 %.';
        }
        if ($d['num_cuotas'] < 1 || $d['num_cuotas'] > 365) {
            return 'El número de cuotas debe estar entre 1 y 365.';
        }
        if (!array_key_exists($d['frecuencia'], frecuencias())) {
            return 'Frecuencia no válida.';
        }
        foreach (['fecha_inicio', 'fecha_primera_cuota'] as $k) {
            $dt = \DateTime::createFromFormat('Y-m-d', $d[$k]);
            if (!$dt || $dt->format('Y-m-d') !== $d[$k]) {
                return 'Fecha no válida.';
            }
        }
        if ($d['fecha_primera_cuota'] < $d['fecha_inicio']) {
            return 'La primera cuota no puede ser anterior a la fecha del préstamo.';
        }
        return null;
    }
}
