<?php
$hoy = date('Y-m-d');
$vigentes = array_filter($pagos, fn ($p) => !$p['anulado']);
?>
<h1>ESTADO DE CUENTA · CRÉDITO <?= e($cr['codigo']) ?></h1>
<div class="sub">Corte al <?= fecha_larga($hoy) ?> · Estado del crédito: <strong><?= e(ucfirst($cr['estado'])) ?></strong></div>

<div class="grid">
    <div><span>Cliente</span><strong><?= e($cr['cliente']) ?></strong></div>
    <div><span>Cédula</span><strong><?= e($cr['cedula']) ?></strong></div>
    <div><span>Teléfonos</span><strong><?= e($cr['telefono1']) ?><?= $cr['telefono2'] ? ' · ' . e($cr['telefono2']) : '' ?></strong></div>
    <div><span>Dirección</span><strong><?= e($cr['direccion']) ?><?= $cr['barrio'] ? ', ' . e($cr['barrio']) : '' ?></strong></div>
    <div><span>Fecha del préstamo</span><strong><?= fecha($cr['fecha_inicio']) ?></strong></div>
    <div><span>Cobrador</span><strong><?= e($cr['cobrador'] ?? 'Sin asignar') ?></strong></div>
    <div><span>Monto prestado</span><strong><?= money($cr['monto']) ?></strong></div>
    <div><span>Interés</span><strong><?= (float) $cr['interes'] ?> % (<?= money($cr['total'] - $cr['monto']) ?>)</strong></div>
    <div><span>Plan</span><strong><?= (int) $cr['num_cuotas'] ?> cuotas <?= frecuencia_plural($cr['frecuencia']) ?> de <?= money($cr['valor_cuota']) ?></strong></div>
    <div><span>Vigencia</span><strong><?= fecha($cr['fecha_primera_cuota']) ?> a <?= fecha($cr['fecha_fin']) ?></strong></div>
    <?php if ($cr['renovado_de_codigo']): ?><div><span>Renovación de</span><strong><?= e($cr['renovado_de_codigo']) ?> (saldo <?= money($cr['saldo_renovado']) ?>)</strong></div><?php endif; ?>
</div>

<div class="kpis">
    <div class="kpi"><small>Total a pagar</small><b><?= money($cr['total']) ?></b></div>
    <div class="kpi ok"><small>Pagado</small><b><?= money($cr['total_pagado']) ?></b></div>
    <div class="kpi <?= $cr['saldo'] > 0 ? 'warn' : 'ok' ?>"><small>Saldo</small><b><?= money($cr['saldo']) ?></b></div>
    <div class="kpi <?= $cr['vencido'] > 0 ? 'bad' : 'ok' ?>"><small>Vencido</small><b><?= money($cr['vencido']) ?></b></div>
</div>

<h2>Plan de cuotas</h2>
<table>
    <thead><tr><th>#</th><th>Vence</th><th class="r">Valor</th><th class="r">Abonado</th><th class="r">Pendiente</th><th>Pagada el</th><th class="c">Estado</th></tr></thead>
    <tbody>
    <?php foreach ($cuotas as $q):
        $vencida = $q['estado'] !== 'pagada' && $q['fecha_vencimiento'] < $hoy && $cr['estado'] === 'activo';
        [$txt, $cls] = $vencida ? ['Vencida', 't-bad'] : (['pagada' => ['Pagada', 't-ok'], 'parcial' => ['Parcial', 't-info']][$q['estado']] ?? ['Pendiente', 't-muted']); ?>
        <tr>
            <td><?= (int) $q['numero'] ?></td>
            <td><?= fecha($q['fecha_vencimiento']) ?></td>
            <td class="r"><?= money($q['valor']) ?></td>
            <td class="r"><?= money($q['abonado']) ?></td>
            <td class="r"><?= money($q['valor'] - $q['abonado']) ?></td>
            <td><?= fecha($q['fecha_pago']) ?></td>
            <td class="c"><span class="tag <?= $cls ?>"><?= $txt ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="2">Total</td><td class="r"><?= money($cr['total']) ?></td><td class="r"><?= money(array_sum(array_column($cuotas, 'abonado'))) ?></td><td class="r"><?= money($cr['saldo']) ?></td><td colspan="2"></td></tr></tfoot>
</table>

<h2>Pagos recibidos</h2>
<table>
    <thead><tr><th>Recibo</th><th>Fecha</th><th>Método</th><th>Recibió</th><th class="r">Valor</th></tr></thead>
    <tbody>
    <?php foreach ($pagos as $p): ?>
        <tr class="<?= $p['anulado'] ? 'tachado' : '' ?>">
            <td><?= e($p['recibo']) ?></td>
            <td><?= fecha($p['fecha'], true) ?></td>
            <td><?= e(metodo_label($p['metodo'])) ?><?= $p['anulado'] ? ' (anulado)' : '' ?></td>
            <td><?= e($p['cobrador'] ?? '') ?></td>
            <td class="r"><?= money($p['monto']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$pagos): ?><tr><td colspan="5" class="c">Aún no hay pagos registrados.</td></tr><?php endif; ?>
    </tbody>
    <tfoot><tr><td colspan="4">Total pagado</td><td class="r"><?= money(array_sum(array_column($vigentes, 'monto'))) ?></td></tr></tfoot>
</table>
