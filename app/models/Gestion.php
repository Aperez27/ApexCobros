<?php

namespace App\Models;

use App\Core\Model;

/**
 * Gestiones de cobro: lo que pasó en cada visita/llamada sin pago y las promesas de pago.
 * Una promesa se considera CUMPLIDA si entre la gestión y la fecha prometida (inclusive)
 * el cliente pagó al menos el valor prometido (o cualquier valor si no se indicó monto).
 */
class Gestion extends Model
{
    protected string $table = 'gestiones';

    public static function tipos(): array
    {
        return ['visita' => 'Visita', 'llamada' => 'Llamada', 'whatsapp' => 'WhatsApp'];
    }

    public static function resultados(): array
    {
        return [
            'promesa'          => 'Promesa de pago',
            'no_estaba'        => 'No estaba / cerrado',
            'sin_dinero'       => 'Sin dinero hoy',
            'se_niega'         => 'Se niega a pagar',
            'direccion_errada' => 'Dirección errada / se mudó',
            'otro'             => 'Otro',
        ];
    }

    /**
     * $op (opcional): ['uuid', 'usuario_id', 'creado_en', 'sin_conexion'] de una operación de la ruta.
     * Con 'creado_en' la gestión queda con la hora en que se hizo en la calle, no la de sincronización.
     */
    public function registrar(int $creditoId, ?int $usuarioId, string $tipo, string $resultado, ?string $fechaPromesa,
                              ?float $montoPromesa, ?string $nota, ?array $op = null): int
    {
        $this->db->beginTransaction();
        try {
            if ($op) {
                (new OperacionOffline())->reservar($op['uuid'], $op['usuario_id'], 'gestion', $op['creado_en'] ?? null, !empty($op['sin_conexion']));
            }
            $this->db->prepare('INSERT INTO gestiones (credito_id, usuario_id, tipo, resultado, fecha_promesa, monto_promesa, nota, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, COALESCE(?, NOW()))')
                ->execute([$creditoId, $usuarioId, $tipo, $resultado, $resultado === 'promesa' ? $fechaPromesa : null,
                    $resultado === 'promesa' ? $montoPromesa : null, $nota ?: null, $op['creado_en'] ?? null]);
            $id = (int) $this->db->lastInsertId();
            if ($op) {
                (new OperacionOffline())->completar($op['uuid'], $id);
            }
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** SQL del estado de la promesa: cumplida | incumplida | pendiente | null (no es promesa). */
    private function estadoSql(): string
    {
        return "CASE WHEN g.resultado <> 'promesa' THEN NULL
            WHEN (SELECT COALESCE(SUM(p.monto),0) FROM pagos p WHERE p.credito_id = g.credito_id AND p.anulado = 0
                  AND p.metodo <> 'renovacion' AND p.fecha >= g.created_at
                  AND p.fecha < DATE_ADD(g.fecha_promesa, INTERVAL 1 DAY)) >= COALESCE(NULLIF(g.monto_promesa, 0), 1) THEN 'cumplida'
            WHEN g.fecha_promesa < CURDATE() THEN 'incumplida'
            ELSE 'pendiente' END";
    }

    public function porCredito(int $creditoId): array
    {
        return $this->fetchAll("SELECT g.*, u.nombre AS usuario, {$this->estadoSql()} AS estado_promesa
            FROM gestiones g LEFT JOIN usuarios u ON u.id = g.usuario_id
            WHERE g.credito_id = ? ORDER BY g.created_at DESC, g.id DESC", [$creditoId]);
    }

    /** Promesas que vencen en la fecha (para la ruta y el panel), con su estado. */
    public function promesasDelDia(?int $cobradorId, string $fecha): array
    {
        $sql = "SELECT g.*, cr.codigo, cr.saldo, CONCAT(c.nombres, ' ', c.apellidos) AS cliente, c.telefono1, c.id AS cliente_id,
                u.nombre AS cobrador, {$this->estadoSql()} AS estado_promesa
            FROM gestiones g
            JOIN creditos cr ON cr.id = g.credito_id
            JOIN clientes c ON c.id = cr.cliente_id
            LEFT JOIN usuarios u ON u.id = c.cobrador_id
            WHERE g.resultado = 'promesa' AND g.fecha_promesa = ? AND cr.estado = 'activo'";
        $p = [$fecha];
        if ($cobradorId) {
            $sql .= ' AND c.cobrador_id = ?';
            $p[] = $cobradorId;
        }
        return $this->fetchAll($sql . ' ORDER BY g.monto_promesa DESC', $p);
    }

    /** Última gestión de cada crédito (para la ruta y el reporte de mora). */
    public function ultimas(array $creditoIds): array
    {
        $creditoIds = array_values(array_filter(array_map('intval', $creditoIds)));
        if (!$creditoIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($creditoIds), '?'));
        $rows = $this->fetchAll("SELECT g.*, {$this->estadoSql()} AS estado_promesa FROM gestiones g
            JOIN (SELECT credito_id, MAX(id) AS id FROM gestiones WHERE credito_id IN ({$in}) GROUP BY credito_id) x ON x.id = g.id", $creditoIds);
        return array_column($rows, null, 'credito_id');
    }

    /** Listado para reporte: $f desde, hasta, cobrador_id */
    public function listar(array $f): array
    {
        $sql = "SELECT g.*, cr.codigo, CONCAT(c.nombres, ' ', c.apellidos) AS cliente, c.cedula, c.telefono1,
                u.nombre AS usuario, cb.nombre AS cobrador, {$this->estadoSql()} AS estado_promesa
            FROM gestiones g
            JOIN creditos cr ON cr.id = g.credito_id
            JOIN clientes c ON c.id = cr.cliente_id
            LEFT JOIN usuarios u ON u.id = g.usuario_id
            LEFT JOIN usuarios cb ON cb.id = c.cobrador_id
            WHERE g.created_at BETWEEN ? AND ?";
        $p = [$f['desde'] . ' 00:00:00', $f['hasta'] . ' 23:59:59'];
        if (!empty($f['cobrador_id'])) {
            $sql .= ' AND c.cobrador_id = ?';
            $p[] = (int) $f['cobrador_id'];
        }
        return $this->fetchAll($sql . ' ORDER BY g.created_at DESC', $p);
    }

    /** Efectividad de promesas por cobrador en un rango (ya vencidas o cumplidas). */
    public function efectividad(string $desde, string $hasta, ?int $cobradorId = null): array
    {
        $rows = $this->listar(['desde' => $desde, 'hasta' => $hasta, 'cobrador_id' => $cobradorId]);
        $out = [];
        foreach ($rows as $r) {
            $k = $r['cobrador'] ?? 'Sin cobrador';
            $out[$k] ??= ['cobrador' => $k, 'gestiones' => 0, 'promesas' => 0, 'cumplidas' => 0, 'incumplidas' => 0, 'pendientes' => 0];
            $out[$k]['gestiones']++;
            if ($r['resultado'] === 'promesa') {
                $out[$k]['promesas']++;
                $out[$k][['cumplida' => 'cumplidas', 'incumplida' => 'incumplidas', 'pendiente' => 'pendientes'][$r['estado_promesa']]]++;
            }
        }
        foreach ($out as &$o) {
            $evaluadas = $o['cumplidas'] + $o['incumplidas'];
            $o['efectividad'] = $evaluadas ? $o['cumplidas'] / $evaluadas : null;
        }
        return array_values($out);
    }
}
