<?php

namespace App\Core;

use App\Models\Auditoria;

class Auth
{
    private static bool $validada = false;

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION = [
            '_csrf' => bin2hex(random_bytes(32)), // token nuevo tras autenticarse
            'user'  => [
                'id'      => (int) $user['id'],
                'nombre'  => $user['nombre'],
                'usuario' => $user['usuario'],
                'rol'     => $user['rol'],
                'debe_cambiar_clave' => (int) ($user['debe_cambiar_clave'] ?? 0),
            ],
            '_auth' => [
                'version' => (int) ($user['sesion_version'] ?? 1),
                'ua'      => hash('sha256', Security::userAgent()),
                'inicio'  => time(),
                'ultimo'  => time(),
            ],
        ];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    /**
     * Revalida la sesión contra la base de datos en cada petición:
     * usuario activo, rol vigente, versión de sesión (cambio de clave / desactivación),
     * mismo navegador, inactividad y duración máxima.
     * Devuelve el motivo si la sesión ya no es válida, o null si está bien.
     */
    public static function validate(): ?string
    {
        if (!self::check()) {
            return 'Inicia sesión para continuar.';
        }
        if (self::$validada) {
            return null;
        }
        $a = $_SESSION['_auth'] ?? null;
        $idle = (int) Config::get('security.session_idle_minutes', 30) * 60;
        $abs = (int) Config::get('security.session_absolute_hours', 12) * 3600;
        $motivo = null;

        if (!$a) {
            $motivo = 'Tu sesión no es válida. Inicia sesión de nuevo.';
        } elseif (time() - $a['ultimo'] > $idle) {
            $motivo = 'Tu sesión se cerró por inactividad.';
        } elseif (time() - $a['inicio'] > $abs) {
            $motivo = 'Tu sesión expiró. Inicia sesión de nuevo.';
        } elseif (!hash_equals($a['ua'], hash('sha256', Security::userAgent()))) {
            $motivo = 'Tu sesión no es válida. Inicia sesión de nuevo.';
        } else {
            $u = (new \App\Models\Usuario())->find((int) $_SESSION['user']['id']);
            if (!$u || !$u['activo']) {
                $motivo = 'Tu usuario fue desactivado.';
            } elseif ((int) $u['sesion_version'] !== (int) $a['version']) {
                $motivo = 'Tu sesión fue cerrada porque cambiaron tus credenciales.';
            } else {
                // Sincroniza rol y datos por si el administrador los cambió
                $_SESSION['user']['rol'] = $u['rol'];
                $_SESSION['user']['nombre'] = $u['nombre'];
                $_SESSION['user']['debe_cambiar_clave'] = (int) $u['debe_cambiar_clave'];
                $_SESSION['_auth']['ultimo'] = time();
            }
        }

        if ($motivo !== null) {
            $uid = (int) ($_SESSION['user']['id'] ?? 0);
            if ($uid) {
                Auditoria::log('sesion_expirada', $motivo, 'usuario', $uid, $uid);
            }
            self::logout();
            Security::startSession();
            return $motivo;
        }
        self::$validada = true;
        return null;
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    public static function hasRole(string ...$roles): bool
    {
        return self::check() && in_array($_SESSION['user']['rol'], $roles, true);
    }

    public static function isAdmin(): bool
    {
        return self::hasRole('admin');
    }

    public static function debeCambiarClave(): bool
    {
        return !empty($_SESSION['user']['debe_cambiar_clave']);
    }

    /** Si es cobrador devuelve su id para filtrar su cartera; si es admin, null (ve todo). */
    public static function scopeCobrador(): ?int
    {
        return self::isAdmin() ? null : self::id();
    }
}
