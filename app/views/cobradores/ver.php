<?php
use App\Core\Auth;

$charts = true;
$esAdmin = Auth::isAdmin();
$meta = (float) $r['meta_diaria'] ?: (float) $r['por_cobrar_hoy'];
$pct = min(100, porcentaje((float) $r['recaudo_hoy'], $meta));
?>
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <span class="avatar avatar-lg"><?= e(iniciales($u['nombre'])) ?></span>
        <div>
            <h2 class="h5 fw-bold mb-0"><?= e($u['nombre']) ?> <?= $u['activo'] ? '' : badge('inactivo') ?></h2>
            <div class="small text-secondary">
                C.C. <?= e($u['cedula'] ?: '—') ?> · <i class="bi bi-telephone"></i> <?= e($u['telefono'] ?: '—') ?> · <i class="bi bi-geo"></i> <?= e($u['zona'] ?: 'Sin zona') ?> · usuario <strong><?= e($u['usuario']) ?></strong>
            </div>
        </div>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <a href="<?= url('ruta' . ($esAdmin ? '?cobrador_id=' . $u['id'] : '')) ?>" class="btn btn-sm btn-light border"><i class="bi bi-signpost-split"></i> Ruta de hoy</a>
            <?php if ($esAdmin): ?>
                <a href="<?= url('clientes/crear?cobrador=' . $u['id']) ?>" class="btn btn-sm btn-light border"><i class="bi bi-person-plus"></i> Cliente</a>
                <a href="<?= url('usuarios/editar/' . $u['id']) ?>" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i> Editar</a>
                <?php if ($r['clientes'] > 0): ?>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalTraslado"><i class="bi bi-arrow-left-right"></i> Trasladar cartera</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-3 col-sm-6"><div class="stat stat-hero"><span class="stat-icon"><i class="bi bi-wallet2"></i></span><div class="stat-label">Dinero en cartera</div><div class="stat-value"><?= money($r['cartera']) ?></div><div class="stat-foot">de <?= money($r['total_creditos']) ?> en créditos activos</div></div></div>
    <div class="col-xl-3 col-sm-6"><div class="stat"><span class="stat-icon tone-primary"><i class="bi bi-people"></i></span><div class="stat-label">Clientes</div><div class="stat-value"><?= (int) $r['clientes'] ?></div><div class="stat-foot"><?= (int) $r['creditos_activos'] ?> créditos activos · capital <?= money($r['capital']) ?></div></div></div>
    <div class="col-xl-3 col-sm-6"><div class="stat"><span class="stat-icon tone-danger"><i class="bi bi-exclamation-triangle"></i></span><div class="stat-label">Vencido</div><div class="stat-value text-danger"><?= money($r['vencido']) ?></div><div class="stat-foot"><?= (int) $r['creditos_mora'] ?> créditos en mora</div></div></div>
    <div class="col-xl-3 col-sm-6"><div class="stat"><span class="stat-icon tone-success"><i class="bi bi-cash-stack"></i></span><div class="stat-label">Recaudo hoy</div><div class="stat-value text-success"><?= money($r['recaudo_hoy']) ?></div>
        <div class="progress mt-2" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div></div>
        <div class="stat-foot"><?= $pct ?> % de <?= money($meta) ?> · mes <?= money($r['recaudo_mes']) ?></div></div></div>
</div>

