<?php

namespace App\Models;

use App\Core\Model;

class Cliente extends Model
{
    protected string $table = 'clientes';
    protected array $fillable = ['cedula', 'nombres', 'apellidos', 'email', 'telefono1', 'telefono2',
        'direccion', 'barrio', 'ciudad', 'fecha_nacimiento', 'ocupacion', 'negocio_nombre',
        'negocio_direccion', 'referencia_nombre', 'referencia_telefono', 'referencia_parentesco',
        'observaciones', 'cobrador_id', 'estado', 'created_by'];

    public static function estados(): array
    {
        return ['activo' => 'Activo', 'inactivo' => 'Inactivo', 'bloqueado' => 'Bloqueado (no prestar)'];
    }

    public function existeCedula(string $cedula, ?int $exceptId = null): bool
    {
        return (bool) $this->fetchValue('SELECT COUNT(*) FROM clientes WHERE cedula = ? AND id <> ?',
            [$cedula, $exceptId ?? 0]);
    }

    /**
     * Listado con saldo y mora.
     * $f: q, cobrador_id, estado, situacion (mora|al_dia|sin_credito)
     */
    public function listar(array $f, ?int $scopeCobrador = null): array
    {
        $sql = "SELECT c.*, u.nombre AS cobrador,
                COALESCE(s.creditos_activos, 0) AS creditos_activos,
                COALESCE(s.saldo, 0) AS saldo,
                COALESCE(m.vencido, 0) AS vencido,
                m.desde AS mora_desde
            FROM clientes c
            LEFT JOIN usuarios u ON u.id = c.cobrador_id
            LEFT JOIN (SELECT cliente_id, COUNT(*) AS creditos_activos, SUM(saldo) AS saldo
                       FROM creditos WHERE estado = 'activo' GROUP BY cliente_id) s ON s.cliente_id = c.id
            LEFT JOIN (SELECT cr.cliente_id, SUM(cu.valor - cu.abonado) AS vencido, MIN(cu.fecha_vencimiento) AS desde
                       FROM cuotas cu JOIN creditos cr ON cr.id = cu.credito_id
                       WHERE cr.estado = 'activo' AND cu.estado <> 'pagada' AND cu.fecha_vencimiento < CURDATE()
                       GROUP BY cr.cliente_id) m ON m.cliente_id = c.id
            WHERE 1=1";
        $p = [];
        if ($scopeCobrador) {
            $sql .= ' AND c.cobrador_id = ?';
            $p[] = $scopeCobrador;
        } elseif (!empty($f['cobrador_id'])) {
            if ($f['cobrador_id'] === 'sin') {
                $sql .= ' AND c.cobrador_id IS NULL';
            } else {
                $sql .= ' AND c.cobrador_id = ?';
                $p[] = (int) $f['cobrador_id'];
            }
        }
        if (!empty($f['q'])) {
            $sql .= " AND (c.cedula LIKE ? OR CONCAT(c.nombres, ' ', c.apellidos) LIKE ? OR c.telefono1 LIKE ?
                      OR c.telefono2 LIKE ? OR c.barrio LIKE ? OR c.direccion LIKE ?)";
            $q = '%' . $f['q'] . '%';
            array_push($p, $q, $q, $q, $q, $q, $q);
        }
        if (!empty($f['estado'])) {
            $sql .= ' AND c.estado = ?';
            $p[] = $f['estado'];
        }
        $sql .= match ($f['situacion'] ?? '') {
            'mora'        => ' AND m.vencido > 0',
            'al_dia'      => ' AND s.creditos_activos > 0 AND m.vencido IS NULL',
            'sin_credito' => ' AND s.creditos_activos IS NULL',
            default       => '',
        };
        return $this->fetchAll($sql . ' ORDER BY c.nombres, c.apellidos', $p);
    }

    public function detalle(int $id): ?array
    {
        return $this->fetchOne('SELECT c.*, u.nombre AS cobrador, u.telefono AS cobrador_telefono,
                cb.nombre AS creado_por
            FROM clientes c
            LEFT JOIN usuarios u ON u.id = c.cobrador_id
            LEFT JOIN usuarios cb ON cb.id = c.created_by
            WHERE c.id = ?', [$id]);
    }

    public function paraSelect(?int $scopeCobrador = null): array
    {
        $sql = "SELECT id, cedula, CONCAT(nombres, ' ', apellidos) AS nombre, estado, cobrador_id
                FROM clientes WHERE estado <> 'inactivo'";
        $p = [];
        if ($scopeCobrador) {
            $sql .= ' AND cobrador_id = ?';
            $p[] = $scopeCobrador;
        }
        return $this->fetchAll($sql . ' ORDER BY nombres, apellidos', $p);
    }

    public function tieneCreditos(int $id): bool
    {
        return (bool) $this->fetchValue('SELECT COUNT(*) FROM creditos WHERE cliente_id = ?', [$id]);
    }

    /** Asigna (o traslada) uno o varios clientes a un cobrador y deja historial. */
    public function asignar(array $clienteIds, ?int $cobradorId, int $usuarioId, ?string $motivo = null): int
    {
        $n = 0;
        $this->db->beginTransaction();
        try {
            $sel = $this->db->prepare('SELECT cobrador_id FROM clientes WHERE id = ? FOR UPDATE');
            // Al cambiar de cobrador el cliente entra al final de la nueva ruta
            $upd = $this->db->prepare('UPDATE clientes SET cobrador_id = ?, orden_ruta = NULL WHERE id = ?');
            $his = $this->db->prepare('INSERT INTO asignaciones (cliente_id, cobrador_anterior_id, cobrador_nuevo_id, usuario_id, motivo)
                                       VALUES (?, ?, ?, ?, ?)');
            foreach ($clienteIds as $cid) {
                $cid = (int) $cid;
                $sel->execute([$cid]);
                $row = $sel->fetch();
                if (!$row) {
                    continue;
                }
                $anterior = $row['cobrador_id'] !== null ? (int) $row['cobrador_id'] : null;
                if ($anterior === $cobradorId) {
                    continue;
                }
                $upd->execute([$cobradorId, $cid]);
                $his->execute([$cid, $anterior, $cobradorId, $usuarioId, $motivo ?: null]);
                $n++;
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $n;
    }

    /**
     * Guarda el orden de visita que el cobrador definió en su ruta.
     * $clienteIds trae solo los clientes que se veían en pantalla (los del día), en el nuevo orden:
     * ocupan las mismas posiciones que tenían, así los clientes que no salen hoy no se mueven.
     * Los clientes sin posición (nuevos o trasladados) quedan numerados al final, por orden de creación.
     */
    public function ordenarRuta(int $cobradorId, array $clienteIds): int
    {
        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare('SELECT id FROM clientes WHERE cobrador_id = ?
                ORDER BY orden_ruta IS NULL, orden_ruta, id FOR UPDATE');
            $st->execute([$cobradorId]);
            $actual = array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));

            $nuevos = array_values(array_intersect(array_unique(array_map('intval', $clienteIds)), $actual));
            $posiciones = array_keys(array_intersect($actual, $nuevos)); // huecos que ocupaban, de menor a mayor
            foreach ($posiciones as $k => $pos) {
                $actual[$pos] = $nuevos[$k];
            }

            $upd = $this->db->prepare('UPDATE clientes SET orden_ruta = ? WHERE id = ?');
            foreach ($actual as $i => $id) {
                $upd->execute([$i + 1, $id]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return count($nuevos);
    }

    public function registrarAsignacionInicial(int $clienteId, ?int $cobradorId, int $usuarioId): void
    {
        if ($cobradorId) {
            $this->db->prepare('INSERT INTO asignaciones (cliente_id, cobrador_anterior_id, cobrador_nuevo_id, usuario_id, motivo)
                VALUES (?, NULL, ?, ?, ?)')->execute([$clienteId, $cobradorId, $usuarioId, 'Asignación inicial']);
        }
    }

    public function historialAsignaciones(int $clienteId): array
    {
        return $this->fetchAll('SELECT a.*, ua.nombre AS anterior, un.nombre AS nuevo, us.nombre AS usuario
            FROM asignaciones a
            LEFT JOIN usuarios ua ON ua.id = a.cobrador_anterior_id
            LEFT JOIN usuarios un ON un.id = a.cobrador_nuevo_id
            LEFT JOIN usuarios us ON us.id = a.usuario_id
            WHERE a.cliente_id = ? ORDER BY a.created_at DESC, a.id DESC', [$clienteId]);
    }

    /**
     * Calificación de pago (A–D) a partir de todo el historial de cuotas del cliente.
     *  - Una cuota cuenta "a tiempo" si se pagó completa hasta 1 día después de su vencimiento (tolerancia de ruta).
     *  - Se evalúan las cuotas ya pagadas y las vencidas sin pagar.
     *  A Excelente: ≥ 90 % a tiempo y sin mora actual > 2 días
     *  B Bueno:     ≥ 75 % y mora actual ≤ 7 días
     *  C Regular:   ≥ 50 % y mora actual ≤ 30 días
     *  D Riesgoso:  el resto
     *  N Sin historial: menos de 3 cuotas evaluadas
     * @return array<int, array> por id de cliente
     */
    public function calificaciones(?array $ids = null): array
    {
        $sql = "SELECT cr.cliente_id,
                SUM(cu.estado = 'pagada' AND cu.fecha_pago <= DATE_ADD(cu.fecha_vencimiento, INTERVAL 1 DAY)) AS a_tiempo,
                SUM(cu.estado = 'pagada' AND cu.fecha_pago > DATE_ADD(cu.fecha_vencimiento, INTERVAL 1 DAY)) AS tarde,
                SUM(cu.estado <> 'pagada' AND cu.fecha_vencimiento < CURDATE()) AS vencidas,
                MAX(CASE WHEN cu.estado <> 'pagada' AND cu.fecha_vencimiento < CURDATE() AND cr.estado = 'activo'
                    THEN DATEDIFF(CURDATE(), cu.fecha_vencimiento) END) AS mora_dias,
                AVG(CASE WHEN cu.estado = 'pagada' THEN GREATEST(0, DATEDIFF(cu.fecha_pago, cu.fecha_vencimiento)) END) AS atraso_promedio,
                COUNT(DISTINCT cr.id) AS creditos,
                COUNT(DISTINCT CASE WHEN cr.estado = 'pagado' THEN cr.id END) AS pagados
            FROM cuotas cu JOIN creditos cr ON cr.id = cu.credito_id
            WHERE cr.estado <> 'anulado' AND (cu.estado = 'pagada' OR cu.fecha_vencimiento < CURDATE())";
        $p = [];
        if ($ids !== null) {
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if (!$ids) {
                return [];
            }
            $sql .= ' AND cr.cliente_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $p = $ids;
        }
        $out = [];
        foreach ($this->fetchAll($sql . ' GROUP BY cr.cliente_id', $p) as $r) {
            $eval = (int) $r['a_tiempo'] + (int) $r['tarde'] + (int) $r['vencidas'];
            $pct = $eval ? (int) $r['a_tiempo'] / $eval : 0;
            $mora = (int) ($r['mora_dias'] ?? 0);
            $letra = match (true) {
                $eval < 3                  => 'N',
                $pct >= .90 && $mora <= 2  => 'A',
                $pct >= .75 && $mora <= 7  => 'B',
                $pct >= .50 && $mora <= 30 => 'C',
                default                    => 'D',
            };
            $out[(int) $r['cliente_id']] = array_merge($r, ['letra' => $letra, 'puntualidad' => $pct, 'evaluadas' => $eval, 'mora_dias' => $mora]);
        }
        return $out;
    }

    public static function calificacionInfo(string $letra): array
    {
        return [
            'A' => ['Excelente', 'success', 'Paga puntual. Apto para renovar o aumentar monto.'],
            'B' => ['Bueno', 'primary', 'Atrasos ocasionales. Apto para renovar.'],
            'C' => ['Regular', 'warning', 'Atrasos frecuentes. Prestar con precaución y montos bajos.'],
            'D' => ['Riesgoso', 'danger', 'Mora alta o incumplimiento. No se recomienda prestar.'],
            'N' => ['Sin historial', 'secondary', 'Aún no tiene suficientes cuotas para calificar.'],
        ][$letra] ?? ['—', 'secondary', ''];
    }

    public function totales(?int $scopeCobrador = null): array
    {
        $w = $scopeCobrador ? ' WHERE cobrador_id = ' . (int) $scopeCobrador : '';
        return $this->fetchOne("SELECT COUNT(*) AS total,
                SUM(estado = 'activo') AS activos,
                SUM(cobrador_id IS NULL) AS sin_cobrador
            FROM clientes{$w}") ?? [];
    }
}
