<?php
$qs = http_build_query(array_filter(['desde' => $desde, 'hasta' => $hasta, 'cobrador_id' => $cobradorId]));
$tc = ['clientes' => 0, 'creditos_activos' => 0, 'capital' => 0, 'cartera' => 0, 'vencido' => 0];
foreach ($cartera as $c) {
    foreach ($tc as $k => $_) {
        $tc[$k] += (float) $c[$k];
    }
}
?>
<form class="filters row g-2 align-items-end" method="get" action="<?= url('reportes') ?>">
    <div class="col-md-3 col-6"><label class="form-label" for="desde">Desde</label><input type="date" class="form-control" id="desde" name="desde" value="<?= e($desde) ?>"></div>
    <div class="col-md-3 col-6"><label class="form-label" for="hasta">Hasta</label><input type="date" class="form-control" id="hasta" name="hasta" value="<?= e($hasta) ?>"></div>
    <div class="col-md-4">
        <label class="form-label" for="cobrador_id">Cobrador</label>
        <select class="form-select" id="cobrador_id" name="cobrador_id">
            <option value="">Todos</option>
            <?php foreach ($cobradores as $c): ?><option value="<?= $c['id'] ?>" <?= $cobradorId == $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Generar</button></div>
</form>

<div class="row g-3 mb-3">
    <?php foreach (App\Controllers\ReportesController::TIPOS as $tipo => [$titulo, $icon, $desc]): $destacado = $tipo === 'completo'; ?>
        <div class="col-xl-3 col-md-6">
            <a href="<?= url('reportes/exportar/' . $tipo . ($qs ? '?' . $qs : '')) ?>" class="card cob-card h-100 text-decoration-none text-body <?= $destacado ? 'border-primary' : '' ?>">
                <div class="card-body d-flex gap-3">
                    <span class="stat-icon position-static <?= $destacado ? 'tone-primary' : 'tone-success' ?> flex-shrink-0"><i class="bi bi-<?= $icon ?>"></i></span>
                    <div><div class="fw-bold"><?= e($titulo) ?></div><div class="small text-secondary"><?= e($desc) ?></div>
                    <div class="small fw-semibold <?= $destacado ? 'text-primary' : 'text-success' ?> mt-1"><i class="bi bi-file-earmark-excel"></i> Descargar Excel</div></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<?php
