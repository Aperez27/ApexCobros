<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Password;
use App\Core\Security;
use App\Models\Auditoria;
use App\Models\IntentoLogin;
use App\Models\Usuario;

class AuthController extends Controller
{
    private const MENSAJE_FALLO = 'Usuario o contraseña incorrectos.';

    public function login(): void
    {
        if (Auth::check() && Auth::validate() === null) {
            $this->redirect('dashboard');
        }
        if ($this->isPost()) {
            $this->requirePost();
            $login = mb_substr((string) $this->input('usuario', ''), 0, 120);
            $pass = $_POST['password'] ?? '';
            $pass = is_string($pass) ? mb_substr($pass, 0, Password::MAX) : '';
            $ip = Security::ip();
            $intentos = new IntentoLogin();
            $_SESSION['_old'] = ['usuario' => $login];

            if ($min = $intentos->bloqueo($login, $ip)) {
                Auditoria::log('login_bloqueado', "Usuario: {$login} · IP: {$ip}");
                flash('danger', "Demasiados intentos fallidos. Por seguridad el ingreso está bloqueado durante {$min} minuto(s).");
                $this->redirect('auth/login');
            }

            $model = new Usuario();
            $user = $login !== '' ? $model->findByLogin($login) : null;
            // Se verifica contra un hash ficticio si el usuario no existe para que el tiempo
            // de respuesta no revele qué usuarios existen.
            $hash = $user['password'] ?? Password::hash(bin2hex(random_bytes(16)));
            $valida = password_verify($pass, $hash) && $user !== null;

            if (!$valida || !$user['activo']) {
                $intentos->registrar($login, $ip, false);
                Auditoria::log('login_fallido', "Usuario: {$login}" . ($user && !$user['activo'] && $valida ? ' (inactivo)' : ''),
                    'usuario', $user['id'] ?? null, $user['id'] ?? null);
                // Mismo mensaje para usuario inexistente, clave errada o usuario inactivo
                flash('danger', self::MENSAJE_FALLO);
                $this->redirect('auth/login');
            }

            $intentos->registrar($login, $ip, true);
            if (Password::necesitaRehash($user['password'])) {
                // Migra hashes antiguos (bcrypt) a Argon2id de forma transparente
                $model->update((int) $user['id'], ['password' => Password::hash($pass)]);
            }
            unset($_SESSION['_old']);
            Auth::login($user);
            $model->touchLogin((int) $user['id']);
            Auditoria::log('login_ok', 'Ingreso al sistema', 'usuario', (int) $user['id']);

            if ($user['debe_cambiar_clave']) {
                flash('warning', 'Por seguridad debes cambiar tu contraseña antes de continuar.');
                $this->redirect('perfil');
            }
            flash('success', 'Bienvenido, ' . $user['nombre'] . '.');
            $this->redirect($user['rol'] === 'cobrador' ? 'ruta' : 'dashboard');
        }
        $this->view('auth/login', ['title' => 'Iniciar sesión'], 'auth');
    }

    public function logout(): void
    {
        $this->requirePost();
        if (Auth::check()) {
            Auditoria::log('logout', 'Cierre de sesión');
        }
        Auth::logout();
        Security::startSession();
        flash('success', 'Sesión cerrada correctamente.');
        $this->redirect('auth/login');
    }
}
