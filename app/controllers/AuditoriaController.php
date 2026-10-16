<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Auditoria;
use App\Models\Usuario;

class AuditoriaController extends Controller
{
    public function index(): void
    {
        $this->requireRole('admin');
        $f = [
            'desde'      => (string) $this->input('desde', date('Y-m-d', strtotime('-7 days'))),
            'hasta'      => (string) $this->input('hasta', date('Y-m-d')),
            'usuario_id' => (string) $this->input('usuario_id', ''),
            'accion'     => (string) $this->input('accion', ''),
            'q'          => (string) $this->input('q', ''),
        ];
        foreach (['desde', 'hasta'] as $k) {
            $d = \DateTime::createFromFormat('Y-m-d', $f[$k]);
            if (!$d || $d->format('Y-m-d') !== $f[$k]) {
                $f[$k] = date('Y-m-d');
            }
        }
        if ($f['accion'] !== '' && !array_key_exists($f['accion'], Auditoria::acciones())) {
            $f['accion'] = '';
        }
        $model = new Auditoria();
        $this->view('auditoria/index', [
            'title'    => 'Auditoría y seguridad',
            'eventos'  => $model->listar($f),
            'resumen'  => $model->resumen24h(),
            'usuarios' => (new Usuario())->listar(),
            'f'        => $f,
        ]);
    }
}
