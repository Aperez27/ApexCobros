<?php

namespace App\Models;

use App\Core\Model;

/**
 * Indicadores financieros del negocio.
 */
class Reporte extends Model
{
    protected string $table = 'creditos';

    /**
     * Rentabilidad por mes.
     * Cada pago se divide en capital e interés en la misma proporción del crédito
     * (interés / total). Así la ganancia se reconoce a medida que se cobra, no cuando se presta.
     * Las renovaciones no cuentan como recaudo porque no entra dinero.
     */
    public function rentabilidad(string $desde, string $hasta, ?int $cobradorId = null): array
    {
        $wc = $cobradorId ? ' AND c.cobrador_id = ' . (int) $cobradorId : '';
        $wp = $cobradorId ? ' AND p.cobrador_id = ' . (int) $cobradorId : '';
        $wg = $cobradorId ? ' AND g.cobrador_id = ' . (int) $cobradorId : '';
        $rango = [$desde, $hasta];

        $colocado = $this->fetchAll("SELECT DATE_FORMAT(cr.fecha_inicio, '%Y-%m') AS mes, COUNT(*) AS creditos,
                SUM(cr.monto) AS capital, SUM(COALESCE(cr.neto_entregado, cr.monto)) AS neto, SUM(cr.total - cr.monto) AS interes_pactado,
                SUM(cr.renovado_de IS NOT NULL) AS renovaciones
            FROM creditos cr JOIN clientes c ON c.id = cr.cliente_id
            WHERE cr.estado <> 'anulado' AND cr.fecha_inicio BETWEEN ? AND ?{$wc} GROUP BY mes", $rango);
        $cobrado = $this->fetchAll("SELECT DATE_FORMAT(p.fecha, '%Y-%m') AS mes, SUM(p.monto) AS recaudo,
                SUM(p.monto * (cr.total - cr.monto) / cr.total) AS interes_cobrado
            FROM pagos p JOIN creditos cr ON cr.id = p.credito_id
            WHERE p.anulado = 0 AND p.metodo <> 'renovacion' AND p.fecha >= ? AND p.fecha < DATE_ADD(?, INTERVAL 1 DAY){$wp} GROUP BY mes", $rango);
        $gastos = $this->fetchAll("SELECT DATE_FORMAT(g.fecha, '%Y-%m') AS mes, SUM(g.valor) AS gastos
            FROM gastos g WHERE g.anulado = 0 AND g.fecha BETWEEN ? AND ?{$wg} GROUP BY mes", $rango);

        $meses = [];
        $d = new \DateTime(substr($desde, 0, 7) . '-01');
        $fin = new \DateTime(substr($hasta, 0, 7) . '-01');
        $nombres = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        while ($d <= $fin) {
            $k = $d->format('Y-m');
            $meses[$k] = ['mes' => $k, 'mes_txt' => $nombres[(int) $d->format('n') - 1] . ' ' . $d->format('Y'),
                'creditos' => 0, 'renovaciones' => 0, 'capital' => 0.0, 'neto' => 0.0, 'interes_pactado' => 0.0,
                'recaudo' => 0.0, 'interes_cobrado' => 0.0, 'gastos' => 0.0];
            $d->modify('+1 month');
        }
        foreach ([$colocado, $cobrado, $gastos] as $set) {
            foreach ($set as $r) {
                if (isset($meses[$r['mes']])) {
                    foreach ($r as $k => $v) {
                        if ($k !== 'mes') {
                            $meses[$r['mes']][$k] = (float) $v;
                        }
                    }
                }
            }
        }
        foreach ($meses as &$m) {
            $m['capital_recuperado'] = $m['recaudo'] - $m['interes_cobrado'];
            $m['utilidad'] = $m['interes_cobrado'] - $m['gastos'];
            $m['margen'] = $m['recaudo'] > 0 ? $m['utilidad'] / $m['recaudo'] : null;
        }
        return array_values($meses);
    }
}
