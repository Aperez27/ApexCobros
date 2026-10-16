<?php

namespace App\Models;

use App\Core\Model;

class Pago extends Model
{
    protected string $table = 'pagos';

    /**
     * $op (opcional): ['uuid', 'usuario_id', 'creado_en', 'sin_conexion'] de una operación de la ruta.
     * Si ese uuid ya se registró lanza OperacionDuplicada y no crea otro pago.
     */
    public function registrar(int $creditoId, float $monto, string $metodo, ?int $cobradorId, string $fecha, ?string $nota,
                              ?array $op = null): int
    {
        $this->db->beginTransaction();
        try {
            if ($op) {
                (new OperacionOffline())->reservar($op['uuid'], $op['usuario_id'], 'pago', $op['creado_en'] ?? null, !empty($op['sin_conexion']));
            }
            // Bloquea el crédito para evitar dos pagos simultáneos que excedan el saldo
            $saldo = (float) $this->fetchValue('SELECT saldo FROM creditos WHERE id = ? AND estado = \'activo\' FOR UPDATE', [$creditoId]);
            if ($saldo <= 0 || $monto > $saldo) {
                throw new \DomainException('El valor supera el saldo pendiente (' . money($saldo) . ').');
            }
            $st = $this->db->prepare('INSERT INTO pagos (recibo, credito_id, cobrador_id, monto, metodo, fecha, nota)
                VALUES (?, ?, ?, ?, ?, ?, ?)');
            $st->execute(['TMP' . bin2hex(random_bytes(6)), $creditoId, $cobradorId, $monto, $metodo, $fecha, $nota ?: null]);
            $id = (int) $this->db->lastInsertId();
            $this->db->prepare('UPDATE pagos SET recibo = ? WHERE id = ?')->execute([sprintf('RC-%06d', $id), $id]);
            (new Credito())->recalcular($creditoId);
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

    public function anular(int $id, string $motivo, int $usuarioId): void
    {
        $pago = $this->find($id);
        if (!$pago || $pago['anulado']) {
            return;
        }
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE pagos SET anulado = 1, motivo_anulacion = ?, anulado_por = ? WHERE id = ?')
                ->execute([$motivo, $usuarioId, $id]);
            (new Credito())->recalcular((int) $pago['credito_id']);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function baseSelect(): string
    {
        return "SELECT p.*, cr.codigo, cr.saldo AS saldo_credito, cr.total AS total_credito, cr.cliente_id,
                CONCAT(c.nombres, ' ', c.apellidos) AS cliente, c.cedula, c.telefono1, c.direccion,
                u.nombre AS cobrador, ua.nombre AS anulado_por_nombre
            FROM pagos p
            JOIN creditos cr ON cr.id = p.credito_id
            JOIN clientes c ON c.id = cr.cliente_id
            LEFT JOIN usuarios u ON u.id = p.cobrador_id
            LEFT JOIN usuarios ua ON ua.id = p.anulado_por";
    }

    /** $f: desde, hasta, cobrador_id, q, metodo, anulados */
    public function listar(array $f, ?int $scopeCobrador = null): array
    {
        [$where, $p] = $this->filtros($f, $scopeCobrador);
        return $this->fetchAll($this->baseSelect() . $where . ' ORDER BY p.fecha DESC, p.id DESC LIMIT 1000', $p);
    }

    public function totales(array $f, ?int $scopeCobrador = null): array
    {
        $f['anulados'] = '';
        $f['solo_recaudo'] = true;
        [$where, $p] = $this->filtros($f, $scopeCobrador);
        return $this->fetchOne("SELECT COUNT(*) AS cantidad, COALESCE(SUM(p.monto),0) AS total
            FROM pagos p JOIN creditos cr ON cr.id = p.credito_id JOIN clientes c ON c.id = cr.cliente_id{$where}", $p) ?? [];
    }

    private function filtros(array $f, ?int $scopeCobrador): array
    {
        $w = ' WHERE 1=1';
        $p = [];
        if (empty($f['anulados'])) {
            $w .= ' AND p.anulado = 0';
        }
        if (!empty($f['desde'])) {
            $w .= ' AND p.fecha >= ?';
            $p[] = $f['desde'] . ' 00:00:00';
        }
        if (!empty($f['hasta'])) {
            $w .= ' AND p.fecha <= ?';
            $p[] = $f['hasta'] . ' 23:59:59';
        }
        if ($scopeCobrador) {
            $w .= ' AND p.cobrador_id = ?';
            $p[] = $scopeCobrador;
        } elseif (!empty($f['cobrador_id'])) {
            $w .= ' AND p.cobrador_id = ?';
            $p[] = (int) $f['cobrador_id'];
        }
        if (!empty($f['metodo'])) {
            $w .= ' AND p.metodo = ?';
            $p[] = $f['metodo'];
        } elseif (!empty($f['solo_recaudo'])) {
            // Las renovaciones cancelan saldo pero no son dinero recibido
            $w .= " AND p.metodo <> 'renovacion'";
        }
        if (!empty($f['q'])) {
            $w .= " AND (p.recibo LIKE ? OR cr.codigo LIKE ? OR c.cedula LIKE ? OR CONCAT(c.nombres, ' ', c.apellidos) LIKE ?)";
            $q = '%' . $f['q'] . '%';
            array_push($p, $q, $q, $q, $q);
        }
        return [$w, $p];
    }

    public function detalle(int $id): ?array
    {
        return $this->fetchOne($this->baseSelect() . ' WHERE p.id = ?', [$id]);
    }

    public function porCredito(int $creditoId): array
    {
        return $this->fetchAll($this->baseSelect() . ' WHERE p.credito_id = ? ORDER BY p.fecha DESC, p.id DESC', [$creditoId]);
    }

    /** Recaudo diario de los últimos N días (para la gráfica). */
    public function recaudoDiario(int $dias, ?int $cobradorId = null): array
    {
        $w = $cobradorId ? ' AND cobrador_id = ' . (int) $cobradorId : '';
        $rows = $this->fetchAll("SELECT DATE(fecha) AS dia, SUM(monto) AS total FROM pagos
            WHERE anulado = 0 AND metodo <> 'renovacion' AND fecha >= DATE_SUB(CURDATE(), INTERVAL ? DAY){$w} GROUP BY DATE(fecha)", [$dias - 1]);
        $map = array_column($rows, 'total', 'dia');
        $out = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $out[$d] = (float) ($map[$d] ?? 0);
        }
        return $out;
    }

    /** Recaudo agrupado por cobrador y método en un rango (liquidación). */
    public function liquidacion(string $desde, string $hasta, ?int $cobradorId = null): array
    {
        $sql = "SELECT u.id, COALESCE(u.nombre, 'Sin cobrador') AS cobrador, COUNT(p.id) AS pagos,
                SUM(p.monto) AS total,
                SUM(CASE WHEN p.metodo = 'efectivo' THEN p.monto ELSE 0 END) AS efectivo,
                SUM(CASE WHEN p.metodo <> 'efectivo' THEN p.monto ELSE 0 END) AS digital
            FROM pagos p LEFT JOIN usuarios u ON u.id = p.cobrador_id
            WHERE p.anulado = 0 AND p.metodo <> 'renovacion' AND p.fecha BETWEEN ? AND ?";
        $p = [$desde . ' 00:00:00', $hasta . ' 23:59:59'];
        if ($cobradorId) {
            $sql .= ' AND p.cobrador_id = ?';
            $p[] = $cobradorId;
        }
        return $this->fetchAll($sql . ' GROUP BY u.id, u.nombre ORDER BY total DESC', $p);
    }
}
