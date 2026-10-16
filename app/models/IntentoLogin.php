<?php

namespace App\Models;

use App\Core\Config;
use App\Core\Model;

/**
 * Freno de fuerza bruta persistido en BD (no depende de la cookie del atacante).
 *  - Por usuario + IP:  N fallos en la ventana  -> bloqueo.
 *  - Por usuario (cualquier IP): 2N fallos      -> bloqueo (ataque distribuido).
 *  - Por IP (cualquier usuario): 4N fallos      -> bloqueo (barrido de usuarios).
 */
class IntentoLogin extends Model
{
    protected string $table = 'intentos_login';

    private function max(): int
    {
        return (int) Config::get('security.login_max_attempts', 5);
    }

    private function ventana(): int
    {
        return (int) Config::get('security.login_lock_minutes', 15);
    }

    /** Minutos que faltan para desbloquear (0 si no está bloqueado). */
    public function bloqueo(string $usuario, string $ip): int
    {
        $usuario = mb_strtolower($usuario);
        $v = $this->ventana();
        $desde = date('Y-m-d H:i:s', time() - $v * 60);
        $reglas = [
            ['usuario = ? AND ip = ?', [$usuario, $ip], $this->max()],
            ['usuario = ?', [$usuario], $this->max() * 2],
            ['ip = ?', [$ip], $this->max() * 4],
        ];
        foreach ($reglas as [$where, $p, $limite]) {
            $r = $this->fetchOne("SELECT COUNT(*) AS n, MAX(created_at) AS ultimo FROM intentos_login
                WHERE exito = 0 AND created_at >= ? AND {$where}", [$desde, ...$p]);
            if ((int) $r['n'] >= $limite) {
                return max(1, (int) ceil((strtotime($r['ultimo']) + $v * 60 - time()) / 60));
            }
        }
        return 0;
    }

    public function registrar(string $usuario, string $ip, bool $exito): void
    {
        $this->db->prepare('INSERT INTO intentos_login (usuario, ip, exito, created_at) VALUES (?, ?, ?, NOW())')
            ->execute([mb_substr(mb_strtolower($usuario), 0, 120), $ip, $exito ? 1 : 0]);
        if ($exito) {
            // Un ingreso correcto limpia los fallos de ese usuario desde esa IP
            $this->db->prepare('DELETE FROM intentos_login WHERE usuario = ? AND ip = ? AND exito = 0')
                ->execute([mb_strtolower($usuario), $ip]);
        }
        // Limpieza de registros viejos (1 % de las veces)
        if (random_int(1, 100) === 1) {
            $this->db->exec('DELETE FROM intentos_login WHERE created_at < NOW() - INTERVAL 30 DAY');
        }
    }
}
