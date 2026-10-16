<?php
use App\Core\Auth;
use App\Models\Caja;

$esAdmin = Auth::isAdmin();
$esHoy = $fecha === date('Y-m-d');
$t = ['efectivo' => 0, 'digital' => 0, 'gastos' => 0, 'a_entregar' => 0, 'entregado' => 0, 'pendientes' => 0];
foreach ($resumen as $r) {
    $t['efectivo'] += $r['efectivo'];
    $t['digital'] += $r['digital'];
    $t['gastos'] += $r['gastos'];
    $t['a_entregar'] += $r['a_entregar'];
    $t['entregado'] += (float) $r['entregado'];
    $t['pendientes'] += !$r['cierre_id'] && ($r['efectivo'] > 0 || $r['gastos'] > 0) ? 1 : 0;
}
$totalMano = array_sum(array_column($enMano, 'en_mano'));
$mio = $resumen[0] ?? null;
?>
<form class="filters row g-2 align-items-end" method="get" action="<?= url('caja') ?>">
    <div class="col-md-3 col-7"><label class="form-label" for="fecha">Fecha</label><input type="date" class="form-control" id="fecha" name="fecha" value="<?= e($fecha) ?>" max="<?= date('Y-m-d') ?>"></div>
    <div class="col-md-2 col-5"><button class="btn btn-primary w-100"><i class="bi bi-arrow-repeat"></i> Ver</button></div>
    <div class="col-md-7 text-md-end small text-secondary">
        <i class="bi bi-info-circle"></i> Efectivo en mano = efectivo cobrado − gastos − lo entregado en los cierres.
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-xl-3 col-6"><div class="stat"><span class="stat-icon tone-success"><i class="bi bi-cash"></i></span><div class="stat-label">Efectivo cobrado</div><div class="stat-value text-success"><?= money($t['efectivo']) ?></div><div class="stat-foot"><?= fecha($fecha) ?></div></div></div>
    <div class="col-xl-3 col-6"><div class="stat"><span class="stat-icon tone-info"><i class="bi bi-phone"></i></span><div class="stat-label">Digital (Nequi, transf.)</div><div class="stat-value"><?= money($t['digital']) ?></div><div class="stat-foot">llega directo a la cuenta</div></div></div>
    <div class="col-xl-3 col-6"><div class="stat"><span class="stat-icon tone-warning"><i class="bi bi-fuel-pump"></i></span><div class="stat-label">Gastos de ruta</div><div class="stat-value text-warning"><?= money($t['gastos']) ?></div><div class="stat-foot">se descuentan del efectivo</div></div></div>
    <div class="col-xl-3 col-6"><div class="stat stat-hero"><span class="stat-icon"><i class="bi bi-wallet2"></i></span><div class="stat-label"><?= $esAdmin ? 'Efectivo en la calle' : 'Efectivo en tu poder' ?></div><div class="stat-value"><?= money($totalMano) ?></div><div class="stat-foot"><?= $esAdmin ? 'sin entregar, todos los cobradores' : 'pendiente por entregar' ?></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <?php if ($esAdmin): ?>
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <span><i class="bi bi-clipboard-check text-primary"></i> Cierre de caja · <?= fecha($fecha) ?></span>
                    <?php if ($t['pendientes']): ?><span class="badge badge-soft-warning ms-2"><?= $t['pendientes'] ?> pendiente(s)</span><?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th style="min-width:220px">Cobrador</th><th class="text-end">Efectivo</th><th class="text-end">Gastos</th><th class="text-end">A entregar</th><th style="min-width:260px">Recibido</th></tr></thead>
                        <tbody>
                        <?php foreach ($resumen as $r): $m = $enMano[$r['id']]['en_mano'] ?? 0; ?>
                            <tr>
                                <td><div class="fw-semibold"><?= e($r['nombre']) ?></div>
                                    <div class="small text-secondary"><?= (int) $r['num_pagos'] ?> pagos · digital <?= money($r['digital']) ?> · en mano <?= money($m) ?></div></td>
                                <td class="text-end money"><?= money($r['efectivo']) ?></td>
                                <td class="text-end money text-warning"><?= $r['gastos'] > 0 ? '−' . money($r['gastos']) : money(0) ?></td>
                                <td class="text-end money fw-bold"><?= money($r['a_entregar']) ?></td>
                                <td>
                                    <?php if ($r['cierre_id'] && !$r['desactualizado']): $d = (float) $r['diferencia']; ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fw-bold money"><?= $r['entregado'] < 0 ? 'Reembolso ' . money(abs($r['entregado'])) : money($r['entregado']) ?></span>
                                            <?= abs($d) < 0.5 ? '<span class="badge badge-soft-success">Cuadrado</span>'
                                                : '<span class="badge badge-soft-' . ($d < 0 ? 'danger">Faltante ' : 'info">Sobrante ') . money(abs($d)) . '</span>' ?>
                                        </div>
                                        <div class="small text-secondary">Recibió <?= e($r['recibido_por']) ?> · <?= fecha($r['cerrado_en'], true) ?><?= $r['observaciones'] ? ' · ' . e($r['observaciones']) : '' ?></div>
                                        <details class="small mt-1">
                                            <summary class="text-primary fw-semibold" style="cursor:pointer">Corregir cierre</summary>
                                            <form method="post" action="<?= url('caja/cerrar') ?>" class="d-flex flex-wrap gap-1 align-items-center mt-1" data-cierre="<?= (int) $r['a_entregar'] ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
                                                <input type="hidden" name="cobrador_id" value="<?= $r['id'] ?>">
                                                <div class="input-group input-group-sm" style="max-width:150px"><span class="input-group-text">$</span>
                                                    <input class="form-control" name="entregado" data-money inputmode="numeric" required value="<?= number_format(max(0, (float) $r['entregado']), 0, ',', '.') ?>" aria-label="Valor recibido corregido"></div>
                                                <input class="form-control form-control-sm" name="observaciones" maxlength="255" placeholder="Motivo de la corrección" required style="max-width:170px" aria-label="Motivo de la corrección">
                                                <button class="btn btn-sm btn-outline-primary">Guardar</button>
                                                <div class="w-100" data-diferencia></div>
                                            </form>
                                        </details>
                                    <?php else: ?>
                                        <?php if ($r['desactualizado']): ?><div class="small text-warning fw-semibold mb-1"><i class="bi bi-exclamation-triangle"></i> Hubo movimientos después del cierre (antes se entregó <?= money($r['entregado']) ?>). Ciérralo de nuevo.</div><?php endif; ?>
                                        <form method="post" action="<?= url('caja/cerrar') ?>" class="d-flex flex-wrap gap-1 align-items-center" data-cierre="<?= (int) $r['a_entregar'] ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
                                            <input type="hidden" name="cobrador_id" value="<?= $r['id'] ?>">
                                            <div class="input-group input-group-sm" style="max-width:150px"><span class="input-group-text">$</span>
                                                <input class="form-control" name="entregado" data-money inputmode="numeric" required value="<?= $r['a_entregar'] > 0 ? number_format($r['a_entregar'], 0, ',', '.') : '0' ?>" aria-label="Valor recibido"></div>
                                            <?php if ($r['a_entregar'] < 0): ?><div class="w-100 small text-info">Gastó más de lo que cobró: con valor 0 se registra un reembolso de <?= money(abs($r['a_entregar'])) ?> al cobrador.</div><?php endif; ?>
                                            <input class="form-control form-control-sm" name="observaciones" maxlength="255" placeholder="Nota" style="max-width:110px" aria-label="Observaciones">
                                            <button class="btn btn-sm btn-primary">Cerrar</button>
                                            <div class="w-100" data-diferencia></div>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$resumen): ?><tr><td colspan="5"><div class="empty"><i class="bi bi-person-badge"></i>No hay cobradores activos.</div></td></tr><?php endif; ?>
                        </tbody>
                        <?php if ($resumen): ?>
                        <tfoot><tr class="fw-bold"><td>Total</td><td class="text-end money"><?= money($t['efectivo']) ?></td><td class="text-end money"><?= money($t['gastos']) ?></td><td class="text-end money"><?= money($t['a_entregar']) ?></td><td class="money">Recibido: <?= money($t['entregado']) ?></td></tr></tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        <?php elseif ($mio): ?>
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-wallet2 text-primary"></i> Tu caja · <?= fecha($fecha) ?></div>
                <div class="card-body">
                    <div class="d-grid gap-2 mb-3" style="max-width:420px">
                        <div class="d-flex justify-content-between"><span class="text-secondary">Efectivo cobrado (<?= (int) $mio['num_pagos'] ?> pagos)</span><strong class="money"><?= money($mio['efectivo']) ?></strong></div>
                        <div class="d-flex justify-content-between"><span class="text-secondary">Menos gastos de ruta</span><strong class="money text-warning">−<?= money($mio['gastos']) ?></strong></div>
                        <div class="d-flex justify-content-between border-top pt-2 fs-5"><span class="fw-semibold">Debes entregar</span><strong class="money"><?= money($mio['a_entregar']) ?></strong></div>
                        <div class="small text-secondary">Lo cobrado por Nequi o transferencia (<?= money($mio['digital']) ?>) no se entrega en efectivo.</div>
                    </div>
                    <?php if ($mio['cierre_id']): ?>
                        <div class="alert alert-<?= abs((float) $mio['diferencia']) < 0.5 ? 'success' : 'warning' ?> mb-0 py-2">
                            Caja cerrada: entregaste <?= money($mio['entregado']) ?> a <?= e($mio['recibido_por']) ?>.
                            <?php if (abs((float) $mio['diferencia']) >= 0.5): ?><?= $mio['diferencia'] < 0 ? 'Quedó un faltante' : 'Quedó un sobrante' ?> de <?= money(abs($mio['diferencia'])) ?>.<?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0 py-2"><i class="bi bi-info-circle"></i> Al terminar la ruta entrega el efectivo al administrador para cerrar tu caja.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">

        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history text-primary"></i> Cierres de los últimos 7 días</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead><tr><th>Fecha</th><?php if ($esAdmin): ?><th>Cobrador</th><?php endif; ?><th class="text-end">A entregar</th><th class="text-end">Entregado</th><th class="text-end">Diferencia</th><th>Recibió</th></tr></thead>
                    <tbody>
                    <?php foreach ($historial as $h): $d = (float) $h['diferencia']; ?>
                        <tr>
                            <td><a href="<?= url('caja?fecha=' . $h['fecha']) ?>" class="text-decoration-none"><?= fecha($h['fecha']) ?></a></td>
                            <?php if ($esAdmin): ?><td><?= e($h['cobrador']) ?></td><?php endif; ?>
                            <td class="text-end money"><?= money($h['a_entregar']) ?></td>
                            <td class="text-end money"><?= $h['entregado'] < 0 ? '<span class="text-info">Reembolso ' . money(abs($h['entregado'])) . '</span>' : money($h['entregado']) ?></td>
                            <td class="text-end money fw-semibold <?= $d < -0.5 ? 'text-danger' : ($d > 0.5 ? 'text-info' : 'text-success') ?>"><?= abs($d) < 0.5 ? 'Cuadrado' : ($d < 0 ? '−' : '+') . money(abs($d)) ?></td>
                            <td class="small"><?= e($h['recibido'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$historial): ?><tr><td colspan="6"><div class="empty"><i class="bi bi-inbox"></i>Sin cierres registrados.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <form class="card mb-3" method="post" action="<?= url('caja/gasto') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="fecha" value="<?= e($esAdmin ? $fecha : date('Y-m-d')) ?>">
            <div class="card-header"><i class="bi bi-fuel-pump text-warning"></i> Registrar gasto de ruta</div>
            <div class="card-body d-grid gap-2">
                <?php if ($esAdmin): ?>
                    <div><label class="form-label" for="gCob">Cobrador</label>
                        <select class="form-select" id="gCob" name="cobrador_id" required><option value="">Selecciona…</option>
                            <?php foreach ($cobradores as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['nombre']) ?></option><?php endforeach; ?>
                        </select></div>
                <?php elseif (!$esHoy): ?>
                    <div class="alert alert-secondary py-2 small mb-0">Los gastos se registran con la fecha de hoy.</div>
                <?php endif; ?>
                <div><label class="form-label" for="gConcepto">Concepto</label>
                    <select class="form-select" id="gConcepto" name="concepto" required>
                        <?php foreach (Caja::conceptos() as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label class="form-label" for="gValor">Valor</label>
                    <div class="input-group"><span class="input-group-text">$</span><input class="form-control" id="gValor" name="valor" data-money inputmode="numeric" required></div></div>
                <div><label class="form-label" for="gNota">Detalle</label><input class="form-control" id="gNota" name="nota" maxlength="255" placeholder="Ej: gasolina moto"></div>
                <button class="btn btn-warning"><i class="bi bi-plus-lg"></i> Registrar gasto</button>
            </div>
        </form>

        <div class="card">
            <div class="card-header">Gastos del <?= fecha($fecha) ?></div>
            <div class="list-group list-group-flush">
                <?php foreach ($gastos as $g): ?>
                    <div class="list-group-item d-flex align-items-center gap-2 <?= $g['anulado'] ? 'row-muted' : '' ?>">
                        <div class="min-w-0">
                            <div class="fw-semibold"><?= e(Caja::conceptos()[$g['concepto']]) ?><?= $g['anulado'] ? ' <span class="badge badge-soft-danger">Anulado</span>' : '' ?></div>
                            <div class="small text-secondary"><?= $esAdmin ? e($g['cobrador']) . ' · ' : '' ?><?= e($g['nota'] ?? '') ?></div>
                        </div>
                        <div class="ms-auto fw-bold money <?= $g['anulado'] ? 'text-decoration-line-through' : '' ?>"><?= money($g['valor']) ?></div>
                        <?php if (!$g['anulado'] && ($esAdmin || $esHoy)): ?>
                            <form method="post" action="<?= url('caja/anular-gasto/' . $g['id']) ?>" data-confirm="El gasto de <?= money($g['valor']) ?> dejará de descontarse del efectivo a entregar." data-confirm-title="¿Anular este gasto?" data-confirm-btn="Sí, anular" data-confirm-tone="warning">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-light border text-danger" title="Anular" aria-label="Anular gasto"><i class="bi bi-x-lg"></i></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$gastos): ?><div class="empty py-4"><i class="bi bi-inbox"></i>Sin gastos.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>
