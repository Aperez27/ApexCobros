<?php
use App\Core\Auth;

$esAdmin = Auth::isAdmin();
$pct = porcentaje((float) $cr['total_pagado'], (float) $cr['total']);
$hoy = date('Y-m-d');
$proxima = null;
foreach ($cuotas as $q) {
    if ($q['estado'] !== 'pagada') {
        $proxima = $q;
        break;
    }
}
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="<?= url('clientes/ver/' . $cr['cliente_id']) ?>" class="btn btn-sm btn-light border"><i class="bi bi-arrow-left"></i> <?= e($cr['cliente']) ?></a>
    <?= badge($cr['estado']) ?>
    <?= calificacion_badge($calificacion, true) ?>
    <?php if ($cr['vencido'] > 0): ?><span class="badge badge-soft-danger"><?= (int) $cr['cuotas_vencidas'] ?> cuota(s) vencida(s) · <?= dias_entre($cr['mora_desde'], $hoy) ?> días</span><?php endif; ?>
    <div class="ms-auto d-flex flex-wrap gap-2">
        <?php if ($cr['estado'] === 'activo'): ?>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalPago"
                    data-credito="<?= $cr['id'] ?>" data-cliente="<?= e($cr['cliente']) ?>" data-codigo="<?= e($cr['codigo']) ?>"
                    data-saldo="<?= (int) $cr['saldo'] ?>" data-sugerido="<?= (int) min($cr['saldo'], max($cr['valor_cuota'], $cr['vencido'])) ?>">
                <i class="bi bi-cash"></i> Registrar pago
            </button>
            <button type="button" class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#modalGestion"
                    data-credito="<?= $cr['id'] ?>" data-cliente="<?= e($cr['cliente']) ?>" data-codigo="<?= e($cr['codigo']) ?>"
                    data-sugerido="<?= (int) min($cr['saldo'], max($cr['valor_cuota'], $cr['vencido'])) ?>">
                <i class="bi bi-journal-plus"></i> No pagó / gestión
            </button>
        <?php endif; ?>
        <div class="dropdown">
            <button class="btn btn-light border dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-file-earmark-text"></i> Documentos</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= url('creditos/estado/' . $cr['id']) ?>" target="_blank"><i class="bi bi-printer"></i> Estado de cuenta (imprimir / PDF)</a></li>
                <li><a class="dropdown-item" href="<?= url('creditos/excel/' . $cr['id']) ?>"><i class="bi bi-file-earmark-excel text-success"></i> Estado de cuenta en Excel</a></li>
                <?php if ($cr['estado'] === 'pagado'): ?>
                    <li><a class="dropdown-item" href="<?= url('creditos/pazysalvo/' . $cr['id']) ?>" target="_blank"><i class="bi bi-patch-check text-success"></i> Paz y salvo</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php if ($esAdmin && $cr['estado'] === 'activo'): ?>
            <a href="<?= url('creditos/renovar/' . $cr['id']) ?>" class="btn btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Renovar</a>
            <button type="button" class="btn btn-light border text-danger" data-bs-toggle="modal" data-bs-target="#modalAnular"><i class="bi bi-x-octagon"></i> Anular</button>
        <?php endif; ?>
    </div>
</div>

<?php if ($cr['estado'] === 'anulado'): ?>
    <div class="alert alert-secondary"><strong>Crédito anulado.</strong> Motivo: <?= e($cr['motivo_anulacion']) ?></div>
<?php endif; ?>
<?php if ($cr['renovado_por_id']): ?>
    <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-arrow-repeat"></i>
        Este crédito fue renovado: su saldo se trasladó al crédito <a href="<?= url('creditos/ver/' . $cr['renovado_por_id']) ?>" class="fw-bold"><?= e($cr['renovado_por_codigo']) ?></a>.</div>
