<?php
/**
 * APEX · Deja el sistema limpio para empezar a operar en producción.
 *
 *   C:\xampp\php\php.exe database\limpiar_produccion.php              Simulación: muestra qué se borraría (no borra nada)
 *   C:\xampp\php\php.exe database\limpiar_produccion.php --confirmar  Ejecuta la limpieza
 *
 * Qué hace:
 *   1. Guarda un respaldo completo en storage/backups/ ANTES de borrar.
 *   2. Borra clientes, créditos, cuotas, pagos, operaciones sin conexión, gestiones, gastos, cierres, asignaciones,
 *      auditoría e intentos de ingreso. Los consecutivos vuelven a empezar (CR-00001, RC-000001).
 *   3. Borra todos los cobradores (usuarios no administradores) y usuarios de prueba.
 *   4. Conserva los administradores y la configuración de la empresa, y cierra todas las sesiones abiertas.
 *
 * Opción: --reiniciar-admin  además deja el usuario "admin" con la clave inicial Admin123*
 *                            y la obligación de cambiarla en el primer ingreso.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Solo por línea de comandos.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Password;
use App\Services\Respaldo;

$db = Database::connection();
$confirmar = in_array('--confirmar', $argv, true);
$reiniciarAdmin = in_array('--reiniciar-admin', $argv, true);

$tablas = ['operaciones_offline', 'pagos', 'cuotas', 'gestiones', 'gastos', 'cierres', 'creditos', 'asignaciones', 'clientes', 'auditoria', 'intentos_login'];
$n = fn (string $sql) => (int) $db->query($sql)->fetchColumn();

echo "APEX · Limpieza para producción\n\nSe borrará:\n";
foreach ($tablas as $t) {
    printf("  - %-15s %6d registros\n", $t, $n("SELECT COUNT(*) FROM `{$t}`"));
}
$cobs = $db->query("SELECT usuario, nombre FROM usuarios WHERE rol <> 'admin' OR usuario LIKE 'sectest%' OR usuario LIKE 'demo.%' ORDER BY nombre")->fetchAll();
printf("  - %-15s %6d (%s)\n", 'usuarios', count($cobs), implode(', ', array_column($cobs, 'usuario')) ?: 'ninguno');
$admins = $db->query("SELECT usuario FROM usuarios WHERE rol = 'admin' AND usuario NOT LIKE 'sectest%'")->fetchAll(PDO::FETCH_COLUMN);
echo "\nSe conserva: administradores (" . implode(', ', $admins) . ") y la configuración de la empresa.\n";
if ($reiniciarAdmin) {
    echo "El usuario admin quedará con la clave inicial Admin123* (cambio obligatorio al entrar).\n";
}

if (!$confirmar) {
    echo "\nSIMULACIÓN: no se borró nada. Para ejecutar: php database\\limpiar_produccion.php --confirmar\n";
    exit(0);
}

// 1. Respaldo previo
$ruta = Respaldo::guardar('antes_de_limpiar');
echo "\n1. Respaldo guardado: " . str_replace(BASE_PATH . DIRECTORY_SEPARATOR, '', str_replace('/', DIRECTORY_SEPARATOR, $ruta))
    . ' (' . number_format(filesize($ruta) / 1024, 0, ',', '.') . " KB)\n";

// 2 y 3. Limpieza (TRUNCATE reinicia los consecutivos)
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
try {
    foreach ($tablas as $t) {
        $db->exec("TRUNCATE TABLE `{$t}`");
    }
    $db->exec("DELETE FROM usuarios WHERE rol <> 'admin' OR usuario LIKE 'sectest%' OR usuario LIKE 'demo.%'");
    $max = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM usuarios')->fetchColumn();
    $db->exec('ALTER TABLE usuarios AUTO_INCREMENT = ' . ($max + 1));
} finally {
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}
echo "2. Información operativa borrada y consecutivos reiniciados.\n";

// 4. Administradores: cerrar sesiones abiertas (y opcionalmente restablecer la clave)
$db->exec("UPDATE usuarios SET sesion_version = sesion_version + 1, ultimo_acceso = NULL");
if ($reiniciarAdmin) {
    $db->prepare("UPDATE usuarios SET password = ?, debe_cambiar_clave = 1, clave_actualizada_at = NULL, activo = 1 WHERE usuario = 'admin'")
        ->execute([Password::hash('Admin123*')]);
}
echo "3. Sesiones abiertas cerradas" . ($reiniciarAdmin ? '; clave del admin restablecida' : '') . ".\n";

App\Models\Auditoria::log('config_editada', 'Sistema limpiado para producción. Respaldo previo: ' . basename($ruta), null, null,
    (int) $db->query("SELECT id FROM usuarios WHERE usuario = 'admin'")->fetchColumn() ?: null);

// Logs de errores de la etapa de pruebas
@unlink(BASE_PATH . '/storage/logs/php-error.log');

echo "\nListo. El sistema quedó limpio. Siguiente paso: php tests\\verificar_produccion.php\n";
