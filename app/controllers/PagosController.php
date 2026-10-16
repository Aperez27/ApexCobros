<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Auditoria;
use App\Models\Credito;
use App\Models\OperacionDuplicada;
use App\Models\OperacionOffline;
use App\Models\Pago;
use App\Models\Usuario;

class PagosController extends Controller
{
    private Pago $model;

    public function __construct()
    {
        $this->requireLogin();
        $this->model = new Pago();
    }

    public function index(): void
    {
        $f = [
            'desde'       => (string) $this->input('desde', date('Y-m-d')),
            'hasta'       => (string) $this->input('hasta', date('Y-m-d')),
            'cobrador_id' => (string) $this->input('cobrador_id', ''),
            'metodo'      => (string) $this->input('metodo', ''),
            'q'           => (string) $this->input('q', ''),
            'anulados'    => (string) $this->input('anulados', ''),
        ];
        $scope = Auth::scopeCobrador();
        $this->view('pagos/index', [
            'title'      => 'Pagos recibidos',
            'pagos'      => $this->model->listar($f, $scope),
            'totales'    => $this->model->totales($f, $scope),
            'cobradores' => (new Usuario())->cobradores(false),
            'f'          => $f,
        ]);
    }

    public function guardar(): void
    {
        $this->requirePost();
        $creditoId = (int) $this->input('credito_id', 0);
        $credito = (new Credito())->detalle($creditoId);
        $volver = match ((string) $this->input('volver', '')) {
            'ruta'    => 'ruta?fecha=' . date('Y-m-d') . (Auth::isAdmin() ? '&cobrador_id=' . (int) $this->input('ruta_cobrador', 0) : ''),
            'cliente' => 'clientes/ver/' . (int) ($credito['cliente_id'] ?? 0),
            default   => 'creditos/ver/' . $creditoId,
        };

        if (!$credito || (!Auth::isAdmin() && (int) $credito['cobrador_id'] !== Auth::id())) {
            flash('danger', 'Crédito no válido.');
            $this->redirect('creditos');
        }
        if ($credito['estado'] !== 'activo') {
            flash('warning', 'El crédito no está activo.');
            $this->redirect($volver);
        }
        $monto = parse_money($this->input('monto', 0));
        $metodo = (string) $this->input('metodo', 'efectivo');
        if ($monto === null || $monto <= 0) {
            flash('danger', 'Ingresa un valor válido mayor a cero, sin decimales (ejemplo: 25.000).');
            $this->redirect($volver);
        }
        if (!array_key_exists($metodo, metodos_pago())) {
            $metodo = 'efectivo';
        }

        // Quién recibió el dinero: el cobrador en sesión, o el que elija el admin
        $cobradorId = Auth::id();
        if (Auth::isAdmin()) {
            $sel = (int) $this->input('cobrador_id', 0);
            $cobradorId = $sel ?: ((int) $credito['cobrador_id'] ?: Auth::id());
        }

        // El admin puede registrar pagos de fechas anteriores; el cobrador siempre con fecha/hora actual
        $fecha = date('Y-m-d H:i:s');
        $f = (string) $this->input('fecha', '');
        $d = \DateTime::createFromFormat('Y-m-d', $f);
        if (Auth::isAdmin() && $d && $d->format('Y-m-d') === $f && $f < date('Y-m-d')) {
            $fecha = $f . ' 12:00:00';
        }

        // Identificador de la operación (lo genera la ruta): si el envío se repite no se duplica el pago
        $uuid = (string) $this->input('op_uuid', '');
        $op = OperacionOffline::uuidValido($uuid) ? ['uuid' => $uuid, 'usuario_id' => Auth::id()] : null;

        try {
            $id = $this->model->registrar($creditoId, $monto, $metodo, $cobradorId, $fecha,
                mb_substr((string) $this->input('nota', ''), 0, 255), $op);
        } catch (\DomainException $e) {
            flash('danger', $e->getMessage());
            $this->redirect($volver);
        } catch (OperacionDuplicada $e) {
            flash('info', 'Este pago ya había quedado registrado; no se guardó dos veces.');
            $this->redirect($volver);
        }
        Auditoria::log('pago_registrado', $credito['codigo'] . ' · ' . money($monto) . ' · ' . $metodo . ' · fecha ' . $fecha, 'pago', $id);
        $_SESSION['_ultimo_recibo'] = $id;
        flash('success', 'Pago de ' . money($monto) . ' registrado a ' . $credito['cliente'] . '.');
        $this->redirect($volver);
    }

    public function recibo(string $id): void
    {
        $p = $this->model->detalle((int) $id);
        if (!$p) {
            flash('danger', 'Recibo no encontrado.');
            $this->redirect('pagos');
        }
        $credito = (new Credito())->detalle((int) $p['credito_id']);
        if (!Auth::isAdmin() && (int) $credito['cobrador_id'] !== Auth::id() && (int) $p['cobrador_id'] !== Auth::id()) {
            flash('danger', 'No tienes acceso a este recibo.');
            $this->redirect('pagos');
        }
        $this->view('pagos/recibo', ['title' => 'Recibo ' . $p['recibo'], 'p' => $p, 'cr' => $credito], 'print');
    }

    public function anular(string $id): void
    {
        $this->requireRole('admin');
        $this->requirePost();
        $p = $this->model->find((int) $id);
        $motivo = trim((string) $this->input('motivo', ''));
        if (!$p) {
            flash('danger', 'Pago no encontrado.');
            $this->redirect('pagos');
        }
        if ($motivo === '') {
            flash('warning', 'Indica el motivo de la anulación.');
        } elseif ($p['anulado']) {
            flash('warning', 'El pago ya estaba anulado.');
        } elseif ($p['metodo'] === 'renovacion') {
            // Anularlo reviviría la deuda anterior además del crédito nuevo (doble cobro)
            flash('warning', 'Este movimiento es de una renovación. Para deshacerla, anula el crédito nuevo que la originó.');
        } else {
            $this->model->anular((int) $id, mb_substr($motivo, 0, 255), Auth::id());
            Auditoria::log('pago_anulado', $p['recibo'] . ' · ' . money($p['monto']) . ' · motivo: ' . $motivo, 'pago', (int) $id);
            flash('success', 'Pago ' . $p['recibo'] . ' anulado. El saldo del crédito fue recalculado.');
        }
        $this->back('creditos/ver/' . $p['credito_id']);
    }
}
