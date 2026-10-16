<?php

namespace App\Services;

use App\Core\Database;

/**
 * Genera un respaldo .sql completo de la base de datos (estructura + datos), sin depender de mysqldump.
 */
class Respaldo
{
    /** Escribe el respaldo en un recurso abierto (archivo o php://output). */
    public static function volcar($out, string $nombre = 'respaldo.sql'): void
    {
        $db = Database::connection();
        $dbName = $db->query('SELECT DATABASE()')->fetchColumn();
        fwrite($out, "-- APEX · Respaldo de la base de datos `{$dbName}`\n-- Generado: " . date('Y-m-d H:i:s')
            . "\n-- Restaurar: phpMyAdmin → Importar, o  mysql -uroot {$dbName} < {$nombre}\n\n"
            . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\nSET time_zone = '+00:00';\n\n");
        $zona = $db->query('SELECT @@session.time_zone')->fetchColumn();
        $db->exec("SET time_zone = '+00:00'"); // fechas TIMESTAMP independientes de la zona horaria
        try {
            foreach ($db->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(\PDO::FETCH_NUM) as [$tabla]) {
                $create = $db->query("SHOW CREATE TABLE `{$tabla}`")->fetch(\PDO::FETCH_NUM)[1];
                fwrite($out, "-- ----------------------------\n-- Tabla `{$tabla}`\n-- ----------------------------\nDROP TABLE IF EXISTS `{$tabla}`;\n{$create};\n\n");
                $st = $db->query("SELECT * FROM `{$tabla}`", \PDO::FETCH_NUM);
                $lote = [];
                $cols = null;
                while ($row = $st->fetch(\PDO::FETCH_NUM)) {
                    if ($cols === null) {
                        $cols = [];
                        for ($i = 0; $i < $st->columnCount(); $i++) {
                            $cols[] = '`' . $st->getColumnMeta($i)['name'] . '`';
                        }
                    }
                    $lote[] = '(' . implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $db->quote((string) $v), $row)) . ')';
                    if (count($lote) >= 200) {
                        fwrite($out, "INSERT INTO `{$tabla}` (" . implode(',', $cols) . ") VALUES\n" . implode(",\n", $lote) . ";\n");
                        $lote = [];
                    }
                }
                if ($lote) {
                    fwrite($out, "INSERT INTO `{$tabla}` (" . implode(',', $cols) . ") VALUES\n" . implode(",\n", $lote) . ";\n");
                }
                fwrite($out, "\n");
            }
            fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n-- Fin del respaldo\n");
        } finally {
            $db->exec('SET time_zone = ' . $db->quote((string) $zona));
        }
    }

    /** Guarda un respaldo en storage/backups y devuelve la ruta. */
    public static function guardar(string $prefijo = 'apex_respaldo'): string
    {
        $dir = BASE_PATH . '/storage/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $ruta = $dir . '/' . $prefijo . '_' . date('Y-m-d_His') . '.sql';
        $fh = fopen($ruta, 'w');
        self::volcar($fh, basename($ruta));
        fclose($fh);
        return $ruta;
    }
}
