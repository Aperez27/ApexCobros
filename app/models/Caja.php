<?php

namespace App\Models;

use App\Core\Model;

/**
 * Caja del cobrador: gastos de ruta, cierres diarios y efectivo en mano.
 *
 * Efectivo en mano = todo el efectivo cobrado − gastos − lo entregado en cierres.
 * Así un faltante en un cierre queda pendiente a cargo del cobrador hasta que lo entregue.
 */
class Caja extends Model
{
    protected string $table = 'cierres';

    public static function conceptos(): array
    {
        return ['transporte' => 'Transporte / gasolina', 'alimentacion' => 'Alimentación', 'papeleria' => 'Papelería',
            'recarga' => 'Recarga celular', 'otro' => 'Otro'];
    }

    // ------------------------------------------------------------ gastos

    public function registrarGasto(int $cobradorId, string $fecha, string $concepto, float $valor, ?string $nota, int $registradoPor): int
    {
        $this->db->prepare('INSERT INTO gastos (cobrador_id, fecha, concepto, valor, nota, registrado_por, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())')->execute([$cobradorId, $fecha, $concepto, $valor, $nota ?: null, $registradoPor]);
        return (int) $this->db->lastInsertId();
    }

    public function gasto(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM gastos WHERE id = ?', [$id]);
    }

    public function anularGasto(int $id): void
    {
        $this->db->prepare('UPDATE gastos SET anulado = 1 WHERE id = ?')->execute([$id]);
    }

    public function gastos(?int $cobradorId, string $desde, string $hasta): array
    {
        $sql = 'SELECT g.*, u.nombre AS cobrador, r.nombre AS registrado FROM gastos g
            JOIN usuarios u ON u.id = g.cobrador_id LEFT JOIN usuarios r ON r.id = g.registrado_por
            WHERE g.fecha BETWEEN ? AND ?';
        $p = [$desde, $hasta];
        if ($cobradorId) {
            $sql .= ' AND g.cobrador_id = ?';
            $p[] = $cobradorId;
        }
        return $this->fetchAll($sql . ' ORDER BY g.fecha DESC, g.id DESC', $p);
    }

    public function diaCerrado(int $cobradorId, string $fecha): bool
    {
        return (bool) $this->fetchValue('SELECT COUNT(*) FROM cierres WHERE cobrador_id = ? AND fecha = ?', [$cobradorId, $fecha]);
    }

    // ------------------------------------------------------------ cierres

