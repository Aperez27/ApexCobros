<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Auditoria;
use App\Models\Configuracion;

class ConfiguracionController extends Controller
{
    private const CLAVES = ['empresa_nombre', 'empresa_nit', 'empresa_telefono', 'empresa_direccion',
        'interes_defecto', 'cuotas_defecto', 'frecuencia_defecto', 'recibo_mensaje'];

    public function index(): void
    {
        $this->requireRole('admin');
        $this->view('configuracion/index', [
            'title' => 'Configuración',
            'cfg'   => (new Configuracion())->todas(),
        ]);
    }

    public function guardar(): void
    {
        $this->requireRole('admin');
        $this->requirePost();
        $data = [];
        foreach (self::CLAVES as $k) {
            $data[$k] = mb_substr((string) $this->input($k, ''), 0, 255);
        }
        if ($data['empresa_nombre'] === '') {
            $data['empresa_nombre'] = 'APEX Cobros';
        }
        $data['interes_defecto'] = (string) max(0, min(500, (float) str_replace(',', '.', $data['interes_defecto'])));
        $data['cuotas_defecto'] = (string) max(1, min(365, (int) $data['cuotas_defecto']));
        if (!array_key_exists($data['frecuencia_defecto'], frecuencias())) {
            $data['frecuencia_defecto'] = 'diaria';
        }
        $antes = (new Configuracion())->todas();
        (new Configuracion())->guardar($data);
        $cambios = array_keys(array_filter($data, fn ($v, $k) => ($antes[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));
        Auditoria::log('config_editada', $cambios ? 'Campos: ' . implode(', ', $cambios) : 'Sin cambios');
        flash('success', 'Configuración guardada.');
        $this->redirect('configuracion');
    }
}
