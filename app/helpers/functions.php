<?php

use App\Core\Config;

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return Config::get('app.url') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = BASE_PATH . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : '1';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/** Formato de pesos colombianos: $ 80.000 */
function money(mixed $value): string
{
    return '$ ' . number_format((float) $value, 0, ',', '.');
}

/**
 * Convierte "85.000" / "$ 85000" / "85,000" en 85000.0 (pesos sin decimales).
 * Devuelve null si el texto no es un valor válido (negativos, decimales, letras…),
 * en lugar de "adivinar" quitando caracteres.
 */
function parse_money(mixed $value): ?float
{
    if (is_int($value) || is_float($value)) {
        return $value >= 0 && $value == floor((float) $value) ? (float) $value : null;
    }
    $s = str_replace(['$', ' ', "\u{A0}"], '', trim((string) $value));
    if ($s === '') {
        return 0.0;
    }
    if (!preg_match('/^(\d{1,3}(\.\d{3})+|\d{1,3}(,\d{3})+|\d+)$/', $s)) {
        return null;
    }
    $n = (float) preg_replace('/\D/', '', $s);
    return $n <= 1e12 ? $n : null;
}

function fecha(?string $date, bool $withTime = false): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $withTime ? date('d/m/Y h:i a', $ts) : date('d/m/Y', $ts);
}

function fecha_larga(string $date): string
{
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
        'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $ts = strtotime($date);
    return date('j', $ts) . ' de ' . $meses[(int) date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}

function dias_entre(string $desde, string $hasta): int
{
    return (int) (new DateTime($desde))->diff(new DateTime($hasta))->format('%r%a');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/** Valor anterior del formulario (tras un error de validación) o el valor por defecto. */
function old(string $key, mixed $default = ''): mixed
{
    static $old = null;
    if ($old === null) {
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);
    }
    return $old[$key] ?? $default;
}

/** Configuración editable guardada en BD (tabla configuracion). */
function setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = (new App\Models\Configuracion())->todas();
        } catch (Throwable) {
            $cache = [];
        }
    }
    return ($cache[$key] ?? '') !== '' ? $cache[$key] : $default;
}

function empresa(): string
{
    return setting('empresa_nombre', 'APEX Cobros');
}

function is_active(string $prefix): string
{
    $current = trim($_GET['url'] ?? 'dashboard', '/');
    if ($current === '') {
        $current = 'dashboard';
    }
    return str_starts_with($current, $prefix) ? 'active' : '';
}

function badge(string $estado): string
{
    $map = [
        'activo'    => 'success',
        'pagado'    => 'primary',
        'anulado'   => 'secondary',
        'inactivo'  => 'secondary',
        'bloqueado' => 'danger',
        'pendiente' => 'warning',
        'parcial'   => 'info',
        'pagada'    => 'success',
        'vencida'   => 'danger',
        'mora'      => 'danger',
        'al dia'    => 'success',
    ];
    $label = $estado === 'al dia' ? 'Al día' : ucfirst($estado);
    return '<span class="badge badge-soft-' . ($map[$estado] ?? 'secondary') . '">' . e($label) . '</span>';
}

function frecuencias(): array
{
    return [
        'diaria'    => 'Diaria',
        'semanal'   => 'Semanal',
        'quincenal' => 'Quincenal',
        'mensual'   => 'Mensual',
    ];
}

function metodos_pago(): array
{
    return [
        'efectivo'      => 'Efectivo',
        'nequi'         => 'Nequi',
        'daviplata'     => 'Daviplata',
        'transferencia' => 'Transferencia',
        'otro'          => 'Otro',
    ];
}

/** Iniciales para avatar: "Juan Pérez" -> "JP" */
function iniciales(string $nombre): string
{
    $parts = preg_split('/\s+/', trim($nombre));
    $ini = mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1);
    return mb_strtoupper($ini ?: '?');
}

/** Número para WhatsApp (Colombia por defecto). */
function wa_link(?string $telefono, string $mensaje = ''): string
{
    $num = preg_replace('/\D/', '', (string) $telefono);
    if (strlen($num) === 10) {
        $num = '57' . $num;
    }
    return 'https://wa.me/' . $num . ($mensaje !== '' ? '?text=' . rawurlencode($mensaje) : '');
}

function porcentaje(float $parte, float $total): float
{
    return $total > 0 ? round($parte * 100 / $total, 1) : 0.0;
}

/** "30 cuotas diarias", "4 cuotas semanales"... */
function frecuencia_plural(string $f): string
{
    return ['diaria' => 'diarias', 'semanal' => 'semanales', 'quincenal' => 'quincenales', 'mensual' => 'mensuales'][$f] ?? $f;
}

/** Nonce de la Content-Security-Policy para los <script> inline propios. */
function csp_nonce(): string
{
    return App\Core\Security::nonce();
}

/** JSON seguro para incrustar dentro de <script>. */
function js_json(mixed $data): string
{
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

/** Etiqueta de un método de pago, incluida la renovación (que no se ofrece en formularios). */
function metodo_label(string $m): string
{
    return metodos_pago()[$m] ?? ($m === 'renovacion' ? 'Renovación' : ucfirst($m));
}

/** Insignia de calificación del cliente (A–D, N). */
function calificacion_badge(?array $cal, bool $conTexto = false): string
{
    $letra = $cal['letra'] ?? 'N';
    [$txt, $tono, $desc] = App\Models\Cliente::calificacionInfo($letra);
    $tip = $desc . ($cal && $cal['evaluadas'] ? ' · Puntualidad ' . round($cal['puntualidad'] * 100) . ' % en ' . $cal['evaluadas'] . ' cuotas' : '');
    return '<span class="badge badge-soft-' . $tono . ' calif" title="' . e($tip) . '">' . $letra . ($conTexto ? ' · ' . e($txt) : '') . '</span>';
}
