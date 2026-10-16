<?php
use App\Core\Auth;

$esAdmin = Auth::isAdmin();
$activos = array_filter($creditos, fn ($x) => $x['estado'] === 'activo');
$saldo = array_sum(array_column($activos, 'saldo'));
$vencido = array_sum(array_column($activos, 'vencido'));
$volver = '';
?>
<div class="row g-3">
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-body text-center">
                <span class="avatar avatar-lg mb-2"><?= e(iniciales($c['nombres'] . ' ' . $c['apellidos'])) ?></span>
                <h2 class="h5 fw-bold mb-0"><?= e($c['nombres'] . ' ' . $c['apellidos']) ?></h2>
                <div class="text-secondary mb-2">C.C. <?= e($c['cedula']) ?></div>
                <?= badge($c['estado']) ?>
                <?= calificacion_badge($calificacion, true) ?>
                <?php if ($vencido > 0): ?><span class="badge badge-soft-danger">En mora</span><?php elseif ($activos): ?><?= badge('al dia') ?><?php endif; ?>
                <div class="d-flex justify-content-center gap-2 mt-3">
                    <a href="tel:<?= e($c['telefono1']) ?>" class="btn btn-sm btn-light border"><i class="bi bi-telephone"></i> Llamar</a>
                    <a href="<?= wa_link($c['telefono1'], 'Hola ' . $c['nombres'] . ', le saludamos de ' . empresa() . '.') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-light border text-success"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                    <a href="<?= url('clientes/editar/' . $c['id']) ?>" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i> Editar</a>
                </div>
            </div>
            <div class="card-body border-top">
                <div class="row text-center g-0">
                    <div class="col-6 border-end"><div class="small text-secondary">Saldo total</div><div class="fw-bold fs-5 money"><?= money($saldo) ?></div></div>
                    <div class="col-6"><div class="small text-secondary">Vencido</div><div class="fw-bold fs-5 money <?= $vencido > 0 ? 'text-danger' : '' ?>"><?= money($vencido) ?></div></div>
                </div>
            </div>
        </div>

        <?php [$cTxt, $cTono, $cDesc] = App\Models\Cliente::calificacionInfo($calificacion['letra'] ?? 'N'); ?>
        <div class="card mb-3 border-<?= $cTono === 'secondary' ? 'light' : $cTono ?>">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="display-6 fw-bold text-<?= $cTono ?>" style="min-width:48px; text-align:center"><?= $calificacion['letra'] ?? 'N' ?></div>
                    <div>
                        <div class="fw-bold">Calificación: <?= e($cTxt) ?></div>
                        <div class="small text-secondary"><?= e($cDesc) ?></div>
                    </div>
                </div>
                <?php if ($calificacion && $calificacion['evaluadas']): ?>
                    <div class="row text-center g-0 mt-3 small">
                        <div class="col-4"><div class="text-secondary">Puntualidad</div><strong><?= round($calificacion['puntualidad'] * 100) ?> %</strong></div>
                        <div class="col-4"><div class="text-secondary">Atraso prom.</div><strong><?= round((float) $calificacion['atraso_promedio'], 1) ?> días</strong></div>
                        <div class="col-4"><div class="text-secondary">Créditos pagados</div><strong><?= (int) $calificacion['pagados'] ?> de <?= (int) $calificacion['creditos'] ?></strong></div>
                    </div>
                    <div class="small text-secondary mt-2">Basado en <?= (int) $calificacion['evaluadas'] ?> cuotas: <?= (int) $calificacion['a_tiempo'] ?> a tiempo, <?= (int) $calificacion['tarde'] ?> tarde, <?= (int) $calificacion['vencidas'] ?> vencidas sin pagar.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="section-title">Contacto</div>
                <dl class="dl-grid">
                    <dt>Teléfono 1</dt><dd><?= e($c['telefono1']) ?></dd>
                    <dt>Teléfono 2</dt><dd><?= e($c['telefono2'] ?: '—') ?></dd>
                    <dt>Correo</dt><dd><?= e($c['email'] ?: '—') ?></dd>
                    <dt>Dirección</dt><dd><?= e($c['direccion']) ?></dd>
                    <dt>Barrio</dt><dd><?= e($c['barrio'] ?: '—') ?></dd>
                    <dt>Ciudad</dt><dd><?= e($c['ciudad'] ?: '—') ?></dd>
                </dl>
                <div class="section-title">Actividad</div>
                <dl class="dl-grid">
                    <dt>Ocupación</dt><dd><?= e($c['ocupacion'] ?: '—') ?></dd>
                    <dt>Negocio</dt><dd><?= e($c['negocio_nombre'] ?: '—') ?></dd>
                    <dt>Dir. negocio</dt><dd><?= e($c['negocio_direccion'] ?: '—') ?></dd>
                    <dt>Nacimiento</dt><dd><?= fecha($c['fecha_nacimiento']) ?></dd>
                </dl>
                <div class="section-title">Referencia</div>
                <dl class="dl-grid">
                    <dt>Nombre</dt><dd><?= e($c['referencia_nombre'] ?: '—') ?><?= $c['referencia_parentesco'] ? ' (' . e($c['referencia_parentesco']) . ')' : '' ?></dd>
                    <dt>Teléfono</dt><dd><?= e($c['referencia_telefono'] ?: '—') ?></dd>
                </dl>
                <?php if ($c['observaciones']): ?>
                    <div class="section-title">Observaciones</div>
                    <p class="mb-0" style="white-space: pre-line"><?= e($c['observaciones']) ?></p>
                <?php endif; ?>
                <div class="small text-secondary mt-3">Registrado el <?= fecha($c['created_at'], true) ?><?= $c['creado_por'] ? ' por ' . e($c['creado_por']) : '' ?></div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center"><i class="bi bi-person-badge text-primary me-1"></i> Cobrador asignado</div>
            <div class="card-body">
                <?php if ($c['cobrador']): ?>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="avatar"><?= e(iniciales($c['cobrador'])) ?></span>
                        <div><div class="fw-semibold"><?= e($c['cobrador']) ?></div><div class="small text-secondary"><?= e($c['cobrador_telefono'] ?? '') ?></div></div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning py-2 small">Este cliente no tiene cobrador asignado.</div>
                <?php endif; ?>
                <?php if ($esAdmin): ?>
                    <form method="post" action="<?= url('clientes/asignar') ?>" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="clientes[]" value="<?= $c['id'] ?>">
                        <input type="hidden" name="motivo" value="Cambio desde ficha del cliente">
                        <select class="form-select form-select-sm" name="cobrador_id" aria-label="Nuevo cobrador">
                            <option value="">Sin cobrador</option>
                            <?php if ($c['cobrador_id'] && !in_array((int) $c['cobrador_id'], array_map('intval', array_column($cobradores, 'id')), true)): ?>
                                <option value="<?= $c['cobrador_id'] ?>" selected><?= e($c['cobrador']) ?> (inactivo)</option>
                            <?php endif; ?>
                            <?php foreach ($cobradores as $cb): ?>
                                <option value="<?= $cb['id'] ?>" <?= (int) $c['cobrador_id'] === (int) $cb['id'] ? 'selected' : '' ?>><?= e($cb['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-primary text-nowrap">Reasignar</button>
                    </form>
                    <?php if ($historial): ?>
                        <div class="section-title mt-3">Historial</div>
                        <ul class="list-unstyled small mb-0 d-grid gap-2">
                            <?php foreach ($historial as $h): ?>
                                <li>
                                    <div><strong><?= e($h['anterior'] ?? 'Sin cobrador') ?></strong> → <strong><?= e($h['nuevo'] ?? 'Sin cobrador') ?></strong></div>
                                    <div class="text-secondary"><?= fecha($h['created_at'], true) ?> · <?= e($h['usuario'] ?? '') ?><?= $h['motivo'] ? ' · ' . e($h['motivo']) : '' ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($esAdmin): ?>
            <form method="post" action="<?= url('clientes/eliminar/' . $c['id']) ?>" data-confirm="Se eliminará a <?= e($c['nombres'] . ' ' . $c['apellidos']) ?> con todos sus datos de contacto. Esta acción no se puede deshacer." data-confirm-title="¿Eliminar este cliente?" data-confirm-btn="Sí, eliminar">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i> Eliminar cliente</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <span><i class="bi bi-cash-coin text-primary"></i> Créditos (<?= count($creditos) ?>)</span>
                <?php if ($esAdmin && $c['estado'] === 'activo'): ?>
                    <a href="<?= url('creditos/crear?cliente=' . $c['id']) ?>" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg"></i> Nuevo crédito</a>
                <?php endif; ?>
            </div>
            <?php if (!$creditos): ?>
                <div class="empty"><i class="bi bi-cash-coin"></i>Este cliente aún no tiene créditos.</div>
            <?php endif; ?>
            <div class="list-group list-group-flush">
                <?php foreach ($creditos as $cr): $pct = porcentaje((float) $cr['total_pagado'], (float) $cr['total']); ?>
                    <div class="list-group-item py-3">
                        <div class="d-flex flex-wrap align-items-start gap-3">
                            <div>
                                <a href="<?= url('creditos/ver/' . $cr['id']) ?>" class="fw-bold text-decoration-none"><?= e($cr['codigo']) ?></a>
                                <?= badge($cr['estado']) ?>
                                <?php if ($cr['vencido'] > 0): ?><span class="badge badge-soft-danger">Mora <?= money($cr['vencido']) ?></span><?php endif; ?>
                                <div class="small text-secondary mt-1">
                                    <?= fecha($cr['fecha_inicio']) ?> · <?= money($cr['monto']) ?> al <?= (float) $cr['interes'] ?> % ·
                                    <?= (int) $cr['num_cuotas'] ?> cuotas <?= frecuencia_plural($cr['frecuencia']) ?> de <?= money($cr['valor_cuota']) ?>
                                </div>
                            </div>
                            <div class="ms-auto text-end">
                                <div class="small text-secondary">Saldo</div>
                                <div class="fw-bold fs-5 money"><?= money($cr['saldo']) ?></div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3 mt-2">
                            <div class="progress flex-fill" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div></div>
                            <span class="small text-secondary text-nowrap"><?= (int) $cr['cuotas_pagadas'] ?>/<?= (int) $cr['num_cuotas'] ?> cuotas · <?= $pct ?> %</span>
                            <?php if ($cr['estado'] === 'activo'): ?>
                                <button type="button" class="btn btn-sm btn-success text-nowrap" data-bs-toggle="modal" data-bs-target="#modalPago"
                                        data-credito="<?= $cr['id'] ?>" data-cliente="<?= e($cr['cliente']) ?>" data-codigo="<?= e($cr['codigo']) ?>"
                                        data-saldo="<?= (int) $cr['saldo'] ?>" data-sugerido="<?= (int) min($cr['saldo'], max($cr['valor_cuota'], $cr['vencido'])) ?>">
                                    <i class="bi bi-cash"></i> Pagar
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php
$volver = 'cliente';
require APP_PATH . '/views/pagos/_modal.php';
?>
