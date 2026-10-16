<?php

namespace App\Core;

use App\Models\Auditoria;

/**
 * Controlador base.
 */
abstract class Controller
{
    /** Rutas permitidas mientras el usuario tiene pendiente el cambio de contraseña. */
    private const RUTAS_CAMBIO_CLAVE = ['perfil', 'perfil/index', 'perfil/clave', 'auth/logout'];

    /** Renderiza una vista dentro del layout. */
    protected function view(string $view, array $data = [], ?string $layout = 'main'): void
    {
        $viewFile = APP_PATH . '/views/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException("Vista no encontrada: {$view}");
        }
        // Una página con avisos de un solo uso no se guarda tal cual como copia sin conexión (sw.js pide una limpia)
        if (!empty($_SESSION['_flash']) && !headers_sent()) {
            header('X-Apex-Copia: no');
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }
        require APP_PATH . '/views/layouts/' . $layout . '.php';
    }

    protected function redirect(string $path): never
    {
        $this->irA(url($path));
    }

    /** Vuelve a la página anterior solo si pertenece a esta aplicación (sin redirección abierta). */
    protected function back(string $fallback = ''): never
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref !== '' && Security::isInternalUrl($ref) && !preg_match('/[\r\n]/', $ref)) {
            $this->irA($ref);
        }
        $this->redirect($fallback);
    }

    /**
     * Petición hecha por el JavaScript de la ruta (trabajo sin conexión): en vez de redirigir responde
     * JSON con el destino, así el celular distingue "sesión vencida" de "guardado" y no pierde el dato.
     */
    protected function esFetch(): bool
    {
        return ($_SERVER['HTTP_X_APEX_FETCH'] ?? '') === '1';
    }

    private function irA(string $destino): never
    {
        if ($this->esFetch()) {
            $codigo = http_response_code();
            // 419 (token del formulario vencido) no es estándar y Apache lo convierte en 500: se envía 403 con una marca
            if ($codigo === 419) {
                $this->json(['redirect' => $destino, 'token_vencido' => true], 403);
            }
            $this->json(['redirect' => $destino], is_int($codigo) && $codigo >= 400 ? $codigo : 200);
        }
        header('Location: ' . $destino);
        exit;
    }

    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        exit;
    }

    protected function requireLogin(): void
    {
        if ($motivo = Auth::validate()) {
            flash('warning', $motivo);
            $this->redirect('auth/login');
        }
        $ruta = strtolower(trim((string) ($_GET['url'] ?? ''), '/'));
        if (Auth::debeCambiarClave() && !in_array($ruta, self::RUTAS_CAMBIO_CLAVE, true)) {
            flash('warning', 'Por seguridad debes cambiar tu contraseña antes de continuar.');
            $this->redirect('perfil');
        }
    }

    protected function requireRole(string ...$roles): void
    {
        $this->requireLogin();
        if (!Auth::hasRole(...$roles)) {
            Auditoria::log('acceso_denegado', 'Ruta: /' . mb_substr((string) ($_GET['url'] ?? ''), 0, 200));
            flash('danger', 'No tienes permisos para acceder a esa sección.');
            $this->redirect('dashboard');
        }
    }

    protected function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /** Exige POST con token CSRF válido. */
    protected function requirePost(): void
    {
        if (!$this->isPost()) {
            http_response_code(405);
            header('Allow: POST');
            exit('Método no permitido');
        }
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($token) || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $token)) {
            http_response_code(419);
            flash('danger', 'La sesión del formulario expiró. Intenta de nuevo.');
            $this->back();
        }
    }

    /** Lee un valor escalar de POST/GET. Los arreglos inesperados se ignoran (devuelven el valor por defecto). */
    protected function input(string $key, mixed $default = null): mixed
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? $default;
        if (is_array($v)) {
            return $default;
        }
        return is_string($v) ? trim($v) : $v;
    }

    /** Guarda los datos del POST para repoblar formularios tras error (nunca contraseñas ni token). */
    protected function withOld(): void
    {
        $old = $_POST;
        unset($old['password'], $old['_csrf'], $old['actual'], $old['nueva'], $old['confirmar']);
        $_SESSION['_old'] = $old;
    }
}
