<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Password;
use App\Models\Auditoria;
use App\Models\Usuario;

class PerfilController extends Controller
{
    public function index(): void
    {
        $this->requireLogin();
        $u = (new Usuario())->find(Auth::id());
        $this->view('perfil/index', ['title' => 'Mi perfil', 'u' => $u]);
    }

    public function clave(): void
    {
        $this->requireLogin();
        $this->requirePost();
        $model = new Usuario();
        $u = $model->find(Auth::id());
        $str = fn (string $k) => is_string($_POST[$k] ?? null) ? $_POST[$k] : '';
        $actual = $str('actual');
        $nueva = $str('nueva');
        $conf = $str('confirmar');

        if (!password_verify($actual, $u['password'])) {
            Auditoria::log('clave_cambiada', 'Intento fallido: contraseña actual incorrecta', 'usuario', (int) $u['id']);
            flash('danger', 'La contraseña actual no es correcta.');
        } elseif ($nueva !== $conf) {
            flash('danger', 'La confirmación no coincide con la nueva contraseña.');
        } elseif (password_verify($nueva, $u['password'])) {
            flash('danger', 'La nueva contraseña debe ser diferente a la actual.');
        } elseif ($e = Password::validar($nueva, [$u['usuario'], $u['nombre'], $u['cedula'], $u['email']])) {
            flash('danger', Password::mensaje($e));
        } else {
            $model->update((int) $u['id'], [
                'password'             => Password::hash($nueva),
                'debe_cambiar_clave'   => 0,
                'clave_actualizada_at' => date('Y-m-d H:i:s'),
            ]);
            // Cierra las demás sesiones abiertas con la clave anterior y mantiene esta
            $model->invalidarSesiones((int) $u['id']);
            $_SESSION['_auth']['version'] = (int) $model->find((int) $u['id'])['sesion_version'];
            $_SESSION['user']['debe_cambiar_clave'] = 0;
            session_regenerate_id(true);
            Auditoria::log('clave_cambiada', 'Contraseña actualizada', 'usuario', (int) $u['id']);
            flash('success', 'Contraseña actualizada. Se cerraron tus otras sesiones abiertas.');
            $this->redirect(Auth::isAdmin() ? 'dashboard' : 'ruta');
        }
        $this->redirect('perfil');
    }
}
