<div class="row g-3 mb-3">
    <div class="col-xl-3 col-sm-6"><div class="stat stat-hero"><span class="stat-icon"><i class="bi bi-wallet2"></i></span><div class="stat-label">Cartera total en la calle</div><div class="stat-value"><?= money($tot['cartera']) ?></div><div class="stat-foot">capital prestado <?= money($tot['capital']) ?></div></div></div>
    <div class="col-xl-3 col-sm-6"><div class="stat"><span class="stat-icon tone-primary"><i class="bi bi-people"></i></span><div class="stat-label">Clientes asignados</div><div class="stat-value"><?= (int) $tot['clientes'] ?></div><div class="stat-foot"><?= (int) $tot['creditos_activos'] ?> créditos activos<?= $sinCobrador ? ' · <a href="' . url('clientes?cobrador_id=sin') . '">' . $sinCobrador . ' sin asignar</a>' : '' ?></div></div></div>
    <div class="col-xl-3 col-sm-6"><div class="stat"><span class="stat-icon tone-success"><i class="bi bi-cash-stack"></i></span><div class="stat-label">Recaudo hoy</div><div class="stat-value text-success"><?= money($tot['recaudo_hoy']) ?></div><div class="stat-foot">mes: <?= money($tot['recaudo_mes']) ?></div></div></div>
    <div class="col-xl-3 col-sm-6"><div class="stat"><span class="stat-icon tone-danger"><i class="bi bi-exclamation-triangle"></i></span><div class="stat-label">Vencido</div><div class="stat-value text-danger"><?= money($tot['vencido']) ?></div><div class="stat-foot"><?= porcentaje($tot['vencido'], $tot['cartera']) ?> % de la cartera</div></div></div>
</div>

<div class="d-flex align-items-center mb-3">
    <h2 class="h6 fw-bold mb-0"><?= count($cartera) ?> cobrador(es)</h2>
    <a href="<?= url('reportes/exportar/cartera') ?>" class="btn btn-sm btn-light border ms-auto me-2"><i class="bi bi-file-earmark-excel"></i> Exportar</a>
    <a href="<?= url('usuarios/crear?rol=cobrador') ?>" class="btn btn-sm btn-primary"><i class="bi bi-person-plus"></i> Nuevo cobrador</a>
</div>

<div class="row g-3">
    <?php foreach ($cartera as $c):
        $meta = (float) $c['meta_diaria'] ?: (float) $c['por_cobrar_hoy'];
        $pct = min(100, porcentaje((float) $c['recaudo_hoy'], $meta));
        $part = porcentaje((float) $c['cartera'], (float) $tot['cartera']); ?>
        <div class="col-xxl-4 col-lg-6">
            <a href="<?= url('cobradores/ver/' . $c['id']) ?>" class="card cob-card h-100 text-decoration-none text-body <?= $c['activo'] ? '' : 'opacity-75' ?>">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="avatar avatar-lg"><?= e(iniciales($c['nombre'])) ?></span>
                        <div class="min-w-0">
                            <div class="fw-bold fs-6"><?= e($c['nombre']) ?> <?= $c['activo'] ? '' : badge('inactivo') ?></div>
                            <div class="small text-secondary"><i class="bi bi-geo"></i> <?= e($c['zona'] ?: 'Sin zona') ?> · <i class="bi bi-telephone"></i> <?= e($c['telefono'] ?: '—') ?></div>
                        </div>
                        <div class="ms-auto text-end">
                            <div class="small text-secondary">Cartera</div>
                            <div class="fw-bold fs-5 money"><?= money($c['cartera']) ?></div>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4 cob-metric">Clientes<strong><?= (int) $c['clientes'] ?></strong></div>
                        <div class="col-4 cob-metric">Créditos activos<strong><?= (int) $c['creditos_activos'] ?></strong></div>
                        <div class="col-4 cob-metric">Capital<strong class="money"><?= money($c['capital']) ?></strong></div>
                        <div class="col-4 cob-metric">Vencido<strong class="money <?= $c['vencido'] > 0 ? 'text-danger' : '' ?>"><?= money($c['vencido']) ?></strong></div>
                        <div class="col-4 cob-metric">En mora<strong><?= (int) $c['creditos_mora'] ?></strong></div>
                        <div class="col-4 cob-metric">Recaudo mes<strong class="money text-success"><?= money($c['recaudo_mes']) ?></strong></div>
                        <?php $m = $enMano[$c['id']] ?? null; ?>
                        <div class="col-12 cob-metric d-flex justify-content-between align-items-center px-2 py-1 rounded-2 tone-warning mt-2">
                            <span><i class="bi bi-wallet2"></i> Efectivo en mano (sin entregar)</span>
                            <strong class="d-inline money"><?= money($m['en_mano'] ?? 0) ?></strong>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-secondary">Hoy: <strong class="text-success"><?= money($c['recaudo_hoy']) ?></strong> de <?= money($meta) ?></span>
                        <span class="fw-semibold"><?= $pct ?> %</span>
                    </div>
                    <div class="progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar <?= $pct >= 100 ? 'bg-success' : '' ?>" style="width: <?= $pct ?>%"></div></div>
                    <div class="small text-secondary mt-2"><?= $part ?> % de la cartera total · último acceso <?= $c['ultimo_acceso'] ? fecha($c['ultimo_acceso'], true) : 'nunca' ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
    <?php if (!$cartera): ?>
        <div class="col-12"><div class="card"><div class="empty"><i class="bi bi-person-badge"></i>No hay cobradores registrados. <a href="<?= url('usuarios/crear?rol=cobrador') ?>">Crea el primero</a>.</div></div></div>
    <?php endif; ?>
</div>
