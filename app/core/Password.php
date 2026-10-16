<?php

namespace App\Core;

/**
 * Política de contraseñas seguras y hashing (Argon2id).
 */
class Password
{
    public const MIN = 10;
    public const MAX = 128;

    /** Claves muy usadas que se rechazan aunque cumplan la forma. */
    private const COMUNES = ['password', 'contraseña', 'contrasena', 'qwerty', 'admin', 'administrador', 'cobrador',
        'cobros', 'apex', 'colombia', 'bucaramanga', 'bogota', 'medellin', '123456', '12345678', '123456789',
        '1234567890', 'abc123', 'iloveyou', 'bienvenido', 'welcome', 'letmein', 'secreto', 'clave', 'usuario'];

    public static function requisitos(): array
    {
        return [
            'Mínimo ' . self::MIN . ' caracteres',
            'Una letra mayúscula y una minúscula',
            'Al menos un número',
            'Al menos un símbolo (por ejemplo # $ % & * ! ?)',
            'Sin tu usuario, nombre o cédula, ni palabras comunes',
        ];
    }

    /**
     * Devuelve la lista de errores (vacía si la clave es válida).
     * $contexto: usuario, nombre, cédula, correo… que no deben aparecer en la clave.
     */
    public static function validar(string $clave, array $contexto = []): array
    {
        $err = [];
        $len = mb_strlen($clave);
        if ($len < self::MIN) {
            $err[] = 'debe tener al menos ' . self::MIN . ' caracteres';
        }
        if ($len > self::MAX) {
            $err[] = 'no puede superar ' . self::MAX . ' caracteres';
        }
        if (!preg_match('/\p{Lu}/u', $clave) || !preg_match('/\p{Ll}/u', $clave)) {
            $err[] = 'debe combinar mayúsculas y minúsculas';
        }
        if (!preg_match('/\d/', $clave)) {
            $err[] = 'debe incluir al menos un número';
        }
        if (!preg_match('/[^\p{L}\d]/u', $clave)) {
            $err[] = 'debe incluir al menos un símbolo';
        }
        if (preg_match('/(.)\1{3,}/u', $clave)) {
            $err[] = 'no puede repetir el mismo carácter 4 o más veces seguidas';
        }

        $plano = self::normalizar($clave);
        foreach (self::COMUNES as $c) {
            $c = self::normalizar($c);
            if ($plano === $c || (strlen($c) >= 5 && str_contains($plano, $c) && strlen($plano) - strlen($c) < 6)) {
                $err[] = 'es demasiado común o fácil de adivinar';
                break;
            }
        }
        foreach ($contexto as $dato) {
            foreach (preg_split('/[\s@._-]+/', self::normalizar((string) $dato)) as $parte) {
                if (strlen($parte) >= 4 && str_contains($plano, $parte)) {
                    $err[] = 'no puede contener tu usuario, nombre, cédula o correo';
                    break 2;
                }
            }
        }
        return array_values(array_unique($err));
    }

    public static function mensaje(array $errores): string
    {
        return 'La contraseña ' . implode(', ', $errores) . '.';
    }

    public static function hash(string $clave): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($clave, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 1]);
        }
        return password_hash($clave, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function necesitaRehash(string $hash): bool
    {
        return defined('PASSWORD_ARGON2ID')
            ? password_needs_rehash($hash, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 1])
            : password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    private static function normalizar(string $s): string
    {
        $s = mb_strtolower($s);
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            '@' => 'a', '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '$' => 's', '7' => 't']);
        return $s;
    }
}