<div class="alert alert-warning d-flex flex-wrap align-items-center gap-3">
    <i class="bi bi-wallet2 fs-4"></i>
    <div><div class="fw-bold">Efectivo en mano: <?= money($enMano['en_mano']) ?></div>
        <div class="small">Último cierre de caja: <?= $enMano['ultimo_cierre'] ? fecha($enMano['ultimo_cierre']) : 'nunca' ?><?= $enMano['faltantes'] < 0 ? ' · faltantes acumulados ' . money(abs($enMano['faltantes'])) : '' ?></div></div>
    <a href="<?= url('caja') ?>" class="btn btn-sm btn-warning ms-auto"><i class="bi bi-clipboard-check"></i> Ir a caja</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Recaudo últimos 14 días</div>
            <div class="card-body"><div class="chart-box"><canvas id="chartCob" aria-label="Recaudo diario del cobrador"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-exclamation-octagon text-danger"></i> Créditos en mora (<?= count($mora) ?>)</div>
            <div class="list-group list-group-flush" style="max-height: 300px; overflow:auto">
                <?php foreach ($mora as $m): ?>
                    <a href="<?= url('creditos/ver/' . $m['id']) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                        <div class="min-w-0"><div class="fw-semibold text-truncate"><?= e($m['cliente']) ?></div><div class="small text-secondary"><?= e($m['codigo']) ?> · desde <?= fecha($m['mora_desde']) ?></div></div>
                        <div class="ms-auto fw-bold text-danger money"><?= money($m['vencido']) ?></div>
                    </a>
                <?php endforeach; ?>
                <?php if (!$mora): ?><div class="empty"><i class="bi bi-emoji-smile"></i>Sin mora.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <span><i class="bi bi-people text-primary"></i> Clientes de la cartera (<?= count($clientes) ?>)</span>
        <?php if ($esAdmin): ?><a href="<?= url('reportes/exportar/clientes?cobrador_id=' . $u['id']) ?>" class="btn btn-sm btn-light border ms-auto"><i class="bi bi-file-earmark-excel"></i> Exportar</a><?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Cliente</th><th class="text-center">Calif.</th><th>Teléfono</th><th>Dirección</th><th class="text-center">Créditos</th><th class="text-end">Saldo</th><th class="text-end">Vencido</th></tr></thead>
            <tbody>
            <?php foreach ($clientes as $c): ?>
                <tr class="<?= $c['estado'] !== 'activo' ? 'row-muted' : '' ?>">
                    <td><a href="<?= url('clientes/ver/' . $c['id']) ?>" class="fw-semibold text-body text-decoration-none"><?= e($c['nombres'] . ' ' . $c['apellidos']) ?></a><div class="small text-secondary">C.C. <?= e($c['cedula']) ?></div></td>
                    <td class="text-center"><?= calificacion_badge($calificaciones[$c['id']] ?? null) ?></td>
                    <td class="small"><?= e($c['telefono1']) ?></td>
                    <td class="small"><?= e($c['direccion']) ?><?= $c['barrio'] ? ', ' . e($c['barrio']) : '' ?></td>
                    <td class="text-center"><?= (int) $c['creditos_activos'] ?></td>
                    <td class="text-end money fw-semibold"><?= money($c['saldo']) ?></td>
                    <td class="text-end money <?= $c['vencido'] > 0 ? 'text-danger fw-semibold' : 'text-secondary' ?>"><?= money($c['vencido']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$clientes): ?><tr><td colspan="7"><div class="empty"><i class="bi bi-people"></i>Sin clientes asignados.</div></td></tr><?php endif; ?>
            </tbody>
            <?php if ($clientes): ?>
            <tfoot><tr class="fw-bold"><td colspan="5" class="text-end">Totales</td><td class="text-end money"><?= money(array_sum(array_column($clientes, 'saldo'))) ?></td><td class="text-end money text-danger"><?= money(array_sum(array_column($clientes, 'vencido'))) ?></td></tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php if ($esAdmin && $r['clientes'] > 0): ?>
<div class="modal fade" id="modalTraslado" tabindex="-1" aria-labelledby="modalTrasladoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= url('cobradores/trasladar/' . $u['id']) ?>">
            <?= csrf_field() ?>
            <div class="modal-header"><h2 class="modal-title h5 fw-bold" id="modalTrasladoLabel">Trasladar cartera completa</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <p class="small text-secondary">Los <strong><?= (int) $r['clientes'] ?> clientes</strong> de <?= e($u['nombre']) ?> (<?= money($r['cartera']) ?> en cartera) pasarán al cobrador que elijas. Queda registrado en el historial de cada cliente. Para mover solo algunos, usa la selección múltiple en <a href="<?= url('clientes?cobrador_id=' . $u['id']) ?>">Clientes</a>.</p>
                <label class="form-label" for="destino_id">Nuevo cobrador</label>
                <select class="form-select mb-3" id="destino_id" name="destino_id" required>
                    <option value="">Selecciona…</option>
                    <?php foreach ($cobradores as $cb): ?><option value="<?= $cb['id'] ?>"><?= e($cb['nombre']) ?></option><?php endforeach; ?>
                </select>
                <label class="form-label" for="motivoTraslado">Motivo</label>
                <input class="form-control" id="motivoTraslado" name="motivo" maxlength="255" placeholder="Ej: vacaciones, retiro, reorganización de rutas">
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Trasladar</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php ob_start(); ?>
<script nonce="<?= csp_nonce() ?>">
(function () {
    const data = <?= js_json(['labels' => array_map(fn ($d) => date('d/m', strtotime($d)), array_keys($grafica)), 'values' => array_values($grafica)]) ?>;
    const ctx = document.getElementById('chartCob');
    if (!ctx || !window.Chart) return;
    new Chart(ctx, {
        type: 'line',
        data: { labels: data.labels, datasets: [{ data: data.values, borderColor: '#4338ca', backgroundColor: 'rgba(99,102,241,.12)', fill: true, tension: .35, pointRadius: 3 }] },
        options: { maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => apexMoney(c.raw) } } },
            scales: { y: { beginAtZero: true, grid: { color: '#eef2f7' }, border: { display: false }, ticks: { callback: (v) => '$' + (v >= 1e6 ? (v / 1e6) + 'M' : v >= 1e3 ? (v / 1e3) + 'k' : v) } }, x: { grid: { display: false } } } }
    });
})();
</script>
<?php $scripts = ob_get_clean(); ?>