$r = ['capital' => 0, 'neto' => 0, 'recaudo' => 0, 'interes_cobrado' => 0, 'gastos' => 0, 'utilidad' => 0, 'capital_recuperado' => 0, 'creditos' => 0];
foreach ($rentabilidad as $m) {
    foreach ($r as $k => $_) {
        $r[$k] += $m[$k];
    }
}
?>
<div class="card mb-3">
    <div class="card-header d-flex align-items-center">
        <span><i class="bi bi-graph-up-arrow text-success"></i> Rentabilidad · <?= fecha($desde) ?> a <?= fecha($hasta) ?></span>
        <span class="ms-auto small text-secondary fw-normal d-none d-md-inline">Cada pago se divide en capital e interés según su crédito</span>
    </div>
    <div class="card-body pb-0">
        <div class="row g-2 text-center mb-3">
            <div class="col-6 col-md"><div class="p-2 rounded-3 tone-primary"><div class="small">Capital colocado</div><div class="fw-bold money"><?= money($r['capital']) ?></div></div></div>
            <div class="col-6 col-md"><div class="p-2 rounded-3 tone-info"><div class="small">Recaudo</div><div class="fw-bold money"><?= money($r['recaudo']) ?></div></div></div>
            <div class="col-6 col-md"><div class="p-2 rounded-3 tone-success"><div class="small">Interés ganado</div><div class="fw-bold money"><?= money($r['interes_cobrado']) ?></div></div></div>
            <div class="col-6 col-md"><div class="p-2 rounded-3 tone-warning"><div class="small">Gastos de ruta</div><div class="fw-bold money"><?= money($r['gastos']) ?></div></div></div>
            <div class="col-12 col-md"><div class="p-2 rounded-3 <?= $r['utilidad'] >= 0 ? 'tone-success' : 'tone-danger' ?>"><div class="small">Utilidad</div><div class="fw-bold money fs-5"><?= money($r['utilidad']) ?></div></div></div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Mes</th><th class="text-center">Créditos</th><th class="text-end">Capital colocado</th><th class="text-end">Efectivo entregado</th><th class="text-end">Recaudo</th><th class="text-end">Capital recuperado</th><th class="text-end">Interés ganado</th><th class="text-end">Gastos</th><th class="text-end">Utilidad</th></tr></thead>
            <tbody>
            <?php foreach ($rentabilidad as $m): ?>
                <tr>
                    <td class="fw-semibold"><?= e($m['mes_txt']) ?></td>
                    <td class="text-center"><?= (int) $m['creditos'] ?><?= $m['renovaciones'] ? ' <span class="small text-secondary">(' . (int) $m['renovaciones'] . ' renov.)</span>' : '' ?></td>
                    <td class="text-end money"><?= money($m['capital']) ?></td>
                    <td class="text-end money"><?= money($m['neto']) ?></td>
                    <td class="text-end money"><?= money($m['recaudo']) ?></td>
                    <td class="text-end money"><?= money($m['capital_recuperado']) ?></td>
                    <td class="text-end money text-success"><?= money($m['interes_cobrado']) ?></td>
                    <td class="text-end money text-warning"><?= money($m['gastos']) ?></td>
                    <td class="text-end money fw-bold <?= $m['utilidad'] < 0 ? 'text-danger' : '' ?>"><?= money($m['utilidad']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($efectividad): ?>
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-telephone-outbound text-primary"></i> Gestión de cobro y cumplimiento de promesas</div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Cobrador</th><th class="text-center">Gestiones</th><th class="text-center">Promesas</th><th class="text-center">Cumplidas</th><th class="text-center">Incumplidas</th><th class="text-center">Pendientes</th><th style="width:22%">Cumplimiento</th></tr></thead>
            <tbody>
            <?php foreach ($efectividad as $ef): $p = $ef['efectividad'] !== null ? round($ef['efectividad'] * 100) : null; ?>
                <tr>
                    <td class="fw-semibold"><?= e($ef['cobrador']) ?></td>
                    <td class="text-center"><?= $ef['gestiones'] ?></td>
                    <td class="text-center"><?= $ef['promesas'] ?></td>
                    <td class="text-center text-success fw-semibold"><?= $ef['cumplidas'] ?></td>
                    <td class="text-center text-danger fw-semibold"><?= $ef['incumplidas'] ?></td>
                    <td class="text-center"><?= $ef['pendientes'] ?></td>
                    <td><?php if ($p !== null): ?><div class="d-flex align-items-center gap-2"><div class="progress flex-fill"><div class="progress-bar <?= $p >= 70 ? 'bg-success' : ($p >= 40 ? 'bg-warning' : 'bg-danger') ?>" style="width: <?= $p ?>%"></div></div><span class="small"><?= $p ?> %</span></div><?php else: ?><span class="small text-secondary">Sin promesas vencidas</span><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header d-flex align-items-center">
        <span><i class="bi bi-clipboard-check text-primary"></i> Liquidación de recaudo · <?= fecha($desde) ?> a <?= fecha($hasta) ?></span>
        <button type="button" data-print class="btn btn-sm btn-light border ms-auto d-print-none"><i class="bi bi-printer"></i> Imprimir</button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Cobrador</th><th class="text-center">Pagos</th><th class="text-end">Efectivo</th><th class="text-end">Digital / transferencia</th><th class="text-end">Total</th><th style="width:22%">Participación</th></tr></thead>
            <tbody>
            <?php foreach ($liquidacion as $l): $p = porcentaje((float) $l['total'], (float) $totales['total']); ?>
                <tr>
                    <td class="fw-semibold"><?= e($l['cobrador']) ?></td>
                    <td class="text-center"><?= (int) $l['pagos'] ?></td>
                    <td class="text-end money"><?= money($l['efectivo']) ?></td>
                    <td class="text-end money"><?= money($l['digital']) ?></td>
                    <td class="text-end money fw-bold"><?= money($l['total']) ?></td>
                    <td><div class="d-flex align-items-center gap-2"><div class="progress flex-fill"><div class="progress-bar" style="width: <?= $p ?>%"></div></div><span class="small"><?= $p ?> %</span></div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$liquidacion): ?><tr><td colspan="6"><div class="empty"><i class="bi bi-inbox"></i>Sin pagos en el periodo.</div></td></tr><?php endif; ?>
            </tbody>
            <?php if ($liquidacion): ?>
            <tfoot><tr class="fw-bold"><td>Total</td><td class="text-center"><?= (int) $totales['cantidad'] ?></td>
                <td class="text-end money"><?= money(array_sum(array_column($liquidacion, 'efectivo'))) ?></td>
                <td class="text-end money"><?= money(array_sum(array_column($liquidacion, 'digital'))) ?></td>
                <td class="text-end money"><?= money($totales['total']) ?></td><td></td></tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-wallet2 text-primary"></i> Estado actual de cartera por cobrador</div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Cobrador</th><th class="text-center">Clientes</th><th class="text-center">Créditos</th><th class="text-end">Capital</th><th class="text-end">Saldo por cobrar</th><th class="text-end">Vencido</th><th class="text-end">% mora</th></tr></thead>
            <tbody>
            <?php foreach ($cartera as $c): ?>
                <tr>
                    <td><a href="<?= url('cobradores/ver/' . $c['id']) ?>" class="fw-semibold text-decoration-none"><?= e($c['nombre']) ?></a></td>
                    <td class="text-center"><?= (int) $c['clientes'] ?></td>
                    <td class="text-center"><?= (int) $c['creditos_activos'] ?></td>
                    <td class="text-end money"><?= money($c['capital']) ?></td>
                    <td class="text-end money fw-bold"><?= money($c['cartera']) ?></td>
                    <td class="text-end money text-danger"><?= money($c['vencido']) ?></td>
                    <td class="text-end"><?= porcentaje((float) $c['vencido'], (float) $c['cartera']) ?> %</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr class="fw-bold"><td>Total</td><td class="text-center"><?= (int) $tc['clientes'] ?></td><td class="text-center"><?= (int) $tc['creditos_activos'] ?></td>
                <td class="text-end money"><?= money($tc['capital']) ?></td><td class="text-end money"><?= money($tc['cartera']) ?></td>
                <td class="text-end money text-danger"><?= money($tc['vencido']) ?></td><td class="text-end"><?= porcentaje($tc['vencido'], $tc['cartera']) ?> %</td></tr></tfoot>
        </table>
    </div>
</div>
