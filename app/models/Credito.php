<?php

namespace App\Models;

use App\Core\Model;
use DateTime;

class Credito extends Model
{
    protected string $table = 'creditos';

    /**
     * Calcula el plan de pagos.
     * Interés simple (porcentaje total sobre el capital) repartido en cuotas iguales;
     * la cuota se redondea a la centena y la última absorbe la diferencia.
     */
    public static function calcularPlan(float $monto, float $interes, int $numCuotas, string $frecuencia,
                                        string $fechaPrimera, bool $excluirDomingos): array
    {
        $total = round($monto * (1 + $interes / 100));
        $cuota = ceil($total / $numCuotas / 100) * 100;
        if ($total - $cuota * ($numCuotas - 1) <= 0) {
            $cuota = floor($total / $numCuotas);
        }
        $ultima = $total - $cuota * ($numCuotas - 1);

        $fecha = new DateTime($fechaPrimera);
        $diaMes = (int) $fecha->format('j');
        $plan = [];
        for ($i = 1; $i <= $numCuotas; $i++) {
            if ($i > 1) {
                switch ($frecuencia) {
                    case 'semanal':
                        $fecha->modify('+7 days');
                        break;
                    case 'quincenal':
                        $fecha->modify('+15 days');
                        break;
                    case 'mensual':
                        $fecha = self::sumarMes($fecha, $diaMes);
                        break;
                    default:
                        $fecha->modify('+1 day');
                }
            }
            if ($frecuencia === 'diaria' && $excluirDomingos && $fecha->format('w') === '0') {
                $fecha->modify('+1 day');
            }
            $plan[] = [
                'numero' => $i,
                'fecha'  => $fecha->format('Y-m-d'),
                'valor'  => $i === $numCuotas ? $ultima : $cuota,
            ];
        }
        return [
            'total'       => $total,
            'valor_cuota' => $cuota,
            'interes_valor' => $total - $monto,
            'fecha_fin'   => end($plan)['fecha'],
            'cuotas'      => $plan,
        ];
    }

    /** Suma un mes conservando el día original (31 ene -> 28/29 feb -> 31 mar). */
    private static function sumarMes(DateTime $fecha, int $dia): DateTime
    {
        $y = (int) $fecha->format('Y');
        $m = (int) $fecha->format('n') + 1;
        if ($m > 12) {
            $m = 1;
            $y++;
        }
        $max = (int) date('t', mktime(0, 0, 0, $m, 1, $y));
        return new DateTime(sprintf('%04d-%02d-%02d', $y, $m, min($dia, $max)));
    }

