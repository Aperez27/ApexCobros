<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Usuario;

class ClientesController extends Controller
{
    private Cliente $model;

    public function __construct()
    {
        $this->requireLogin();
        $this->model = new Cliente();
    }

    public function index(): void
    {
        $f = [
            'q'           => (string) $this->input('q', ''),
            'cobrador_id' => (string) $this->input('cobrador_id', ''),
            'estado'      => (string) $this->input('estado', ''),
            'situacion'   => (string) $this->input('situacion', ''),
        ];
        $clientes = $this->model->listar($f, Auth::scopeCobrador());
        $cal = $this->model->calificaciones(array_column($clientes, 'id'));
        if (!empty($_GET['calif']) && is_string($_GET['calif'])) {
            $letra = strtoupper(substr((string) $_GET['calif'], 0, 1));
            $clientes = array_values(array_filter($clientes, fn ($c) => ($cal[$c['id']]['letra'] ?? 'N') === $letra));
        }
        $this->view('clientes/index', [
            'title'      => 'Clientes',
            'calificaciones' => $cal,
            'clientes'   => $clientes,
            'cobradores' => (new Usuario())->cobradores(false),
            'f'          => $f,
        ]);
    }

    public function crear(): void
    {
        $this->view('clientes/form', [
            'title'      => 'Nuevo cliente',
            'c'          => ['cobrador_id' => $this->input('cobrador', ''), 'estado' => 'activo', 'ciudad' => ''],
            'cobradores' => (new Usuario())->cobradores(),
        ]);
    }

    public function guardar(): void
    {
        $this->requirePost();
        $data = $this->datos();
        if ($err = $this->validar($data)) {
            $this->fallo($err, 'clientes/crear');
        }
        $data['created_by'] = Auth::id();
        if (!Auth::isAdmin()) {
            // El cobrador que registra el cliente queda como responsable
            $data['cobrador_id'] = Auth::id();
            $data['estado'] = 'activo';
        }
        $id = $this->model->create($data);
        $this->model->registrarAsignacionInicial($id, $data['cobrador_id'], Auth::id());
        Auditoria::log('cliente_creado', $data['cedula'] . ' ' . $data['nombres'] . ' ' . $data['apellidos'], 'cliente', $id);
        flash('success', 'Cliente registrado correctamente.');
        $this->redirect('clientes/ver/' . $id);
    }

    public function editar(string $id): void
    {
        $c = $this->cargar((int) $id);
        $this->view('clientes/form', [
            'title'      => 'Editar cliente',
            'c'          => $c,
            'cobradores' => (new Usuario())->cobradores(),
        ]);
    }

    public function actualizar(string $id): void
    {
        $this->requirePost();
        $actual = $this->cargar((int) $id);
        $data = $this->datos();
        if ($err = $this->validar($data, (int) $id)) {
            $this->fallo($err, 'clientes/editar/' . $id);
        }
        $nuevoCobrador = $data['cobrador_id'];
        unset($data['cobrador_id']);
        if (!Auth::isAdmin()) {
            unset($data['estado']);
        }
        $this->model->update((int) $id, $data);

        $anterior = $actual['cobrador_id'] !== null ? (int) $actual['cobrador_id'] : null;
        if (Auth::isAdmin() && $nuevoCobrador !== $anterior) {
            $this->model->asignar([(int) $id], $nuevoCobrador, Auth::id(), 'Cambio desde edición del cliente');
        }
        Auditoria::log('cliente_editado', $data['cedula'] . ' ' . $data['nombres'] . ' ' . $data['apellidos'], 'cliente', (int) $id);
        flash('success', 'Datos del cliente actualizados.');
        $this->redirect('clientes/ver/' . $id);
    }

    public function ver(string $id): void
    {
        $c = $this->cargar((int) $id);
        $creditos = (new Credito())->porCliente((int) $id);
        $this->view('clientes/ver', [
            'title'      => $c['nombres'] . ' ' . $c['apellidos'],
            'c'          => $c,
            'creditos'   => $creditos,
            'historial'  => $this->model->historialAsignaciones((int) $id),
            'calificacion' => $this->model->calificaciones([(int) $id])[(int) $id] ?? null,
            'cobradores' => (new Usuario())->cobradores(),
        ]);
    }

    public function eliminar(string $id): void
    {
        $this->requireRole('admin');
        $this->requirePost();
        $c = $this->cargar((int) $id);
        if ($this->model->tieneCreditos((int) $id)) {
            flash('warning', 'El cliente tiene créditos registrados; no se puede eliminar. Puedes marcarlo como inactivo.');
            $this->redirect('clientes/ver/' . $id);
        }
        $this->model->delete((int) $id);
        Auditoria::log('cliente_eliminado', $c['cedula'] . ' ' . $c['nombres'] . ' ' . $c['apellidos'], 'cliente', (int) $id);
        flash('success', 'Cliente ' . $c['nombres'] . ' eliminado.');
        $this->redirect('clientes');
    }

