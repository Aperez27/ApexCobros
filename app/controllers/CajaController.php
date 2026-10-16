<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Auditoria;
use App\Models\Caja;
use App\Models\Usuario;

/**
 * Caja del cobrador: gastos de ruta y cierre diario (entrega del efectivo).
 *  - El cobrador ve su caja del día, registra gastos y consulta su historial.
 *  - El administrador recibe el dinero de cada cobrador y registra el cierre (faltantes/sobrantes).
 */
class CajaController extends Controller
{
    private Caja $model;

    public function __construct()
    {
        $this->requireLogin();
        $this->model = new Caja();
    }

    public function index(): void
    {
        $fecha = $this->fecha((string) $this->input('fecha', date('Y-m-d')));
        $scope = Auth::scopeCobrador();
        $desde = date('Y-m-d', strtotime($fecha . ' -6 days'));
        $this->view('caja/index', [
            'title'     => Auth::isAdmin() ? 'Caja y cierres' : 'Mi caja',
            'fecha'     => $fecha,
            'resumen'   => $this->model->resumenDia($fecha, $scope),
            'gastos'    => $this->model->gastos($scope, $fecha, $fecha),
            'historial' => $this->model->historial($scope, $desde, $fecha),
            'enMano'    => $this->model->enMano($scope),
            'cobradores' => Auth::isAdmin() ? (new Usuario())->cobradores() : [],
        ]);
    }

    public function gasto(): void
    {
        $this->requirePost();
        $fecha = $this->fecha((string) $this->input('fecha', date('Y-m-d')));
        $cobradorId = Auth::isAdmin() ? (int) $this->input('cobrador_id', 0) : Auth::id();
        $valor = parse_money($this->input('valor', ''));
        $concepto = (string) $this->input('concepto', '');
        $volver = 'caja?fecha=' . $fecha;

        $cob = (new Usuario())->find($cobradorId);
        if (!$cob || $cob['rol'] !== 'cobrador') {
            flash('danger', 'Selecciona un cobrador válido.');
            $this->redirect($volver);
        }
        if (!Auth::isAdmin() && $fecha !== date('Y-m-d')) {
            flash('warning', 'Solo puedes registrar gastos del día de hoy.');
            $this->redirect('caja');
        }
        if ($valor === null || $valor <= 0 || $valor > 2000000) {
            flash('danger', 'Ingresa un valor válido (máximo $ 2.000.000).');
            $this->redirect($volver);
        }
        if (!array_key_exists($concepto, Caja::conceptos())) {
            flash('danger', 'Selecciona el concepto del gasto.');
            $this->redirect($volver);
        }
        if ($this->model->diaCerrado($cobradorId, $fecha) && !Auth::isAdmin()) {
            flash('warning', 'La caja de hoy ya fue cerrada. Pide al administrador que registre el gasto.');
            $this->redirect($volver);
        }
        $id = $this->model->registrarGasto($cobradorId, $fecha, $concepto, $valor, mb_substr((string) $this->input('nota', ''), 0, 255), Auth::id());
        Auditoria::log('gasto_registrado', $cob['nombre'] . ' · ' . Caja::conceptos()[$concepto] . ' · ' . money($valor) . ' · ' . $fecha, 'gasto', $id);
        flash('success', 'Gasto de ' . money($valor) . ' registrado.');
        $this->redirect($volver);
    }

    public function anularGasto(string $id): void
    {
        $this->requirePost();
        $g = $this->model->gasto((int) $id);
        if (!$g || (!Auth::isAdmin() && ((int) $g['cobrador_id'] !== Auth::id() || $g['fecha'] !== date('Y-m-d')))) {
            flash('danger', 'No puedes anular ese gasto.');
            $this->redirect('caja');
        }
        if (!Auth::isAdmin() && $this->model->diaCerrado((int) $g['cobrador_id'], $g['fecha'])) {
            flash('warning', 'La caja de ese día ya fue cerrada.');
            $this->redirect('caja');
        }
        $this->model->anularGasto((int) $id);
        Auditoria::log('gasto_anulado', money($g['valor']) . ' del ' . $g['fecha'], 'gasto', (int) $id);
        flash('success', 'Gasto anulado.');
        $this->redirect('caja?fecha=' . $g['fecha']);
    }

    /** El administrador recibe el efectivo del cobrador y cierra su caja del día. */
    public function cerrar(): void
    {
        $this->requireRole('admin');
        $this->requirePost();
        $fecha = $this->fecha((string) $this->input('fecha', ''));
        $cobradorId = (int) $this->input('cobrador_id', 0);
        $entregado = parse_money($this->input('entregado', ''));
        if ($fecha > date('Y-m-d')) {
            flash('danger', 'No se puede cerrar un día futuro.');
            $this->redirect('caja');
        }
        if ($entregado === null) {
            flash('danger', 'Ingresa el valor entregado (sin decimales).');
            $this->redirect('caja?fecha=' . $fecha);
        }
        $cob = (new Usuario())->find($cobradorId);
        if (!$cob || $cob['rol'] !== 'cobrador') {
            flash('danger', 'Cobrador no válido.');
            $this->redirect('caja?fecha=' . $fecha);
        }
        $obs = mb_substr((string) $this->input('observaciones', ''), 0, 255);
        $r = $this->model->cerrar($cobradorId, $fecha, $entregado, $obs, Auth::id());
        $dif = $r['diferencia_nueva'];
        Auditoria::log('caja_cerrada', "{$cob['nombre']} · {$fecha} · a entregar " . money($r['a_entregar']) . ' · entregó ' . money($entregado)
            . ($dif ? ' · ' . ($dif < 0 ? 'faltante ' : 'sobrante ') . money(abs($dif)) : ''), 'usuario', $cobradorId);
        if (abs($dif) < 0.5) {
            flash('success', "Caja de {$cob['nombre']} cerrada sin diferencias.");
        } else {
            flash($dif < 0 ? 'warning' : 'info', "Caja de {$cob['nombre']} cerrada con " . ($dif < 0 ? 'FALTANTE' : 'sobrante') . ' de ' . money(abs($dif)) . '.');
        }
        $this->redirect('caja?fecha=' . $fecha);
    }

    private function fecha(string $f): string
    {
        $d = \DateTime::createFromFormat('Y-m-d', $f);
        return $d && $d->format('Y-m-d') === $f ? $f : date('Y-m-d');
    }
}
