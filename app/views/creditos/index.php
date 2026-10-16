<?php
use App\Core\Auth;

$esAdmin = Auth::isAdmin();
?>
<form class="filters row g-2 align-items-end" method="get" action="<?= url('creditos') ?>">
    <div class="col-lg-4 col-md-6">
        <label class="form-label" for="q">Buscar</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input class="form-control" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Código, cédula o nombre">
        </div>
    </div>
    <div class="col-lg-2 col-md-3 col-6">
        <label class="form-label" for="estado">Estado</label>
        <select class="form-select" id="estado" name="estado">
            <option value="">Todos</option>
            <?php foreach (['activo' => 'Activos', 'pagado' => 'Pagados', 'anulado' => 'Anulados'] as $k => $v): ?>
                <option value="<?= $k ?>" <?= $f['estado'] === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($esAdmin): ?>
    <div class="col-lg-2 col-md-3 col-6">
        <label class="form-label" for="cobrador_id">Cobrador</label>
        <select class="form-select" id="cobrador_id" name="cobrador_id">
            <option value="">Todos</option>
            <?php foreach ($cobradores as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $f['cobrador_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="col-lg-2 col-md-6 col-6">
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="mora" name="mora" value="1" <?= $f['mora'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="mora">Solo en mora</label>
        </div>
    </div>
    <div class="col-lg-2 col-md-6 d-flex gap-2">
        <button class="btn btn-primary flex-fill"><i class="bi bi-funnel"></i> Filtrar</button>
        <a href="<?= url('creditos') ?>" class="btn btn-light border" aria-label="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
    </div>
</form>

<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <span><?= count($creditos) ?> crédito(s)</span>
        <span class="badge badge-soft-primary">Saldo <?= money(array_sum(array_column($creditos, 'saldo'))) ?></span>
        <?php if ($v = array_sum(array_column($creditos, 'vencido'))): ?><span class="badge badge-soft-danger">Vencido <?= money($v) ?></span><?php endif; ?>
        <?php if ($esAdmin): ?><a href="<?= url('creditos/crear') ?>" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg"></i> Nuevo crédito</a><?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr>
                <th>Crédito</th><th>Cliente</th><?php if ($esAdmin): ?><th>Cobrador</th><?php endif; ?>
                <th class="text-end">Prestado</th><th class="text-end">Total</th><th>Plan</th><th class="text-end">Saldo</th><th>Estado</th>
            </tr></thead>
            <tbody>
            <?php foreach ($creditos as $cr): $pct = porcentaje((float) $cr['total_pagado'], (float) $cr['total']); ?>
                <tr class="<?= $cr['estado'] === 'anulado' ? 'row-muted' : '' ?>">
                    <td><a href="<?= url('creditos/ver/' . $cr['id']) ?>" class="fw-bold text-decoration-none"><?= e($cr['codigo']) ?></a>
                        <div class="small text-secondary"><?= fecha($cr['fecha_inicio']) ?></div></td>
                    <td><a href="<?= url('clientes/ver/' . $cr['cliente_id']) ?>" class="text-body text-decoration-none fw-semibold"><?= e($cr['cliente']) ?></a>
                        <div class="small text-secondary">C.C. <?= e($cr['cedula']) ?></div></td>
                    <?php if ($esAdmin): ?><td class="small"><?= e($cr['cobrador'] ?? '—') ?></td><?php endif; ?>
                    <td class="text-end money"><?= money($cr['monto']) ?></td>
                    <td class="text-end money"><?= money($cr['total']) ?><div class="small text-secondary"><?= (float) $cr['interes'] ?> %</div></td>
                    <td class="small" style="min-width:150px">
                        <?= (int) $cr['cuotas_pagadas'] ?>/<?= (int) $cr['num_cuotas'] ?> <?= frecuencia_plural($cr['frecuencia']) ?> de <?= money($cr['valor_cuota']) ?>
                        <div class="progress mt-1" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div></div>
                    </td>
                    <td class="text-end money fw-bold"><?= money($cr['saldo']) ?></td>
                    <td>
                        <?= badge($cr['estado']) ?>
                        <?php if ($cr['vencido'] > 0): ?>
                            <div class="small text-danger fw-semibold mt-1"><?= money($cr['vencido']) ?> · <?= dias_entre($cr['mora_desde'], date('Y-m-d')) ?> d</div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$creditos): ?>
                <tr><td colspan="8"><div class="empty"><i class="bi bi-cash-coin"></i>No hay créditos con esos filtros.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
