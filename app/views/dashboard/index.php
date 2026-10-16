<?php
use App\Core\Auth;

$charts = true;
$esAdmin = Auth::isAdmin();
$cartera = (float) ($resumen['cartera'] ?? 0);
$vencido = (float) ($resumen['vencido'] ?? 0);
?>
<div class="row g-3 mb-3">
    <div class="col-xl-3 col-sm-6">
        <div class="stat stat-hero">
            <span class="stat-icon"><i class="bi bi-wallet2"></i></span>
            <div class="stat-label">Cartera por cobrar</div>
            <div class="stat-value"><?= money($cartera) ?></div>
            <div class="stat-foot"><?= (int) ($resumen['activos'] ?? 0) ?> créditos activos · capital <?= money($resumen['capital'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat">
            <span class="stat-icon tone-success"><i class="bi bi-cash-stack"></i></span>
            <div class="stat-label">Recaudo de hoy</div>
            <div class="stat-value text-success"><?= money($recaudoHoy['total'] ?? 0) ?></div>
            <div class="stat-foot"><?= (int) ($recaudoHoy['cantidad'] ?? 0) ?> pagos · por cobrar hoy <?= money($resumen['hoy'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat">
            <span class="stat-icon tone-primary"><i class="bi bi-calendar-check"></i></span>
            <div class="stat-label">Recaudo del mes</div>
            <div class="stat-value"><?= money($recaudoMes['total'] ?? 0) ?></div>
            <div class="stat-foot"><?= (int) ($recaudoMes['cantidad'] ?? 0) ?> pagos · prestado <?= money($resumen['prestado_mes'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat">
            <span class="stat-icon tone-danger"><i class="bi bi-exclamation-triangle"></i></span>
            <div class="stat-label">Vencido (mora)</div>
            <div class="stat-value text-danger"><?= money($vencido) ?></div>
            <div class="stat-foot"><?= (int) ($resumen['en_mora'] ?? 0) ?> créditos en mora · <?= porcentaje($vencido, $cartera) ?> % de la cartera</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center">
                <span>Recaudo últimos 14 días</span>
                <a href="<?= url('pagos') ?>" class="ms-auto small fw-semibold text-decoration-none">Ver pagos <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body">
                <div class="chart-box"><canvas id="chartRecaudo" aria-label="Gráfica de recaudo diario"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Clientes</div>
            <div class="card-body">
                <div class="d-flex align-items-end gap-2 mb-3">
                    <div class="display-6 fw-bold"><?= (int) ($clientes['total'] ?? 0) ?></div>
                    <div class="text-secondary mb-2">registrados</div>
                </div>
                <div class="d-grid gap-2 small">
                    <div class="d-flex justify-content-between"><span class="text-secondary">Activos</span><strong><?= (int) ($clientes['activos'] ?? 0) ?></strong></div>
                    <div class="d-flex justify-content-between"><span class="text-secondary">Créditos nuevos este mes</span><strong><?= (int) ($resumen['nuevos_mes'] ?? 0) ?></strong></div>
                    <div class="d-flex justify-content-between"><span class="text-secondary">Total recuperado (créditos activos)</span><strong class="money"><?= money($resumen['recuperado'] ?? 0) ?></strong></div>
                    <?php if ($esAdmin && ($clientes['sin_cobrador'] ?? 0) > 0): ?>
                        <a href="<?= url('clientes?cobrador_id=sin') ?>" class="alert alert-warning py-2 px-3 mb-0 mt-2 text-decoration-none small">
                            <i class="bi bi-person-exclamation"></i> <?= (int) $clientes['sin_cobrador'] ?> cliente(s) sin cobrador asignado
                        </a>
                    <?php endif; ?>
                </div>
                <hr>
                <div class="d-grid gap-2">
                    <a href="<?= url('ruta') ?>" class="btn btn-outline-primary"><i class="bi bi-signpost-split"></i> Ruta de cobro de hoy</a>
                    <a href="<?= url('clientes/crear') ?>" class="btn btn-light border"><i class="bi bi-person-plus"></i> Registrar cliente</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($esAdmin): ?>
<div class="card mb-3">
    <div class="card-header d-flex align-items-center">
        <span>Cartera por cobrador</span>
        <a href="<?= url('cobradores') ?>" class="ms-auto small fw-semibold text-decoration-none">Ver detalle <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr>
                <th>Cobrador</th><th class="text-center">Clientes</th><th class="text-center">Créditos</th>
                <th class="text-end">Cartera</th><th class="text-end">Vencido</th><th class="text-end">Recaudo hoy</th><th style="width:160px">Meta del día</th>
            </tr></thead>
            <tbody>
            <?php foreach ($cobradores as $c): if (!$c['activo'] && !$c['clientes']) continue;
                $meta = (float) $c['meta_diaria'] ?: (float) $c['por_cobrar_hoy'];
                $pct = min(100, porcentaje((float) $c['recaudo_hoy'], $meta)); ?>
                <tr>
                    <td><a href="<?= url('cobradores/ver/' . $c['id']) ?>" class="d-flex align-items-center gap-2 text-decoration-none text-body">
                        <span class="avatar"><?= e(iniciales($c['nombre'])) ?></span>
                        <span><span class="fw-semibold d-block"><?= e($c['nombre']) ?></span><span class="small text-secondary"><?= e($c['zona'] ?: 'Sin zona') ?></span></span>
                    </a></td>
                    <td class="text-center fw-semibold"><?= (int) $c['clientes'] ?></td>
                    <td class="text-center"><?= (int) $c['creditos_activos'] ?></td>
                    <td class="text-end fw-bold money"><?= money($c['cartera']) ?></td>
                    <td class="text-end money <?= $c['vencido'] > 0 ? 'text-danger' : 'text-secondary' ?>"><?= money($c['vencido']) ?></td>
                    <td class="text-end money text-success fw-semibold"><?= money($c['recaudo_hoy']) ?></td>
                    <td>
                        <div class="progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar <?= $pct >= 100 ? 'bg-success' : '' ?>" style="width: <?= $pct ?>%"></div></div>
                        <div class="small text-secondary mt-1"><?= $pct ?> % de <?= money($meta) ?></div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$cobradores): ?>
                <tr><td colspan="7"><div class="empty"><i class="bi bi-person-badge"></i>Aún no hay cobradores. <a href="<?= url('usuarios/crear?rol=cobrador') ?>">Crear el primero</a></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
$totalMano = array_sum(array_column($enMano, 'en_mano'));
$tonoProm = ['cumplida' => 'success', 'incumplida' => 'danger', 'pendiente' => 'info'];
$promPend = count(array_filter($promesas, fn ($p) => $p['estado_promesa'] === 'pendiente'));
?>
<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center"><span><i class="bi bi-wallet2 text-warning"></i> Efectivo <?= $esAdmin ? 'en la calle' : 'en tu poder' ?></span>
                <a href="<?= url('caja') ?>" class="ms-auto small fw-semibold text-decoration-none">Caja <i class="bi bi-arrow-right"></i></a></div>
            <div class="card-body">
                <div class="fs-3 fw-bold money mb-1"><?= money($totalMano) ?></div>
                <div class="small text-secondary mb-3">Efectivo cobrado que aún no se ha entregado en un cierre de caja.</div>
                <?php if ($esAdmin): foreach ($enMano as $m): if (abs((float) $m['en_mano']) < 1) continue; ?>
                    <div class="d-flex justify-content-between small py-1 border-bottom">
                        <span><?= e($m['nombre']) ?></span>
                        <span class="money fw-semibold <?= $m['en_mano'] > 500000 ? 'text-danger' : '' ?>"><?= money($m['en_mano']) ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center">
                <span><i class="bi bi-calendar-check text-primary"></i> Promesas de pago para hoy</span>
                <?php if ($promPend): ?><span class="badge badge-soft-info ms-2"><?= $promPend ?> pendiente(s)</span><?php endif; ?>
                <a href="<?= url('ruta') ?>" class="ms-auto small fw-semibold text-decoration-none">Ruta <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="list-group list-group-flush" style="max-height:260px; overflow:auto">
                <?php foreach ($promesas as $p): ?>
                    <a href="<?= url('creditos/ver/' . $p['credito_id']) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2">
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate"><?= e($p['cliente']) ?></div>
                            <div class="small text-secondary text-truncate"><?= e($p['codigo']) ?><?= $esAdmin ? ' · ' . e($p['cobrador'] ?? '') : '' ?><?= $p['nota'] ? ' · ' . e($p['nota']) : '' ?></div>
                        </div>
                        <div class="ms-auto text-end">
                            <div class="fw-bold money"><?= $p['monto_promesa'] ? money($p['monto_promesa']) : '—' ?></div>
                            <span class="badge badge-soft-<?= $tonoProm[$p['estado_promesa']] ?>"><?= ucfirst($p['estado_promesa']) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
                <?php if (!$promesas): ?><div class="empty py-4"><i class="bi bi-calendar"></i>No hay promesas de pago para hoy.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center">
                <span><i class="bi bi-exclamation-octagon text-danger"></i> Créditos en mora</span>
                <a href="<?= url('creditos?mora=1') ?>" class="ms-auto small fw-semibold text-decoration-none">Ver todos</a>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($mora as $m): $dias = dias_entre($m['mora_desde'], date('Y-m-d')); ?>
                    <a href="<?= url('creditos/ver/' . $m['id']) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate"><?= e($m['cliente']) ?></div>
                            <div class="small text-secondary"><?= e($m['codigo']) ?> · <?= e($m['cobrador'] ?? 'Sin cobrador') ?></div>
                        </div>
                        <div class="ms-auto text-end">
                            <div class="fw-bold text-danger money"><?= money($m['vencido']) ?></div>
                            <div class="small text-secondary"><?= $dias ?> día(s) · <?= (int) $m['cuotas_vencidas'] ?> cuota(s)</div>
                        </div>
                    </a>
                <?php endforeach; ?>
                <?php if (!$mora): ?>
                    <div class="empty"><i class="bi bi-emoji-smile"></i>¡Sin créditos en mora!</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center">
                <span><i class="bi bi-receipt text-success"></i> Últimos pagos</span>
                <a href="<?= url('pagos') ?>" class="ms-auto small fw-semibold text-decoration-none">Ver todos</a>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($ultimos as $p): ?>
                    <a href="<?= url('creditos/ver/' . $p['credito_id']) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                        <span class="avatar"><?= e(iniciales($p['cliente'])) ?></span>
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate"><?= e($p['cliente']) ?></div>
                            <div class="small text-secondary"><?= e($p['recibo']) ?> · <?= fecha($p['fecha'], true) ?> · <?= e($p['cobrador'] ?? '') ?></div>
                        </div>
                        <div class="ms-auto fw-bold text-success money">+<?= money($p['monto']) ?></div>
                    </a>
                <?php endforeach; ?>
                <?php if (!$ultimos): ?>
                    <div class="empty"><i class="bi bi-inbox"></i>Aún no hay pagos registrados.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script nonce="<?= csp_nonce() ?>">
(function () {
    const data = <?= js_json(['labels' => array_map(fn ($d) => date('d/m', strtotime($d)), array_keys($grafica)), 'values' => array_values($grafica)]) ?>;
    const ctx = document.getElementById('chartRecaudo');
    if (!ctx || !window.Chart) return;
    new Chart(ctx, {
        type: 'bar',
        data: { labels: data.labels, datasets: [{ label: 'Recaudo', data: data.values, backgroundColor: '#6366f1', hoverBackgroundColor: '#4338ca', borderRadius: 6, maxBarThickness: 34 }] },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => apexMoney(c.raw) } } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#eef2f7' }, border: { display: false }, ticks: { callback: (v) => '$' + (v >= 1e6 ? (v / 1e6) + 'M' : v >= 1e3 ? (v / 1e3) + 'k' : v) } },
                x: { grid: { display: false }, border: { display: false } }
            }
        }
    });
})();
</script>
<?php $scripts = ob_get_clean(); ?>
