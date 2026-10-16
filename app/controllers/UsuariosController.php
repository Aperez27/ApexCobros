<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Password;
use App\Models\Auditoria;
use App\Models\Usuario;

class UsuariosController extends Controller
{
    private Usuario $model;

    public function __construct()
    {
        $this->requireRole('admin');
        $this->model = new Usuario();
    }

    public function index(): void
    {
        $rol = (string) $this->input('rol', '');
        $this->view('usuarios/index', [
            'title'    => 'Usuarios',
            'usuarios' => $this->model->listar(array_key_exists($rol, Usuario::roles()) ? $rol : null, (string) $this->input('q', '')),
            'rol'      => $rol,
        ]);
    }

    public function crear(): void
    {
        $this->view('usuarios/form', [
            'title' => 'Nuevo usuario',
            'u'     => ['rol' => $this->input('rol', 'cobrador'), 'activo' => 1, 'meta_diaria' => 0],
        ]);
    }

    public function guardar(): void
    {
        $this->requirePost();
        $d = $this->datos();
        $pass = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $err = $this->validar($d);
        if (!$err && ($e = Password::validar($pass, [$d['usuario'], $d['nombre'], $d['cedula'], $d['email']]))) {
            $err = Password::mensaje($e);
        }
        if ($err) {
            $this->fallo($err, 'usuarios/crear');
        }
        $d['password'] = Password::hash($pass);
        // Clave asignada por el administrador: el usuario debe cambiarla en su primer ingreso
        $d['debe_cambiar_clave'] = 1;
        $id = $this->model->create($d);
        Auditoria::log('usuario_creado', "{$d['usuario']} ({$d['rol']})", 'usuario', $id);
        flash('success', ($d['rol'] === 'cobrador' ? 'Cobrador' : 'Usuario') . ' creado. Usuario de acceso: ' . $d['usuario']);
        $this->redirect($d['rol'] === 'cobrador' ? 'cobradores/ver/' . $id : 'usuarios');
    }

    public function editar(string $id): void
    {
        $u = $this->cargar((int) $id);
        $this->view('usuarios/form', ['title' => 'Editar usuario', 'u' => $u]);
    }

    public function actualizar(string $id): void
    {
        $this->requirePost();
        $actual = $this->cargar((int) $id);
        $d = $this->datos();
        $err = $this->validar($d, (int) $id);
        if (!$err && (int) $id === Auth::id() && ($d['rol'] !== 'admin' || !$d['activo'])) {
            $err = 'No puedes quitarte el rol de administrador ni desactivarte a ti mismo.';
        }
        if (!$err && $actual['rol'] === 'cobrador' && $d['rol'] !== 'cobrador' && $actual['clientes'] > 0) {
            $err = 'El cobrador tiene clientes asignados. Traslada su cartera antes de cambiarle el rol.';
        }
        $pass = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (!$err && $pass !== '' && ($e = Password::validar($pass, [$d['usuario'], $d['nombre'], $d['cedula'], $d['email']]))) {
            $err = Password::mensaje($e);
        }
        if ($err) {
            $this->fallo($err, 'usuarios/editar/' . $id);
        }
        $cambios = [];
        if ($pass !== '') {
            $d['password'] = Password::hash($pass);
            if ((int) $id !== Auth::id()) {
                $d['debe_cambiar_clave'] = 1;
            }
            $cambios[] = 'contraseña restablecida';
        }
        if ($d['rol'] !== $actual['rol']) {
            $cambios[] = "rol {$actual['rol']} → {$d['rol']}";
        }
        if ((int) $d['activo'] !== (int) $actual['activo']) {
            $cambios[] = $d['activo'] ? 'activado' : 'desactivado';
        }
        $this->model->update((int) $id, $d);
        // Cambios sensibles cierran las sesiones abiertas de ese usuario
        if ($pass !== '' || !$d['activo'] || $d['rol'] !== $actual['rol']) {
            $this->model->invalidarSesiones((int) $id);
        }
        Auditoria::log('usuario_editado', $d['usuario'] . ($cambios ? ': ' . implode(', ', $cambios) : ''), 'usuario', (int) $id);
        flash('success', 'Usuario actualizado.');
        $this->redirect('usuarios');
    }

