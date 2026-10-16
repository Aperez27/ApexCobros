<?php
/**
 * APEX · Restablece la contraseña de un usuario desde la consola del servidor.
 * Úsalo cuando el administrador olvidó su contraseña y no hay otro administrador que pueda cambiarla.
 *
 *   C:\xampp\php\php.exe database\restablecer_clave.php admin
 *
 * Genera una contraseña temporal segura, obliga a cambiarla en el siguiente ingreso,
 * cierra las sesiones abiertas de ese usuario, lo reactiva y desbloquea sus intentos de ingreso.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Solo por línea de comandos.');
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Password;

$usuario = mb_strtolower(trim($argv[1] ?? ''));
if ($usuario === '') {
    exit("Uso: php database\\restablecer_clave.php <usuario>\n");
}
$db = Database::connection();
$u = $db->prepare('SELECT id, nombre, rol FROM usuarios WHERE usuario = ?');
$u->execute([$usuario]);
$u = $u->fetch();
if (!$u) {
    exit("No existe el usuario \"{$usuario}\".\n");
}

// Contraseña temporal que cumple la política: 4 bloques aleatorios + símbolo + número
$alfa = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
do {
    $temp = '';
    for ($i = 0; $i < 8; $i++) {
        $temp .= $alfa[random_int(0, strlen($alfa) - 1)];
    }
    $temp .= '#' . random_int(10, 99) . '!';
} while (Password::validar($temp, [$usuario, $u['nombre']]));

$db->prepare('UPDATE usuarios SET password = ?, debe_cambiar_clave = 1, activo = 1, sesion_version = sesion_version + 1 WHERE id = ?')
    ->execute([Password::hash($temp), $u['id']]);
$db->prepare('DELETE FROM intentos_login WHERE usuario = ?')->execute([$usuario]);
App\Models\Auditoria::log('usuario_editado', "{$usuario}: contraseña restablecida desde la consola del servidor", 'usuario', (int) $u['id'], (int) $u['id']);

echo "Contraseña temporal de {$u['nombre']} ({$usuario}): {$temp}\n";
echo "Deberá cambiarla al ingresar. Las sesiones abiertas de este usuario se cerraron.\n";
