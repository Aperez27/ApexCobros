<?php
use App\Core\Auth;

$esAdmin = Auth::isAdmin();
$totSaldo = array_sum(array_column($clientes, 'saldo'));
$totVencido = array_sum(array_column($clientes, 'vencido'));
?>
<form class="filters row g-2 align-items-end" method="get" action="<?= url('clientes') ?>">
    <div class="col-lg-4 col-md-6">
        <label class="form-label" for="q">Buscar</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input class="form-control" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Cédula, nombre, teléfono o barrio">
        </div>
    </div>
    <?php if ($esAdmin): ?>
    <div class="col-lg-2 col-md-6">
        <label class="form-label" for="cobrador_id">Cobrador</label>
        <select class="form-select" id="cobrador_id" name="cobrador_id">
            <option value="">Todos</option>
            <option value="sin" <?= $f['cobrador_id'] === 'sin' ? 'selected' : '' ?>>Sin asignar</option>
            <?php foreach ($cobradores as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $f['cobrador_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?><?= $c['activo'] ? '' : ' (inactivo)' ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="col-lg-2 col-md-4 col-6">
        <label class="form-label" for="situacion">Situación</label>
        <select class="form-select" id="situacion" name="situacion" aria-label="Situación">
            <option value="">Todas</option>
            <option value="mora" <?= $f['situacion'] === 'mora' ? 'selected' : '' ?>>En mora</option>
            <option value="al_dia" <?= $f['situacion'] === 'al_dia' ? 'selected' : '' ?>>Al día</option>
            <option value="sin_credito" <?= $f['situacion'] === 'sin_credito' ? 'selected' : '' ?>>Sin crédito activo</option>
        </select>
    </div>
    <div class="col-lg-1 col-md-4 col-6">
        <label class="form-label" for="calif">Calif.</label>
        <select class="form-select" id="calif" name="calif">
            <option value="">Todas</option>
            <?php foreach (['A', 'B', 'C', 'D', 'N'] as $l): ?><option value="<?= $l ?>" <?= ($_GET['calif'] ?? '') === $l ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-lg-1 col-md-4 col-6">
        <label class="form-label" for="estado">Estado</label>
        <select class="form-select" id="estado" name="estado">
            <option value="">Todos</option>
            <?php foreach (App\Models\Cliente::estados() as $k => $v): ?>
                <option value="<?= $k ?>" <?= $f['estado'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-lg-2 col-md-4 d-flex gap-2">
        <button class="btn btn-primary flex-fill"><i class="bi bi-funnel"></i> Filtrar</button>
        <a href="<?= url('clientes') ?>" class="btn btn-light border" title="Limpiar filtros" aria-label="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
    </div>
</form>

<form method="post" action="<?= url('clientes/asignar') ?>" id="formAsignar">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <span><?= count($clientes) ?> cliente(s)</span>
            <span class="badge badge-soft-primary">Saldo <?= money($totSaldo) ?></span>
            <?php if ($totVencido > 0): ?><span class="badge badge-soft-danger">Vencido <?= money($totVencido) ?></span><?php endif; ?>
            <?php if ($esAdmin): ?>
                <div class="ms-auto d-none align-items-center gap-2" data-needs-selection style="display:flex">
                    <span class="small text-secondary"><strong id="selCount">0</strong> seleccionado(s) →</span>
                    <select class="form-select form-select-sm" name="cobrador_id" style="width:auto" aria-label="Cobrador destino">
                        <option value="">Quitar cobrador</option>
                        <?php foreach ($cobradores as $c): if (!$c['activo']) continue; ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-primary"><i class="bi bi-arrow-left-right"></i> Asignar</button>
                </div>
            <?php endif; ?>
            <a href="<?= url('clientes/crear') ?>" class="btn btn-sm btn-primary <?= $esAdmin ? '' : 'ms-auto' ?>"><i class="bi bi-person-plus"></i> Nuevo cliente</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr>
                    <?php if ($esAdmin): ?><th style="width:36px"><input type="checkbox" class="form-check-input" id="checkAll" aria-label="Seleccionar todos"></th><?php endif; ?>
                    <th>Cliente</th><th class="text-center">Calif.</th><th>Contacto</th><th>Dirección</th>
                    <?php if ($esAdmin): ?><th>Cobrador</th><?php endif; ?>
                    <th class="text-end">Saldo</th><th>Situación</th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($clientes as $c): ?>
                    <tr class="<?= $c['estado'] === 'inactivo' ? 'row-muted' : '' ?>">
                        <?php if ($esAdmin): ?><td><input type="checkbox" class="form-check-input" name="clientes[]" value="<?= $c['id'] ?>" aria-label="Seleccionar"></td><?php endif; ?>
                        <td>
                            <a href="<?= url('clientes/ver/' . $c['id']) ?>" class="d-flex align-items-center gap-2 text-decoration-none text-body">
                                <span class="avatar"><?= e(iniciales($c['nombres'] . ' ' . $c['apellidos'])) ?></span>
                                <span><span class="fw-semibold d-block"><?= e($c['nombres'] . ' ' . $c['apellidos']) ?></span>
                                <span class="small text-secondary">C.C. <?= e($c['cedula']) ?></span></span>
                            </a>
                        </td>
                        <td class="text-center"><?= calificacion_badge($calificaciones[$c['id']] ?? null) ?></td>
                        <td class="small">
                            <div><i class="bi bi-telephone text-secondary"></i> <?= e($c['telefono1']) ?></div>
                            <?php if ($c['telefono2']): ?><div class="text-secondary"><i class="bi bi-telephone"></i> <?= e($c['telefono2']) ?></div><?php endif; ?>
                        </td>
                        <td class="small"><?= e($c['direccion']) ?><?php if ($c['barrio']): ?><div class="text-secondary"><?= e($c['barrio']) ?></div><?php endif; ?></td>
                        <?php if ($esAdmin): ?>
                            <td class="small"><?= $c['cobrador'] ? e($c['cobrador']) : '<span class="badge badge-soft-warning">Sin asignar</span>' ?></td>
                        <?php endif; ?>
                        <td class="text-end money fw-semibold"><?= $c['saldo'] > 0 ? money($c['saldo']) : '<span class="text-secondary">—</span>' ?></td>
                        <td>
                            <?php if ($c['estado'] !== 'activo'): ?>
                                <?= badge($c['estado']) ?>
                            <?php elseif ($c['vencido'] > 0): ?>
                                <span class="badge badge-soft-danger" title="Vencido desde <?= fecha($c['mora_desde']) ?>">Mora <?= money($c['vencido']) ?></span>
                            <?php elseif ($c['creditos_activos'] > 0): ?>
                                <?= badge('al dia') ?>
                            <?php else: ?>
                                <span class="badge badge-soft-secondary">Sin crédito</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="<?= url('clientes/ver/' . $c['id']) ?>" class="btn btn-sm btn-light border" title="Ver" aria-label="Ver"><i class="bi bi-eye"></i></a>
                            <a href="<?= wa_link($c['telefono1']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-light border text-success" title="WhatsApp" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$clientes): ?>
                    <tr><td colspan="9"><div class="empty"><i class="bi bi-people"></i>No hay clientes con esos filtros.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>