    /** Movimiento de un día por cobrador: efectivo, digital, gastos, a entregar y su cierre (si existe). */
    public function resumenDia(string $fecha, ?int $cobradorId = null): array
    {
        $sql = "SELECT u.id, u.nombre, u.zona, u.activo,
                COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.cobrador_id = u.id AND p.anulado = 0
                    AND p.metodo = 'efectivo' AND p.fecha >= ? AND p.fecha < DATE_ADD(?, INTERVAL 1 DAY)), 0) AS efectivo,
                COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.cobrador_id = u.id AND p.anulado = 0
                    AND p.metodo NOT IN ('efectivo', 'renovacion') AND p.fecha >= ? AND p.fecha < DATE_ADD(?, INTERVAL 1 DAY)), 0) AS digital,
                (SELECT COUNT(*) FROM pagos p WHERE p.cobrador_id = u.id AND p.anulado = 0 AND p.metodo <> 'renovacion'
                    AND p.fecha >= ? AND p.fecha < DATE_ADD(?, INTERVAL 1 DAY)) AS num_pagos,
                COALESCE((SELECT SUM(g.valor) FROM gastos g WHERE g.cobrador_id = u.id AND g.anulado = 0 AND g.fecha = ?), 0) AS gastos,
                ci.id AS cierre_id, ci.entregado, ci.diferencia, ci.a_entregar AS a_entregar_cierre, ci.observaciones,
                ci.created_at AS cerrado_en, r.nombre AS recibido_por
            FROM usuarios u
            LEFT JOIN cierres ci ON ci.cobrador_id = u.id AND ci.fecha = ?
            LEFT JOIN usuarios r ON r.id = ci.recibido_por
            WHERE u.rol = 'cobrador'";
        $p = [$fecha, $fecha, $fecha, $fecha, $fecha, $fecha, $fecha, $fecha];
        if ($cobradorId) {
            $sql .= ' AND u.id = ?';
            $p[] = $cobradorId;
        }
        $rows = $this->fetchAll($sql . ' ORDER BY u.activo DESC, u.nombre', $p);
        foreach ($rows as &$r) {
            $r['a_entregar'] = (float) $r['efectivo'] - (float) $r['gastos'];
            // Si después del cierre entraron pagos o gastos, el cierre quedó desactualizado
            $r['desactualizado'] = $r['cierre_id'] && abs($r['a_entregar'] - (float) $r['a_entregar_cierre']) > 0.5;
        }
        // Oculta cobradores inactivos sin movimiento
        return array_values(array_filter($rows, fn ($r) => $r['activo'] || $r['efectivo'] > 0 || $r['digital'] > 0 || $r['gastos'] > 0 || $r['cierre_id']));
    }

    public function cerrar(int $cobradorId, string $fecha, float $entregado, ?string $obs, int $recibidoPor): array
    {
        $r = $this->resumenDia($fecha, $cobradorId)[0] ?? null;
        if (!$r) {
            throw new \DomainException('Cobrador no válido.');
        }
        // Si los gastos superaron el efectivo, no hay nada que entregar: la empresa le reembolsa
        // la diferencia al cobrador (se guarda como entrega negativa y el cierre queda cuadrado).
        if ($r['a_entregar'] < 0 && $entregado == 0) {
            $entregado = $r['a_entregar'];
        }
        $dif = $entregado - $r['a_entregar'];
        $this->db->prepare('INSERT INTO cierres (cobrador_id, fecha, recaudo_efectivo, recaudo_digital, gastos, a_entregar, entregado,
                diferencia, observaciones, recibido_por, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE recaudo_efectivo = VALUES(recaudo_efectivo), recaudo_digital = VALUES(recaudo_digital),
                gastos = VALUES(gastos), a_entregar = VALUES(a_entregar), entregado = VALUES(entregado), diferencia = VALUES(diferencia),
                observaciones = VALUES(observaciones), recibido_por = VALUES(recibido_por), created_at = NOW()')
            ->execute([$cobradorId, $fecha, $r['efectivo'], $r['digital'], $r['gastos'], $r['a_entregar'], $entregado, $dif,
                $obs ?: null, $recibidoPor]);
        return $r + ['diferencia_nueva' => $dif];
    }

    public function historial(?int $cobradorId, string $desde, string $hasta): array
    {
        $sql = 'SELECT ci.*, u.nombre AS cobrador, r.nombre AS recibido FROM cierres ci
            JOIN usuarios u ON u.id = ci.cobrador_id LEFT JOIN usuarios r ON r.id = ci.recibido_por
            WHERE ci.fecha BETWEEN ? AND ?';
        $p = [$desde, $hasta];
        if ($cobradorId) {
            $sql .= ' AND ci.cobrador_id = ?';
            $p[] = $cobradorId;
        }
        return $this->fetchAll($sql . ' ORDER BY ci.fecha DESC, u.nombre', $p);
    }

    /** Efectivo que cada cobrador tiene en su poder (no entregado). */
    public function enMano(?int $cobradorId = null): array
    {
        $sql = "SELECT u.id, u.nombre,
                COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.cobrador_id = u.id AND p.anulado = 0 AND p.metodo = 'efectivo'), 0)
              - COALESCE((SELECT SUM(g.valor) FROM gastos g WHERE g.cobrador_id = u.id AND g.anulado = 0), 0)
              - COALESCE((SELECT SUM(ci.entregado) FROM cierres ci WHERE ci.cobrador_id = u.id), 0) AS en_mano,
                (SELECT MAX(ci.fecha) FROM cierres ci WHERE ci.cobrador_id = u.id) AS ultimo_cierre,
                COALESCE((SELECT SUM(ci.diferencia) FROM cierres ci WHERE ci.cobrador_id = u.id AND ci.diferencia < 0), 0) AS faltantes
            FROM usuarios u WHERE u.rol = 'cobrador'";
        $p = [];
        if ($cobradorId) {
            $sql .= ' AND u.id = ?';
            $p[] = $cobradorId;
        }
        return array_column($this->fetchAll($sql, $p), null, 'id');
    }
}
