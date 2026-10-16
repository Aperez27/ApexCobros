<?php

namespace App\Core;

/**
 * Cabeceras HTTP de seguridad, nonce para CSP, sesión endurecida e IP del cliente.
 */
class Security
{
    private static ?string $nonce = null;

    /** Nonce aleatorio por petición para permitir solo NUESTROS scripts inline. */
    public static function nonce(): string
    {
        return self::$nonce ??= base64_encode(random_bytes(18));
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    }

    public static function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header_remove('X-Powered-By');
        $n = self::nonce();
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$n}' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com",
            "img-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
        header('Content-Security-Policy: ' . $csp);
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function startSession(): void
    {
        ini_set('session.use_strict_mode', '1');   // rechaza IDs de sesión inventados
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) (Config::get('security.session_absolute_hours', 12) * 3600));
        session_name('APEXCOBROSSESSID');
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure'   => self::isHttps(),
            'cookie_path'     => parse_url(Config::get('app.url'), PHP_URL_PATH) ?: '/',
        ]);
    }

    /** IP del cliente. Solo se confía en X-Forwarded-For si se configura un proxy de confianza. */
    public static function ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $proxies = array_filter(array_map('trim', explode(',', (string) Config::get('security.trusted_proxies', ''))));
        if ($proxies && in_array($ip, $proxies, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $fwd = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($fwd, FILTER_VALIDATE_IP)) {
                $ip = $fwd;
            }
        }
        return substr($ip, 0, 45);
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    /** ¿La URL pertenece a esta aplicación? (evita redirecciones abiertas) */
    public static function isInternalUrl(string $url): bool
    {
        $app = parse_url(Config::get('app.url'));
        $u = parse_url($url);
        if (!$u || !isset($u['host'], $app['host'])) {
            return false;
        }
        $base = rtrim($app['path'] ?? '', '/');
        $path = $u['path'] ?? '/';
        return strcasecmp($u['host'], $app['host']) === 0
            && ($u['scheme'] ?? '') === ($app['scheme'] ?? 'http')
            && ($u['port'] ?? null) === ($app['port'] ?? null)
            && ($path === $base || str_starts_with($path, $base . '/'));
    }
}
