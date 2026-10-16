<?php

use App\Core\Env;

return [
    'app' => [
        'name'     => Env::get('APP_NAME', 'APEX'),
        'url'      => rtrim(Env::get('APP_URL', 'http://localhost/ApexCobros'), '/'),
        'timezone' => Env::get('APP_TIMEZONE', 'America/Bogota'),
        'debug'    => Env::bool('APP_DEBUG', false),
    ],
    'db' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::get('DB_PORT', '3306'),
        'name' => Env::get('DB_NAME', 'apex_cobros_db'),
        'user' => Env::get('DB_USER', 'root'),
        'pass' => Env::get('DB_PASS', ''),
    ],
    'security' => [
        // Cierra la sesión tras N minutos sin actividad
        'session_idle_minutes'   => (int) Env::get('SESSION_IDLE_MINUTES', '30'),
        // Duración máxima de una sesión aunque haya actividad
        'session_absolute_hours' => (int) Env::get('SESSION_ABSOLUTE_HOURS', '12'),
        // Intentos fallidos permitidos antes de bloquear el ingreso
        'login_max_attempts'     => (int) Env::get('LOGIN_MAX_ATTEMPTS', '5'),
        'login_lock_minutes'     => (int) Env::get('LOGIN_LOCK_MINUTES', '15'),
        // IPs de proxies inversos de confianza (separadas por coma) para leer X-Forwarded-For
        'trusted_proxies'        => Env::get('TRUSTED_PROXIES', ''),
    ],
];
