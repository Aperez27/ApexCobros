<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Auditoria;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Gestion;
use App\Models\Pago;
use App\Models\Usuario;

/**
 * Ruta de cobro del día: lo que cada cobrador debe recoger hoy (incluye atrasos).
 */
class RutaController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();
        [$fecha, $cobradorId] = $this->filtros();
        $items = (new Credito())->ruta($cobradorId, $fecha);
        $ultimo = $_SESSION['_ultimo_recibo'] ?? null;
        unset($_SESSION['_ultimo_recibo']);
        if ($ultimo) {
            header('X-Apex-Copia: no');   // el botón "Ver recibo" es de un solo uso
        }

        $this->view('ruta/index', [
            'title'       => 'Ruta de cobro',
            'items'       => $items,
            'tot'         => $this->totales($items),
            'fecha'       => $fecha,
            'cobradorId'  => $cobradorId,
            'cobradores'  => (new Usuario())->cobradores(),
            'meta'        => $cobradorId ? (float) ((new Usuario())->find($cobradorId)['meta_diaria'] ?? 0) : 0,
            'ultimoRecibo' => $ultimo,
            'promesas'    => (new Gestion())->promesasDelDia($cobradorId, $fecha),
            'gestiones'   => (new Gestion())->ultimas(array_column($items, 'id')),
            'enMano'      => $cobradorId ? (float) ((new Caja())->enMano($cobradorId)[$cobradorId]['en_mano'] ?? 0) : null,
        ]);
    }

    /** Hoja de ruta imprimible (o en PDF) para trabajar sin conexión. */
    public function hoja(): void
    {
        $this->requireLogin();
        [$fecha, $cobradorId] = $this->filtros();
        $items = (new Credito())->ruta($cobradorId, $fecha);

        $this->view('ruta/hoja', [
            'title'      => 'Hoja de ruta ' . $fecha,
            'items'      => $items,
            'tot'        => $this->totales($items),
            'fecha'      => $fecha,
            'cobradorId' => $cobradorId,
            'cobrador'   => $cobradorId ? ((new Usuario())->find($cobradorId)['nombre'] ?? '') : '',
            'promesas'   => (new Gestion())->promesasDelDia($cobradorId, $fecha),
            'volver'     => ['ruta?' . http_build_query(['fecha' => $fecha, 'cobrador_id' => Auth::isAdmin() ? $cobradorId : null]), 'Volver a la ruta'],
        ], 'documento');
    }

    /** Guarda el orden de visita que el cobrador definió arrastrando las tarjetas. */
    public function ordenar(): void
    {
        $this->requireLogin();
        $this->requirePost();
        $cobradorId = Auth::isAdmin() ? (int) $this->input('cobrador_id', 0) : (int) Auth::id();
        if ($cobradorId <= 0) {
            $this->json(['ok' => false, 'mensaje' => 'Selecciona un cobrador para organizar su ruta.'], 422);
        }
        $ids = array_filter(array_map('intval', explode(',', (string) $this->input('clientes', ''))));
        if (!$ids || count($ids) > 2000) {
            $this->json(['ok' => false, 'mensaje' => 'No se recibió el orden de la ruta.'], 422);
        }
        $n = (new Cliente())->ordenarRuta($cobradorId, $ids);
        Auditoria::log('ruta_ordenada', "Orden de ruta actualizado ({$n} clientes)", 'usuarios', $cobradorId);
        $this->json(['ok' => true, 'mensaje' => 'Orden de la ruta guardado.']);
    }

    /** Fecha y cobrador de la ruta: el cobrador solo ve la suya; el administrador elige. */
    private function filtros(): array
    {
        $fecha = (string) $this->input('fecha', date('Y-m-d'));
        $d = \DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$d || $d->format('Y-m-d') !== $fecha) {
            $fecha = date('Y-m-d');
        }
        $cobradorId = Auth::isAdmin() ? ((int) $this->input('cobrador_id', 0) ?: null) : Auth::id();
        return [$fecha, $cobradorId];
    }

    private function totales(array $items): array
    {
        $tot = ['clientes' => count($items), 'a_cobrar' => 0, 'atrasado' => 0, 'cobrado' => 0, 'visitados' => 0];
        foreach ($items as $i) {
            $tot['a_cobrar'] += (float) $i['a_cobrar'];
            $tot['atrasado'] += (float) $i['atrasado'];
            $tot['cobrado'] += (float) $i['pagado_hoy'];
            $tot['visitados'] += $i['pagado_hoy'] > 0 ? 1 : 0;
        }
        return $tot;
    }
}
