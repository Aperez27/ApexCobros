<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Pago;
use App\Models\Usuario;

/**
 * Cartera por cobrador: cuántos clientes maneja, cuánto dinero tiene en la calle,
 * lo vencido y lo recaudado.
 */
class CobradoresController extends Controller
{
    public function index(): void
    {
        $this->requireRole('admin');
        $cartera = (new Usuario())->cartera();
        $tot = ['clientes' => 0, 'creditos_activos' => 0, 'capital' => 0, 'cartera' => 0, 'vencido' => 0,
            'recaudo_hoy' => 0, 'recaudo_mes' => 0, 'por_cobrar_hoy' => 0];
        foreach ($cartera as $c) {
            foreach ($tot as $k => $_) {
                $tot[$k] += (float) $c[$k];
            }
        }
        $this->view('cobradores/index', [
            'title'       => 'Cobradores y cartera',
            'cartera'     => $cartera,
            'tot'         => $tot,
            'sinCobrador' => (int) ((new Cliente())->totales()['sin_cobrador'] ?? 0),
            'enMano'      => (new \App\Models\Caja())->enMano(),
        ]);
    }

    public function ver(string $id): void
    {
        $this->requireLogin();
        $id = (int) $id;
        if (!Auth::isAdmin() && $id !== Auth::id()) {
            $this->redirect('cobradores/ver/' . Auth::id());
        }
        $u = (new Usuario())->find($id);
        $resumen = (new Usuario())->cartera($id)[0] ?? null;
        if (!$u || $u['rol'] !== 'cobrador' || !$resumen) {
            flash('danger', 'Cobrador no encontrado.');
            $this->redirect('cobradores');
        }
        $pago = new Pago();
        $this->view('cobradores/ver', [
            'title'      => 'Cartera de ' . $u['nombre'],
            'u'          => $u,
            'r'          => $resumen,
            'clientes'   => (new Cliente())->listar([], $id),
            'mora'       => (new Credito())->proximosAVencer($id, 50),
            'grafica'    => $pago->recaudoDiario(14, $id),
            'enMano'     => (new \App\Models\Caja())->enMano($id)[$id] ?? ['en_mano' => 0, 'ultimo_cierre' => null, 'faltantes' => 0],
            'calificaciones' => (new Cliente())->calificaciones(array_column((new Cliente())->listar([], $id), 'id')),
            'cobradores' => array_filter((new Usuario())->cobradores(), fn ($c) => (int) $c['id'] !== $id),
        ]);
    }

    /** Traslada TODA la cartera de un cobrador a otro (vacaciones, retiro, etc.). */
    public function trasladar(string $id): void
    {
        $this->requireRole('admin');
        $this->requirePost();
        $origen = (int) $id;
        $destino = (int) $this->input('destino_id', 0);
        $u = new Usuario();
        $dest = $u->find($destino);
        if (!$dest || $dest['rol'] !== 'cobrador' || $destino === $origen) {
            flash('danger', 'Selecciona un cobrador de destino válido.');
            $this->redirect('cobradores/ver/' . $origen);
        }
        $ids = array_column((new Cliente())->listar([], $origen), 'id');
        $n = (new Cliente())->asignar($ids, $destino, Auth::id(),
            (string) $this->input('motivo', '') ?: 'Traslado de cartera completa');
        Auditoria::log('cliente_asignado', "Traslado de cartera: {$n} cliente(s) del cobrador #{$origen} a {$dest['nombre']}", 'usuario', $destino);
        flash('success', "Se trasladaron {$n} cliente(s) a {$dest['nombre']}.");
        $this->redirect('cobradores/ver/' . $destino);
    }
}
