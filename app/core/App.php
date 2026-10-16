<?php

namespace App\Core;

use ReflectionMethod;
use Throwable;

/**
 * Router simple: /controlador/accion/param1/param2
 *   clientes/ver/5  ->  ClientesController::ver('5')
 *   membresias/por-vencer -> MembresiasController::porVencer()
 */
class App
{
    public function run(string $url): void
    {
        $segments = array_values(array_filter(explode('/', trim($url, '/')), 'strlen'));

        $controllerName = $segments[0] ?? 'dashboard';
        $action         = $segments[1] ?? 'index';
        $params         = array_slice($segments, 2);

        $class  = 'App\\Controllers\\' . $this->studly($controllerName) . 'Controller';
        $method = lcfirst($this->studly($action));

        try {
            if (!preg_match('/^[a-z0-9-]+$/i', $controllerName . $action) || !class_exists($class)) {
                $this->notFound();
                return;
            }
            if (!method_exists($class, $method)) {
                $this->notFound();
                return;
            }
            $ref = new ReflectionMethod($class, $method);
            // Solo métodos públicos declarados en el controlador concreto
            if (!$ref->isPublic() || $ref->isStatic() || $ref->getDeclaringClass()->getName() !== $class
                || str_starts_with($method, "__")) {
                $this->notFound();
                return;
            }
            if (count($params) < $ref->getNumberOfRequiredParameters()) {
                $this->notFound();
                return;
            }
            $controller = new $class();
            $controller->$method(...array_slice($params, 0, $ref->getNumberOfParameters()));
        } catch (\PDOException $e) {
            error_log((string) $e);
            $this->error('Error de base de datos', Config::get('app.debug') ? $e->getMessage()
                : 'No fue posible conectar o consultar la base de datos.');
        } catch (Throwable $e) {
            error_log((string) $e);
            $this->error('Error inesperado', Config::get('app.debug') ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
                : 'Ocurrió un error inesperado.');
        }
    }

    private function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', strtolower($value))));
    }

    private function notFound(): void
    {
        http_response_code(404);
        $title = 'Página no encontrada';
        $message = 'La ruta solicitada no existe.';
        require APP_PATH . '/views/errors/error.php';
    }

    private function error(string $title, string $message): void
    {
        http_response_code(500);
        require APP_PATH . '/views/errors/error.php';
    }
}
