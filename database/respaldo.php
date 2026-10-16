<?php
/**
 * APEX · Respaldo automático de la base de datos (para el Programador de tareas de Windows o cron).
 *
 *   C:\xampp\php\php.exe database\respaldo.php            Guarda un respaldo en storage\backups
 *   C:\xampp\php\php.exe database\respaldo.php --dias=30  Además borra respaldos automáticos de más de 30 días
 *
 * Ver respaldo_diario.bat para programarlo todos los días.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Solo por línea de comandos.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\Respaldo;

$dias = 30;
foreach ($argv as $a) {
    if (preg_match('/^--dias=(\d+)$/', $a, $m)) {
        $dias = max(1, (int) $m[1]);
    }
}

try {
    $ruta = Respaldo::guardar('auto');
    $kb = number_format(filesize($ruta) / 1024, 0, ',', '.');
    App\Models\Auditoria::log('respaldo', 'Respaldo automático: ' . basename($ruta) . " ({$kb} KB)", null, null, null);
    echo date('Y-m-d H:i:s') . " Respaldo guardado: " . realpath($ruta) . " ({$kb} KB)\n";

    // Rotación: solo se eliminan respaldos automáticos (auto_*), nunca los manuales ni los previos a una limpieza
    $borrados = 0;
    foreach (glob(BASE_PATH . '/storage/backups/auto_*.sql') ?: [] as $f) {
        if (filemtime($f) < time() - $dias * 86400) {
            unlink($f);
            $borrados++;
        }
    }
    if ($borrados) {
        echo "Se eliminaron {$borrados} respaldo(s) automáticos de más de {$dias} días.\n";
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, date('Y-m-d H:i:s') . ' ERROR al respaldar: ' . $e->getMessage() . "\n");
    error_log('Respaldo automático: ' . $e->getMessage());
    exit(1);
}