    public function crear(array $d, int $usuarioId): int
    {
        $plan = self::calcularPlan($d['monto'], $d['interes'], $d['num_cuotas'], $d['frecuencia'],
            $d['fecha_primera_cuota'], $d['excluir_domingos']);

        $this->db->beginTransaction();
        try {
            $id = $this->insertar($d, $plan, $usuarioId);
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Renovación (refinanciación): crea un crédito nuevo y cancela el saldo del anterior con un
     * pago de tipo "renovacion" (no es dinero recibido). Al cliente se le entrega solo la diferencia.
     * Todo ocurre en una sola transacción.
     */
    public function renovar(int $anteriorId, array $d, int $usuarioId): int
    {
        $plan = self::calcularPlan($d['monto'], $d['interes'], $d['num_cuotas'], $d['frecuencia'],
            $d['fecha_primera_cuota'], $d['excluir_domingos']);
        $this->db->beginTransaction();
        try {
            $ant = $this->fetchOne("SELECT * FROM creditos WHERE id = ? AND estado = 'activo' FOR UPDATE", [$anteriorId]);
            if (!$ant) {
                throw new \DomainException('El crédito a renovar ya no está activo.');
            }
            $saldo = (float) $ant['saldo'];
            if ($d['monto'] <= $saldo) {
                throw new \DomainException('El nuevo monto debe ser mayor al saldo pendiente (' . money($saldo) . ').');
            }
            $id = $this->insertar(['cliente_id' => (int) $ant['cliente_id'], 'renovado_de' => $anteriorId, 'saldo_renovado' => $saldo] + $d,
                $plan, $usuarioId);
            $codigo = sprintf('CR-%05d', $id);
            $this->db->prepare("INSERT INTO pagos (recibo, credito_id, cobrador_id, monto, metodo, fecha, nota)
                VALUES (?, ?, ?, ?, 'renovacion', NOW(), ?)")
                ->execute(['TMP' . bin2hex(random_bytes(6)), $anteriorId, $usuarioId, $saldo, 'Saldo cancelado por renovación con ' . $codigo]);
            $pid = (int) $this->db->lastInsertId();
            $this->db->prepare('UPDATE pagos SET recibo = ? WHERE id = ?')->execute([sprintf('RC-%06d', $pid), $pid]);
            $this->recalcular($anteriorId);
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function insertar(array $d, array $plan, int $usuarioId): int
    {
        $saldoRenovado = (float) ($d['saldo_renovado'] ?? 0);
        $st = $this->db->prepare('INSERT INTO creditos (codigo, cliente_id, renovado_de, monto, saldo_renovado, neto_entregado,
                interes, total, num_cuotas, valor_cuota, frecuencia, excluir_domingos, fecha_inicio, fecha_primera_cuota, fecha_fin,
                saldo, observaciones, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([
            'TMP' . bin2hex(random_bytes(6)), $d['cliente_id'], $d['renovado_de'] ?? null, $d['monto'], $saldoRenovado,
            $d['monto'] - $saldoRenovado, $d['interes'], $plan['total'], $d['num_cuotas'], $plan['valor_cuota'], $d['frecuencia'],
            $d['excluir_domingos'] ? 1 : 0, $d['fecha_inicio'], $plan['cuotas'][0]['fecha'], $plan['fecha_fin'], $plan['total'],
            ($d['observaciones'] ?? '') ?: null, $usuarioId,
        ]);
        $id = (int) $this->db->lastInsertId();
        $this->db->prepare('UPDATE creditos SET codigo = ? WHERE id = ?')->execute([sprintf('CR-%05d', $id), $id]);
        $ins = $this->db->prepare('INSERT INTO cuotas (credito_id, numero, fecha_vencimiento, valor) VALUES (?, ?, ?, ?)');
        foreach ($plan['cuotas'] as $c) {
            $ins->execute([$id, $c['numero'], $c['fecha'], $c['valor']]);
        }
        return $id;
    }

    private function baseSelect(): string
    {
        return "SELECT cr.*, CONCAT(c.nombres, ' ', c.apellidos) AS cliente, c.cedula, c.telefono1, c.telefono2,
                c.direccion, c.barrio, c.cobrador_id, u.nombre AS cobrador,
                (SELECT a.codigo FROM creditos a WHERE a.id = cr.renovado_de) AS renovado_de_codigo,
                (SELECT n.id FROM creditos n WHERE n.renovado_de = cr.id LIMIT 1) AS renovado_por_id,
                (SELECT n.codigo FROM creditos n WHERE n.renovado_de = cr.id LIMIT 1) AS renovado_por_codigo,
                COALESCE(m.vencido, 0) AS vencido, COALESCE(m.cuotas_vencidas, 0) AS cuotas_vencidas, m.desde AS mora_desde,
                (SELECT COUNT(*) FROM cuotas x WHERE x.credito_id = cr.id AND x.estado = 'pagada') AS cuotas_pagadas
            FROM creditos cr
            JOIN clientes c ON c.id = cr.cliente_id
            LEFT JOIN usuarios u ON u.id = c.cobrador_id
            LEFT JOIN (SELECT credito_id, SUM(valor - abonado) AS vencido, COUNT(*) AS cuotas_vencidas,
                              MIN(fecha_vencimiento) AS desde
                       FROM cuotas WHERE estado <> 'pagada' AND fecha_vencimiento < CURDATE()
                       GROUP BY credito_id) m ON m.credito_id = cr.id AND cr.estado = 'activo'";
    }

    /** $f: q, estado, cobrador_id, mora (1) */
    public function listar(array $f, ?int $scopeCobrador = null): array
    {
        $sql = $this->baseSelect() . ' WHERE 1=1';
        $p = [];
        if ($scopeCobrador) {
            $sql .= ' AND c.cobrador_id = ?';
            $p[] = $scopeCobrador;
        } elseif (!empty($f['cobrador_id'])) {
            $sql .= ' AND c.cobrador_id = ?';
            $p[] = (int) $f['cobrador_id'];
        }
        if (!empty($f['q'])) {
            $sql .= " AND (cr.codigo LIKE ? OR c.cedula LIKE ? OR CONCAT(c.nombres, ' ', c.apellidos) LIKE ?)";
            $q = '%' . $f['q'] . '%';
            array_push($p, $q, $q, $q);
        }
        if (!empty($f['estado'])) {
            $sql .= ' AND cr.estado = ?';
            $p[] = $f['estado'];
        }
        if (!empty($f['mora'])) {
            $sql .= ' AND m.vencido > 0';
        }
        return $this->fetchAll($sql . " ORDER BY FIELD(cr.estado, 'activo', 'pagado', 'anulado'), cr.id DESC", $p);
    }

    public function detalle(int $id): ?array
    {
        return $this->fetchOne($this->baseSelect() . ' WHERE cr.id = ?', [$id]);
    }

    public function porCliente(int $clienteId): array
    {
        return $this->fetchAll($this->baseSelect() . ' WHERE cr.cliente_id = ? ORDER BY cr.id DESC', [$clienteId]);
    }

    public function cuotas(int $creditoId): array
    {
        return $this->fetchAll('SELECT * FROM cuotas WHERE credito_id = ? ORDER BY numero', [$creditoId]);
    }

    /**
     * Ruta de cobro: créditos activos con cuotas que vencen hasta hoy (incluye atrasadas).
     */
    public function ruta(?int $cobradorId, string $fecha): array
    {
        $sql = "SELECT cr.id, cr.codigo, cr.valor_cuota, cr.saldo, cr.frecuencia,
                CONCAT(c.nombres, ' ', c.apellidos) AS cliente, c.id AS cliente_id, c.cedula, c.telefono1,
                c.direccion, c.barrio, c.orden_ruta, u.nombre AS cobrador,
                SUM(cu.valor - cu.abonado) AS a_cobrar,
                SUM(CASE WHEN cu.fecha_vencimiento < ? THEN cu.valor - cu.abonado ELSE 0 END) AS atrasado,
                SUM(cu.fecha_vencimiento < ?) AS cuotas_atrasadas,
                MIN(cu.fecha_vencimiento) AS desde,
                (SELECT COALESCE(SUM(p.monto),0) FROM pagos p WHERE p.credito_id = cr.id AND p.anulado = 0
                    AND p.metodo <> 'renovacion' AND DATE(p.fecha) = ?) AS pagado_hoy
            FROM creditos cr
            JOIN clientes c ON c.id = cr.cliente_id
            LEFT JOIN usuarios u ON u.id = c.cobrador_id
            JOIN cuotas cu ON cu.credito_id = cr.id AND cu.estado <> 'pagada' AND cu.fecha_vencimiento <= ?
            WHERE cr.estado = 'activo'";
        $p = [$fecha, $fecha, $fecha, $fecha];
        if ($cobradorId) {
            $sql .= ' AND c.cobrador_id = ?';
            $p[] = $cobradorId;
        }
        $sql .= ' GROUP BY cr.id';
        $rows = $this->fetchAll($sql, $p);

        // Créditos que ya pagaron hoy (y quedaron al día) también se muestran como "cobrados"
        $sql2 = "SELECT cr.id, cr.codigo, cr.valor_cuota, cr.saldo, cr.frecuencia,
                CONCAT(c.nombres, ' ', c.apellidos) AS cliente, c.id AS cliente_id, c.cedula, c.telefono1,
                c.direccion, c.barrio, c.orden_ruta, u.nombre AS cobrador, 0 AS a_cobrar, 0 AS atrasado, 0 AS cuotas_atrasadas,
                NULL AS desde, SUM(p.monto) AS pagado_hoy
            FROM pagos p
            JOIN creditos cr ON cr.id = p.credito_id
            JOIN clientes c ON c.id = cr.cliente_id
            LEFT JOIN usuarios u ON u.id = c.cobrador_id
            WHERE p.anulado = 0 AND p.metodo <> 'renovacion' AND DATE(p.fecha) = ?";
        $p2 = [$fecha];
        if ($cobradorId) {
            $sql2 .= ' AND c.cobrador_id = ?';
            $p2[] = $cobradorId;
        }
        $ids = array_column($rows, 'id');
        foreach ($this->fetchAll($sql2 . ' GROUP BY cr.id', $p2) as $r) {
            if (!in_array($r['id'], $ids)) {
                $rows[] = $r;
            }
        }
        // Orden de visita definido por el cobrador; los clientes sin posición (nuevos) van al final
        usort($rows, fn ($a, $b) => [$a['cobrador'] ?? '', $a['orden_ruta'] === null, (int) $a['orden_ruta'], (int) $a['cliente_id'], (int) $a['id']]
            <=> [$b['cobrador'] ?? '', $b['orden_ruta'] === null, (int) $b['orden_ruta'], (int) $b['cliente_id'], (int) $b['id']]);
        return $rows;
    }

    /**
     * Recalcula abonos de cuotas, total pagado, saldo y estado a partir de los pagos vigentes.
     * Se usa tras registrar o anular un pago, así el plan siempre cuadra con la caja.
     */
    public function recalcular(int $creditoId): void
    {
        $credito = $this->find($creditoId);
        if (!$credito) {
            return;
        }
        $cuotas = $this->cuotas($creditoId);
        $pagos = $this->fetchAll('SELECT monto, fecha FROM pagos WHERE credito_id = ? AND anulado = 0 ORDER BY fecha, id',
            [$creditoId]);

        foreach ($cuotas as &$c) {
            $c['abonado'] = 0.0;
            $c['fecha_pago'] = null;
        }
        unset($c);

        $totalPagado = 0.0;
        foreach ($pagos as $p) {
            $resto = (float) $p['monto'];
            $totalPagado += $resto;
            foreach ($cuotas as &$c) {
                if ($resto <= 0) {
                    break;
                }
                $falta = (float) $c['valor'] - $c['abonado'];
                if ($falta <= 0) {
                    continue;
                }
                $aplica = min($falta, $resto);
                $c['abonado'] += $aplica;
                $resto -= $aplica;
                if ($c['abonado'] >= (float) $c['valor']) {
                    $c['fecha_pago'] = substr($p['fecha'], 0, 10);
                }
            }
            unset($c);
        }

        $upd = $this->db->prepare('UPDATE cuotas SET abonado = ?, estado = ?, fecha_pago = ? WHERE id = ?');
        foreach ($cuotas as $c) {
            $estado = $c['abonado'] >= (float) $c['valor'] ? 'pagada' : ($c['abonado'] > 0 ? 'parcial' : 'pendiente');
            $upd->execute([$c['abonado'], $estado, $c['fecha_pago'], $c['id']]);
        }

        $saldo = max(0, (float) $credito['total'] - $totalPagado);
        $estado = $credito['estado'] === 'anulado' ? 'anulado' : ($saldo <= 0 ? 'pagado' : 'activo');
        $this->db->prepare('UPDATE creditos SET total_pagado = ?, saldo = ?, estado = ? WHERE id = ?')
            ->execute([$totalPagado, $saldo, $estado, $creditoId]);
    }

    public function anular(int $id, string $motivo): void
    {
        $this->db->prepare("UPDATE creditos SET estado = 'anulado', motivo_anulacion = ? WHERE id = ?")
            ->execute([$motivo, $id]);
    }

    public function tienePagos(int $id): bool
    {
        return (bool) $this->fetchValue('SELECT COUNT(*) FROM pagos WHERE credito_id = ? AND anulado = 0', [$id]);
    }

    /** Totales globales (o de un cobrador) para el dashboard. */
    public function resumen(?int $cobradorId = null): array
    {
        $w = $cobradorId ? ' AND c.cobrador_id = ' . (int) $cobradorId : '';
        $r = $this->fetchOne("SELECT COUNT(*) AS activos, COALESCE(SUM(cr.monto),0) AS capital,
                COALESCE(SUM(cr.saldo),0) AS cartera, COALESCE(SUM(cr.total_pagado),0) AS recuperado
            FROM creditos cr JOIN clientes c ON c.id = cr.cliente_id WHERE cr.estado = 'activo'{$w}") ?? [];
        $m = $this->fetchOne("SELECT COALESCE(SUM(cu.valor - cu.abonado),0) AS vencido, COUNT(DISTINCT cr.id) AS en_mora
            FROM cuotas cu JOIN creditos cr ON cr.id = cu.credito_id JOIN clientes c ON c.id = cr.cliente_id
            WHERE cr.estado = 'activo' AND cu.estado <> 'pagada' AND cu.fecha_vencimiento < CURDATE(){$w}") ?? [];
        $h = $this->fetchOne("SELECT COALESCE(SUM(cu.valor - cu.abonado),0) AS hoy
            FROM cuotas cu JOIN creditos cr ON cr.id = cu.credito_id JOIN clientes c ON c.id = cr.cliente_id
            WHERE cr.estado = 'activo' AND cu.estado <> 'pagada' AND cu.fecha_vencimiento = CURDATE(){$w}") ?? [];
        $pm = $this->fetchOne("SELECT COALESCE(SUM(COALESCE(cr.neto_entregado, cr.monto)),0) AS prestado_mes, COUNT(*) AS nuevos_mes
            FROM creditos cr JOIN clientes c ON c.id = cr.cliente_id
            WHERE cr.estado <> 'anulado' AND cr.fecha_inicio >= DATE_FORMAT(CURDATE(), '%Y-%m-01'){$w}") ?? [];
        return array_merge($r, $m, $h, $pm);
    }

    public function proximosAVencer(?int $cobradorId = null, int $limit = 8): array
    {
        $w = $cobradorId ? ' AND c.cobrador_id = ' . (int) $cobradorId : '';
        return $this->fetchAll($this->baseSelect() . " WHERE cr.estado = 'activo' AND m.vencido > 0{$w}
            ORDER BY m.desde ASC LIMIT " . (int) $limit);
    }
}
