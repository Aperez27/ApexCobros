<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

/**
 * Centro de ayuda: manuales en PDF según el rol del usuario.
 * La carpeta docs/ está bloqueada desde el navegador; los archivos se entregan solo con sesión iniciada.
 */
class AyudaController extends Controller
{
    /** clave => [archivo, título, descripción, ícono, roles] */
    private const MANUALES = [
        'administrador' => ['APEX_01_Manual_del_Administrador.pdf', 'Manual del administrador', 'Todos los módulos paso a paso: clientes, créditos, pagos, caja, cobradores, reportes, auditoría y respaldo.', 'person-gear', ['admin']],
        'cobrador'      => ['APEX_02_Manual_del_Cobrador.pdf', 'Manual del cobrador', 'Uso desde el celular: ruta del día, cobrar, cuando el cliente no paga, gastos y entrega del dinero.', 'phone', ['admin', 'cobrador']],
        'rapida'        => ['APEX_03_Guia_de_Inicio_Rapido.pdf', 'Guía de inicio rápido', 'Las tareas más comunes en pocas páginas, para tener a mano.', 'lightning-charge', ['admin', 'cobrador']],
        'procedimientos' => ['APEX_04_Procedimientos_Operativos.pdf', 'Procedimientos operativos', 'Rutinas diarias, semanales y mensuales, política de crédito y corrección de errores.', 'list-check', ['admin']],
        'instalacion'   => ['APEX_05_Manual_de_Instalacion_y_Produccion.pdf', 'Instalación y puesta en producción', 'Requisitos, instalación, configuración, respaldos automáticos y solución de problemas.', 'hdd-network', ['admin']],
        'tecnico'       => ['APEX_06_Manual_Tecnico.pdf', 'Manual técnico', 'Arquitectura, reglas de cálculo, seguridad técnica, base de datos y cómo extender el sistema.', 'code-slash', ['admin']],
        'seguridad'     => ['APEX_07_Manual_de_Seguridad.pdf', 'Manual de seguridad', 'Políticas de acceso y contraseñas, auditoría, respaldos, incidentes y datos personales.', 'shield-lock', ['admin']],
    ];

    public function __construct()
    {
        $this->requireLogin();
    }

    public function index(): void
    {
        $rol = Auth::user()['rol'];
        $lista = [];
        foreach (self::MANUALES as $clave => [$archivo, $titulo, $desc, $icono, $roles]) {
            $ruta = BASE_PATH . '/docs/' . $archivo;
            if (in_array($rol, $roles, true) && is_file($ruta)) {
                $lista[$clave] = compact('titulo', 'desc', 'icono') + ['kb' => (int) round(filesize($ruta) / 1024)];
            }
        }
        $this->view('ayuda/index', ['title' => 'Ayuda y manuales', 'manuales' => $lista]);
    }

    public function ver(string $clave): void
    {
        $m = self::MANUALES[$clave] ?? null;
        $ruta = $m ? BASE_PATH . '/docs/' . $m[0] : null;
        if (!$m || !in_array(Auth::user()['rol'], $m[4], true) || !is_file($ruta)) {
            flash('warning', 'Ese manual no está disponible.');
            $this->redirect('ayuda');
        }
        $modo = ($_GET['descargar'] ?? '') === '1' ? 'attachment' : 'inline';
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $modo . '; filename="' . $m[0] . '"');
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: private, max-age=3600');
        readfile($ruta);
        exit;
    }
}
