<?php

namespace App\Models;

use App\Core\Model;

class Usuario extends Model
{
    protected string $table = 'usuarios';
    protected array $fillable = ['nombre', 'cedula', 'telefono', 'email', 'usuario', 'password',
        'rol', 'zona', 'meta_diaria', 'activo', 'debe_cambiar_clave', 'clave_actualizada_at'];

    public static function roles(): array
    {
        return ['admin' => 'Administrador', 'cobrador' => 'Cobrador'];
    }

    public function findByLogin(string $login): ?array
    {
        return $this->fetchOne('SELECT * FROM usuarios WHERE usuario = ? OR (email = ? AND email IS NOT NULL) LIMIT 1',
            [$login, $login]);
    }

    public function touchLogin(int $id): void
    {
        $this->db->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$id]);
    }

    public function existe(string $campo, string $valor, ?int $exceptId = null): bool
    {
        if (!in_array($campo, ['usuario', 'cedula', 'email'], true) || $valor === '') {
            return false;
        }
        return (bool) $this->fetchValue("SELECT COUNT(*) FROM usuarios WHERE {$campo} = ? AND id <> ?",
            [$valor, $exceptId ?? 0]);
    }

    public function listar(?string $rol = null, string $q = ''): array
    {
        $sql = 'SELECT u.*, (SELECT COUNT(*) FROM clientes c WHERE c.cobrador_id = u.id) AS clientes
                FROM usuarios u WHERE 1=1';
        $p = [];
        if ($rol) {
            $sql .= ' AND u.rol = ?';
            $p[] = $rol;
        }
        if ($q !== '') {
            $sql .= ' AND (u.nombre LIKE ? OR u.usuario LIKE ? OR u.cedula LIKE ? OR u.zona LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%", "%$q%");
        }
        return $this->fetchAll($sql . ' ORDER BY u.activo DESC, u.rol, u.nombre', $p);
    }

    /** Incrementa la versión de sesión: todas las sesiones abiertas del usuario quedan inválidas. */
    public function invalidarSesiones(int $id): void
    {
        $this->db->prepare('UPDATE usuarios SET sesion_version = sesion_version + 1 WHERE id = ?')->execute([$id]);
    }

    public function contarClientes(int $id): int
    {
        return (int) $this->fetchValue('SELECT COUNT(*) FROM clientes WHERE cobrador_id = ?', [$id]);
    }

    /** Cobradores para selects. */
    public function cobradores(bool $soloActivos = true): array
    {
        return $this->fetchAll("SELECT id, nombre, zona, activo FROM usuarios WHERE rol = 'cobrador'"
            . ($soloActivos ? ' AND activo = 1' : '') . ' ORDER BY nombre');
    }

    /**
     * Resumen de cartera por cobrador: clientes, créditos, capital, saldo por cobrar,
     * valor vencido y recaudo de hoy / del mes.
     */
    public function cartera(?int $cobradorId = null): array
    {
        $sql = "SELECT u.id, u.nombre, u.cedula, u.telefono, u.zona, u.activo, u.meta_diaria, u.ultimo_acceso,
                (SELECT COUNT(*) FROM clientes c WHERE c.cobrador_id = u.id) AS clientes,
                (SELECT COUNT(*) FROM clientes c WHERE c.cobrador_id = u.id AND c.estado = 'activo') AS clientes_activos,
                (SELECT COUNT(*) FROM creditos cr JOIN clientes c ON c.id = cr.cliente_id
                    WHERE c.cobrador_id = u.id AND cr.estado = 'activo') AS creditos_activos,
                (SELECT COALESCE(SUM(cr.monto),0) FROM creditos cr JOIN clientes c ON c.id = cr.cliente_id
                    WHERE c.cobrador_id = u.id AND cr.estado = 'activo') AS capital,
                (SELECT COALESCE(SUM(cr.total),0) FROM creditos cr JOIN clientes c ON c.id = cr.cliente_id
                    WHERE c.cobrador_id = u.id AND cr.estado = 'activo') AS total_creditos,
                (SELECT COALESCE(SUM(cr.saldo),0) FROM creditos cr JOIN clientes c ON c.id = cr.cliente_id
                    WHERE c.cobrador_id = u.id AND cr.estado = 'activo') AS cartera,
                (SELECT COALESCE(SUM(cu.valor - cu.abonado),0) FROM cuotas cu
                    JOIN creditos cr ON cr.id = cu.credito_id JOIN clientes c ON c.id = cr.cliente_id
                    WHERE c.cobrador_id = u.id AND cr.estado = 'activo' AND cu.estado <> 'pagada'
                    AND cu.fecha_vencimiento < CURDATE()) AS vencido,
                (SELECT COUNT(DISTINCT cr.id) FROM cuotas cu
                    JOIN creditos cr ON cr.id = cu.credito_id JOIN clientes c ON c.id = cr.cliente_id
                    WHERE c.cobrador_id = u.id AND cr.estado = 'activo' AND cu.estado <> 'pagada'
                    AND cu.fecha_vencimiento < CURDATE()) AS creditos_mora,
                (SELECT COALESCE(SUM(cu.valor - cu.abonado),0) FROM cuotas cu
                    JOIN creditos cr ON cr.id = cu.credito_id JOIN clientes c ON c.id = cr.cliente_id
                    WHERE c.cobrador_id = u.id AND cr.estado = 'activo' AND cu.estado <> 'pagada'
                    AND cu.fecha_vencimiento <= CURDATE()) AS por_cobrar_hoy,
                (SELECT COALESCE(SUM(p.monto),0) FROM pagos p
                    WHERE p.cobrador_id = u.id AND p.anulado = 0 AND p.metodo <> 'renovacion' AND DATE(p.fecha) = CURDATE()) AS recaudo_hoy,
                (SELECT COALESCE(SUM(p.monto),0) FROM pagos p
                    WHERE p.cobrador_id = u.id AND p.anulado = 0 AND p.metodo <> 'renovacion'
                    AND p.fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS recaudo_mes
            FROM usuarios u
            WHERE u.rol = 'cobrador'";
        $p = [];
        if ($cobradorId) {
            $sql .= ' AND u.id = ?';
            $p[] = $cobradorId;
        }
        return $this->fetchAll($sql . ' ORDER BY u.activo DESC, cartera DESC, u.nombre', $p);
    }
}