    /** Asignación masiva de clientes a un cobrador. */
    public function asignar(): void
    {
        $this->requireRole('admin');
        $this->requirePost();
        $ids = array_filter(array_map('intval', (array) ($_POST['clientes'] ?? [])));
        $cobrador = (int) $this->input('cobrador_id', 0);
        if (!$ids) {
            flash('warning', 'Selecciona al menos un cliente.');
            $this->back('clientes');
        }
        $destino = $cobrador ? (new Usuario())->find($cobrador) : null;
        if ($cobrador && (!$destino || $destino['rol'] !== 'cobrador')) {
            flash('danger', 'Cobrador no válido.');
            $this->back('clientes');
        }
        $n = $this->model->asignar($ids, $cobrador ?: null, Auth::id(), (string) $this->input('motivo', '') ?: 'Asignación masiva');
        Auditoria::log('cliente_asignado', $n . ' cliente(s) → ' . ($destino['nombre'] ?? 'sin cobrador') . ' · IDs: ' . implode(',', $ids));
        flash('success', $n . ' cliente(s) asignado(s) a ' . ($destino['nombre'] ?? 'nadie (sin cobrador)') . '.');
        $this->back('clientes');
    }

    // ------------------------------------------------------------------

    private function cargar(int $id): array
    {
        $c = $this->model->detalle($id);
        if (!$c) {
            flash('danger', 'Cliente no encontrado.');
            $this->redirect('clientes');
        }
        if (!Auth::isAdmin() && (int) $c['cobrador_id'] !== Auth::id()) {
            flash('danger', 'Este cliente no pertenece a tu cartera.');
            $this->redirect('clientes');
        }
        return $c;
    }

    private function datos(): array
    {
        $txt = fn (string $k, int $max = 180) => mb_substr((string) $this->input($k, ''), 0, $max);
        $nullable = fn (string $k, int $max = 180) => ($v = $txt($k, $max)) === '' ? null : $v;
        $cobrador = (int) $this->input('cobrador_id', 0);
        return [
            'cedula'                => preg_replace('/[^0-9A-Za-z]/', '', $txt('cedula', 20)),
            'nombres'               => mb_convert_case($txt('nombres', 80), MB_CASE_TITLE),
            'apellidos'             => mb_convert_case($txt('apellidos', 80), MB_CASE_TITLE),
            'email'                 => $nullable('email', 120) !== null ? mb_strtolower($txt('email', 120)) : null,
            'telefono1'             => preg_replace('/[^0-9+]/', '', $txt('telefono1', 20)),
            'telefono2'             => ($t = preg_replace('/[^0-9+]/', '', $txt('telefono2', 20))) === '' ? null : $t,
            'direccion'             => $txt('direccion'),
            'barrio'                => $nullable('barrio', 100),
            'ciudad'                => $nullable('ciudad', 80),
            'fecha_nacimiento'      => $nullable('fecha_nacimiento', 10),
            'ocupacion'             => $nullable('ocupacion', 100),
            'negocio_nombre'        => $nullable('negocio_nombre', 120),
            'negocio_direccion'     => $nullable('negocio_direccion'),
            'referencia_nombre'     => $nullable('referencia_nombre', 120),
            'referencia_telefono'   => ($t = preg_replace('/[^0-9+]/', '', $txt('referencia_telefono', 20))) === '' ? null : $t,
            'referencia_parentesco' => $nullable('referencia_parentesco', 50),
            'observaciones'         => $nullable('observaciones', 2000),
            'cobrador_id'           => $cobrador ?: null,
            'estado'                => array_key_exists($this->input('estado', ''), Cliente::estados()) ? $this->input('estado') : 'activo',
        ];
    }

    private function validar(array $d, ?int $id = null): ?string
    {
        if (strlen($d['cedula']) < 5) {
            return 'La cédula es obligatoria (mínimo 5 dígitos).';
        }
        if ($this->model->existeCedula($d['cedula'], $id)) {
            return 'Ya existe un cliente con la cédula ' . $d['cedula'] . '.';
        }
        if ($d['nombres'] === '' || $d['apellidos'] === '') {
            return 'Nombres y apellidos son obligatorios.';
        }
        if (strlen($d['telefono1']) < 7) {
            return 'El teléfono principal es obligatorio.';
        }
        if ($d['direccion'] === '') {
            return 'La dirección es obligatoria.';
        }
        if ($d['email'] !== null && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            return 'El correo electrónico no es válido.';
        }
        if ($d['fecha_nacimiento'] !== null && !strtotime($d['fecha_nacimiento'])) {
            return 'La fecha de nacimiento no es válida.';
        }
        if ($d['cobrador_id'] !== null && Auth::isAdmin()) {
            $u = (new Usuario())->find($d['cobrador_id']);
            if (!$u || $u['rol'] !== 'cobrador') {
                return 'El cobrador seleccionado no es válido.';
            }
        }
        return null;
    }

    private function fallo(string $msg, string $ruta): never
    {
        $this->withOld();
        flash('danger', $msg);
        $this->redirect($ruta);
    }
}
