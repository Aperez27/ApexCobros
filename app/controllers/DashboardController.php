<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Pago;
use App\Models\Usuario;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();
        $scope = Auth::scopeCobrador();
        $credito = new Credito();
        $pago = new Pago();
        $hoy = date('Y-m-d');
        $mesIni = date('Y-m-01');

        $this->view('dashboard/index', [
            'title'      => 'Panel principal',
            'resumen'    => $credito->resumen($scope),
            'clientes'   => (new Cliente())->totales($scope),
            'recaudoHoy' => $pago->totales(['desde' => $hoy, 'hasta' => $hoy], $scope),
            'recaudoMes' => $pago->totales(['desde' => $mesIni, 'hasta' => $hoy], $scope),
            'grafica'    => $pago->recaudoDiario(14, $scope),
            'cobradores' => (new Usuario())->cartera($scope),
            'mora'       => $credito->proximosAVencer($scope),
            'ultimos'    => array_slice($pago->listar([], $scope), 0, 8),
            'promesas'   => (new \App\Models\Gestion())->promesasDelDia($scope, $hoy),
            'enMano'     => (new \App\Models\Caja())->enMano($scope),
        ]);
    }
}
