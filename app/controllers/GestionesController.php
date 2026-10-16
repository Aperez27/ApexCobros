<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Auditoria;
use App\Models\Credito;
use App\Models\Gestion;
use App\Models\OperacionDuplicada;
use App\Models\OperacionOffline;

/**
 * Registro de gestiones de cobro (visitas/llamadas sin pago y promesas de pago).
 */
class GestionesController extends Controller
{
    public function guardar(): void
    {
        $this->requireLogin();
        $this->requirePost();
        $creditoId = (int) $this->input('credito_id', 0);
        $cr = (new Credito())->detalle($creditoId);
        $volver = (string) $this->input('volver', '') === 'ruta' ? 'ruta' : 'creditos/ver/' . $creditoId;

        if (!$cr || (!Auth::isAdmin() && (int) $cr['cobrador_id'] !== Auth::id())) {
            flash('danger', 'Crédito no válido.');
            $this->redirect('creditos');
        }
        if ($cr['estado'] !== 'activo') {
            flash('warning', 'Solo se registran gestiones en créditos activos.');
            $this->redirect($volver);
        }
        $tipo = (string) $this->input('tipo', 'visita');
        $resultado = (string) $this->input('resultado', '');
        if (!array_key_exists($tipo, Gestion::tipos()) || !array_key_exists($resultado, Gestion::resultados())) {
            flash('danger', 'Selecciona el tipo y el resultado de la gestión.');
            $this->redirect($volver);
        }
        $fechaPromesa = null;
        $montoPromesa = null;
        if ($resultado === 'promesa') {
            $fechaPromesa = (string) $this->input('fecha_promesa', '');
            $d = \DateTime::createFromFormat('Y-m-d', $fechaPromesa);
            if (!$d || $d->format('Y-m-d') !== $fechaPromesa || $fechaPromesa < date('Y-m-d') || $fechaPromesa > date('Y-m-d', strtotime('+30 days'))) {
                flash('danger', 'La fecha de la promesa debe estar entre hoy y los próximos 30 días.');
                $this->redirect($volver);
            }
            $montoPromesa = parse_money($this->input('monto_promesa', ''));
            if ($montoPromesa === null || $montoPromesa < 0 || $montoPromesa > (float) $cr['saldo']) {
                flash('danger', 'El valor prometido no es válido o supera el saldo.');
                $this->redirect($volver);
            }
        }
        $nota = mb_substr((string) $this->input('nota', ''), 0, 255);
        $uuid = (string) $this->input('op_uuid', '');
        $op = OperacionOffline::uuidValido($uuid) ? ['uuid' => $uuid, 'usuario_id' => Auth::id()] : null;
        try {
            $id = (new Gestion())->registrar($creditoId, Auth::id(), $tipo, $resultado, $fechaPromesa, $montoPromesa ?: null, $nota, $op);
        } catch (OperacionDuplicada $e) {
            flash('info', 'Esta gestión ya había quedado registrada; no se guardó dos veces.');
            $this->redirect($volver);
        }
        Auditoria::log('gestion_registrada', $cr['codigo'] . ' · ' . Gestion::resultados()[$resultado]
            . ($fechaPromesa ? ' para el ' . $fechaPromesa . ($montoPromesa ? ' por ' . money($montoPromesa) : '') : ''), 'gestion', $id);
        flash('success', 'Gestión registrada para ' . $cr['cliente'] . '.');
        $this->redirect($volver);
    }
}