<?php endif; ?>
<?php if ($cr['renovado_de']): ?>
    <div class="alert alert-light border d-flex flex-wrap align-items-center gap-2"><i class="bi bi-arrow-repeat text-primary"></i>
        Renovación de <a href="<?= url('creditos/ver/' . $cr['renovado_de']) ?>" class="fw-bold"><?= e($cr['renovado_de_codigo']) ?></a>:
        monto <?= money($cr['monto']) ?> − saldo anterior <?= money($cr['saldo_renovado']) ?> = <strong>efectivo entregado <?= money($cr['neto_entregado']) ?></strong></div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-md-3 col-6"><div class="stat"><div class="stat-label">Prestado</div><div class="stat-value"><?= money($cr['monto']) ?></div><div class="stat-foot">Interés <?= (float) $cr['interes'] ?> % · <?= money($cr['total'] - $cr['monto']) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="stat"><div class="stat-label">Total a pagar</div><div class="stat-value"><?= money($cr['total']) ?></div><div class="stat-foot"><?= (int) $cr['num_cuotas'] ?> cuotas <?= frecuencia_plural($cr['frecuencia']) ?> de <?= money($cr['valor_cuota']) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="stat"><div class="stat-label">Pagado</div><div class="stat-value text-success"><?= money($cr['total_pagado']) ?></div>
        <div class="progress mt-2" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div></div></div></div>
    <div class="col-md-3 col-6"><div class="stat stat-hero"><div class="stat-label">Saldo pendiente</div><div class="stat-value"><?= money($cr['saldo']) ?></div>
        <div class="stat-foot"><?= $cr['vencido'] > 0 ? 'Vencido: ' . money($cr['vencido']) : ($proxima ? 'Próxima: ' . fecha($proxima['fecha_vencimiento']) : 'Sin cuotas pendientes') ?></div></div></div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <span><i class="bi bi-calendar3 text-primary"></i> Plan de cuotas</span>
                <span class="ms-auto small text-secondary fw-normal"><?= (int) $cr['cuotas_pagadas'] ?> de <?= (int) $cr['num_cuotas'] ?> pagadas</span>
            </div>
            <div class="table-responsive" style="max-height: 560px; overflow:auto">
                <table class="table table-sm table-hover">
                    <thead class="sticky-top"><tr><th>#</th><th>Vence</th><th class="text-end">Valor</th><th class="text-end">Abonado</th><th class="text-end">Pendiente</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($cuotas as $q):
                        $pend = (float) $q['valor'] - (float) $q['abonado'];
                        $vencida = $q['estado'] !== 'pagada' && $q['fecha_vencimiento'] < $hoy && $cr['estado'] === 'activo';
                        $esHoy = $q['fecha_vencimiento'] === $hoy; ?>
                        <tr class="<?= $q['estado'] === 'pagada' ? 'cuota-pagada' : '' ?> <?= $vencida ? 'cuota-vencida' : '' ?> <?= $esHoy ? 'table-warning' : '' ?>">
                            <td class="fw-semibold"><?= (int) $q['numero'] ?></td>
                            <td><?= fecha($q['fecha_vencimiento']) ?><?= $esHoy ? ' <span class="badge badge-soft-warning">Hoy</span>' : '' ?></td>
                            <td class="text-end money"><?= money($q['valor']) ?></td>
                            <td class="text-end money"><?= $q['abonado'] > 0 ? money($q['abonado']) : '—' ?></td>
                            <td class="text-end money fw-semibold"><?= $pend > 0 ? money($pend) : '—' ?></td>
                            <td><?= $vencida ? badge('vencida') : badge($q['estado']) ?>
                                <?php if ($q['fecha_pago']): ?><span class="small text-secondary"><?= fecha($q['fecha_pago']) ?></span><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-info-circle text-primary"></i> Datos del crédito</div>
            <div class="card-body">
                <dl class="dl-grid">
                    <dt>Código</dt><dd><?= e($cr['codigo']) ?></dd>
                    <dt>Cliente</dt><dd><a href="<?= url('clientes/ver/' . $cr['cliente_id']) ?>"><?= e($cr['cliente']) ?></a> · <?= e($cr['cedula']) ?></dd>
                    <dt>Teléfonos</dt><dd><?= e($cr['telefono1']) ?><?= $cr['telefono2'] ? ' · ' . e($cr['telefono2']) : '' ?></dd>
                    <dt>Dirección</dt><dd><?= e($cr['direccion']) ?><?= $cr['barrio'] ? ', ' . e($cr['barrio']) : '' ?></dd>
                    <dt>Cobrador</dt><dd><?= e($cr['cobrador'] ?? 'Sin asignar') ?></dd>
                    <dt>Desembolso</dt><dd><?= fecha($cr['fecha_inicio']) ?></dd>
                    <dt>Vigencia</dt><dd><?= fecha($cr['fecha_primera_cuota']) ?> → <?= fecha($cr['fecha_fin']) ?></dd>
                    <?php if ($cr['frecuencia'] === 'diaria'): ?><dt>Domingos</dt><dd><?= $cr['excluir_domingos'] ? 'No se cobra' : 'Se cobra' ?></dd><?php endif; ?>
                    <?php if ($cr['observaciones']): ?><dt>Notas</dt><dd style="white-space:pre-line"><?= e($cr['observaciones']) ?></dd><?php endif; ?>
                </dl>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center"><i class="bi bi-journal-text text-primary me-1"></i> Gestiones de cobro (<?= count($gestiones) ?>)</div>
            <div class="list-group list-group-flush" style="max-height:320px; overflow:auto">
                <?php
                $tonoProm = ['cumplida' => 'success', 'incumplida' => 'danger', 'pendiente' => 'info'];
                foreach ($gestiones as $g): ?>
                    <div class="list-group-item py-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold"><?= e(App\Models\Gestion::resultados()[$g['resultado']]) ?></span>
                            <?php if ($g['estado_promesa']): ?><span class="badge badge-soft-<?= $tonoProm[$g['estado_promesa']] ?>"><?= ucfirst($g['estado_promesa']) ?></span><?php endif; ?>
                            <span class="ms-auto small text-secondary"><?= fecha($g['created_at'], true) ?></span>
                        </div>
                        <div class="small text-secondary">
                            <?= e(App\Models\Gestion::tipos()[$g['tipo']]) ?> · <?= e($g['usuario'] ?? '') ?>
                            <?php if ($g['resultado'] === 'promesa'): ?> · pagará el <strong><?= fecha($g['fecha_promesa']) ?></strong><?= $g['monto_promesa'] ? ' ' . money($g['monto_promesa']) : '' ?><?php endif; ?>
                        </div>
                        <?php if ($g['nota']): ?><div class="small"><?= e($g['nota']) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$gestiones): ?><div class="empty py-3"><i class="bi bi-journal"></i>Sin gestiones registradas.</div><?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-receipt text-primary"></i> Pagos (<?= count($pagos) ?>)</div>
            <div class="list-group list-group-flush">
                <?php foreach ($pagos as $p): ?>
                    <div class="list-group-item py-2 <?= $p['anulado'] ? 'row-muted' : '' ?>">
                        <div class="d-flex align-items-center gap-2">
                            <div class="min-w-0">
                                <div class="fw-semibold"><?= e($p['recibo']) ?> <?= $p['anulado'] ? '<span class="badge badge-soft-danger">Anulado</span>' : '' ?></div>
                                <div class="small text-secondary"><?= fecha($p['fecha'], true) ?> · <?= e(metodo_label($p['metodo'])) ?> · <?= e($p['cobrador'] ?? '') ?></div>
                                <?php if ($p['nota']): ?><div class="small"><?= e($p['nota']) ?></div><?php endif; ?>
                                <?php if ($p['anulado']): ?><div class="small text-danger">Anulado por <?= e($p['anulado_por_nombre']) ?>: <?= e($p['motivo_anulacion']) ?></div><?php endif; ?>
                            </div>
                            <div class="ms-auto text-end">
                                <div class="fw-bold money <?= $p['anulado'] ? 'text-decoration-line-through' : 'text-success' ?>"><?= money($p['monto']) ?></div>
                                <div class="d-flex gap-1 justify-content-end mt-1">
                                    <a href="<?= url('pagos/recibo/' . $p['id']) ?>" target="_blank" class="btn btn-sm btn-light border py-0" title="Recibo" aria-label="Ver recibo"><i class="bi bi-printer"></i></a>
                                    <?php if ($esAdmin && !$p['anulado'] && $p['metodo'] !== 'renovacion'): ?>
                                        <form method="post" action="<?= url('pagos/anular/' . $p['id']) ?>" class="d-inline"
                                              data-confirm="El recibo <?= e($p['recibo']) ?> por <?= money($p['monto']) ?> quedará anulado y el saldo del crédito se recalculará. Esta acción queda registrada en la auditoría."
                                              data-confirm-title="¿Anular este pago?" data-confirm-btn="Sí, anular pago" data-confirm-input="Motivo de la anulación">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="motivo" value="">
                                            <button class="btn btn-sm btn-light border py-0 text-danger" title="Anular pago" aria-label="Anular pago"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$pagos): ?><div class="empty"><i class="bi bi-inbox"></i>Sin pagos todavía.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($esAdmin && $cr['estado'] === 'activo'): ?>
<div class="modal fade" id="modalAnular" tabindex="-1" aria-labelledby="modalAnularLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= url('creditos/anular/' . $cr['id']) ?>">
            <?= csrf_field() ?>
            <div class="modal-header"><h2 class="modal-title h5 fw-bold" id="modalAnularLabel">Anular crédito <?= e($cr['codigo']) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <p class="text-secondary small">Solo se pueden anular créditos sin pagos vigentes (por ejemplo, si se registró por error). El crédito no se borra: queda en el historial como anulado.</p>
                <label class="form-label" for="motivoAnular">Motivo</label>
                <input class="form-control" id="motivoAnular" name="motivo" required maxlength="255">
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger">Anular crédito</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php
$volver = '';
require APP_PATH . '/views/pagos/_modal.php';
require APP_PATH . '/views/gestiones/_modal.php';
?>