    public function estado(string $id): void
    {
        $this->requirePost();
        $u = $this->cargar((int) $id);
        if ((int) $id === Auth::id()) {
            flash('warning', 'No puedes desactivar tu propio usuario.');
        } else {
            $this->model->update((int) $id, ['activo' => $u['activo'] ? 0 : 1]);
            if ($u['activo']) {
                $this->model->invalidarSesiones((int) $id);
            }
            Auditoria::log('usuario_estado', $u['usuario'] . ($u['activo'] ? ' desactivado' : ' activado'), 'usuario', (int) $id);
            flash('success', $u['nombre'] . ($u['activo'] ? ' desactivado.' : ' activado.'));
        }
        $this->back('usuarios');
    }

    public function eliminar(string $id): void
    {
        $this->requirePost();
        $u = $this->cargar((int) $id);
        $tienePagos = (bool) (new \App\Models\Pago())->totales(['cobrador_id' => $id, 'anulados' => '1'])['cantidad'];
        if ((int) $id === Auth::id()) {
            flash('warning', 'No puedes eliminar tu propio usuario.');
        } elseif ($u['clientes'] > 0 || $tienePagos) {
            flash('warning', 'El usuario tiene clientes o pagos asociados; desactívalo en lugar de eliminarlo.');
        } else {
            $this->model->delete((int) $id);
            Auditoria::log('usuario_eliminado', $u['usuario'], 'usuario', (int) $id);
            flash('success', 'Usuario eliminado.');
        }
        $this->redirect('usuarios');
    }

    // ------------------------------------------------------------------

    private function cargar(int $id): array
    {
        $u = $this->model->find($id);
        if (!$u) {
            flash('danger', 'Usuario no encontrado.');
            $this->redirect('usuarios');
        }
        $u['clientes'] = $this->model->contarClientes($id);
        return $u;
    }

    private function datos(): array
    {
        $t = fn (string $k, int $max) => mb_substr((string) $this->input($k, ''), 0, $max);
        $rol = $t('rol', 20);
        return [
            'nombre'      => mb_convert_case($t('nombre', 120), MB_CASE_TITLE),
            'cedula'      => ($c = preg_replace('/\D/', '', $t('cedula', 20))) === '' ? null : $c,
            'telefono'    => ($c = preg_replace('/[^0-9+]/', '', $t('telefono', 20))) === '' ? null : $c,
            'email'       => ($c = mb_strtolower($t('email', 120))) === '' ? null : $c,
            'usuario'     => mb_strtolower(preg_replace('/[^A-Za-z0-9._-]/', '', $t('usuario', 50))),
            'rol'         => array_key_exists($rol, Usuario::roles()) ? $rol : 'cobrador',
            'zona'        => ($c = $t('zona', 100)) === '' ? null : $c,
            'meta_diaria' => parse_money($this->input('meta_diaria', 0)) ?? 0.0,
            'activo'      => $this->input('activo') ? 1 : 0,
        ];
    }

    private function validar(array $d, ?int $id = null): ?string
    {
        if (mb_strlen($d['nombre']) < 3) {
            return 'El nombre es obligatorio.';
        }
        if (strlen($d['usuario']) < 3) {
            return 'El usuario de acceso debe tener al menos 3 caracteres (letras, números, punto o guion).';
        }
        if ($this->model->existe('usuario', $d['usuario'], $id)) {
            return 'El usuario "' . $d['usuario'] . '" ya existe.';
        }
        if ($d['cedula'] && $this->model->existe('cedula', $d['cedula'], $id)) {
            return 'Ya hay un usuario con esa cédula.';
        }
        if ($d['email'] && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            return 'Correo electrónico no válido.';
        }
        if ($d['email'] && $this->model->existe('email', $d['email'], $id)) {
            return 'Ya hay un usuario con ese correo.';
        }
        if ($d['rol'] === 'cobrador' && (!$d['cedula'] || !$d['telefono'])) {
            return 'Para un cobrador la cédula y el teléfono son obligatorios.';
        }
        return null;
    }

    private function fallo(string $msg, string $ruta): never
    {
        unset($_POST['password']);
        $this->withOld();
        flash('danger', $msg);
        $this->redirect($ruta);
    }
}
