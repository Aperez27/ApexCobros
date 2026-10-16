<?php

namespace App\Core;

class Config
{
    private static array $config = [];

    public static function set(array $config): void
    {
        self::$config = $config;
    }

    /** Acceso con notación de punto: Config::get('db.host') */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}
