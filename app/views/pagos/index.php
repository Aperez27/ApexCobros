<?php
use App\Core\Auth;

$esAdmin = Auth::isAdmin();
?>
<form class="filters row g-2 align-items-end" method="get" action="<?= url('pagos') ?>">
    <div class="col-lg-2 col-md-4 col-6">
        <label class="form-label" for="desde">Desde</label>
        <input type="date" class="form-control" id="desde" name="desde" value="<?= e($f['desde']) ?>">
    </div>
    <div class="col-lg-2 col-md-4 col-6">
        <label class="form-label" for="hasta">Hasta</label>
        <input type="date" class="form-control" id="hasta" name="hasta" value="<?= e($f['hasta']) ?>">
    </div>
    <?php if ($esAdmin): ?>
    <div class="col-lg-2 col-md-4 col-6">
        <label class="form-label" for="cobrador_id">Recibido por</label>
        <select class="form-select" id="cobrador_id" name="cobrador_id">
            <option value="">Todos</option>
            <?php foreach ($cobradores as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $f['cobrador_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="col-lg-2 col-md-4 col-6">
        <label class="form-label" for="metodo">Método</label>
        <select class="form-select" id="metodo" name="metodo">
            <option value="">Todos</option>
            <?php foreach (metodos_pago() as $k => $v): ?>
                <option value="<?= $k ?>" <?= $f['metodo'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-lg-2 col-md-4">
        <label class="form-label" for="q">Buscar</label>
        <input class="form-control" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Recibo, cliente…">
    </div>
    <div class="col-lg-2 col-md-4 d-flex gap-2 align-items-center">
        <button class="btn btn-primary flex-fill"><i class="bi bi-funnel"></i> Filtrar</button>
        <div class="form-check ms-1" title="Incluir anulados">
            <input class="form-check-input" type="checkbox" id="anulados" name="anulados" value="1" <?= $f['anulados'] ? 'checked' : '' ?>>
            <label class="form-check-label small" for="anulados">Anulados</label>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-4 col-6"><div class="stat stat-hero"><div class="stat-label">Total recaudado</div><div class="stat-value"><?= money($totales['total'] ?? 0) ?></div><div class="stat-foot"><?= fecha($f['desde']) ?> — <?= fecha($f['hasta']) ?></div></div></div>
    <div class="col-md-4 col-6"><div class="stat"><div class="stat-label">Pagos</div><div class="stat-value"><?= (int) ($totales['cantidad'] ?? 0) ?></div><div class="stat-foot">sin contar anulados</div></div></div>
    <div class="col-md-4"><div class="stat"><div class="stat-label">Promedio por pago</div><div class="stat-value"><?= money(($totales['cantidad'] ?? 0) ? $totales['total'] / $totales['cantidad'] : 0) ?></div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Recibo</th><th>Fecha</th><th>Cliente</th><th>Crédito</th><?php if ($esAdmin): ?><th>Recibido por</th><?php endif; ?><th>Método</th><th class="text-end">Valor</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pagos as $p): ?>
                <tr class="<?= $p['anulado'] ? 'row-muted' : '' ?>">
                    <td class="fw-semibold"><?= e($p['recibo']) ?><?= $p['anulado'] ? ' <span class="badge badge-soft-danger">Anulado</span>' : '' ?></td>
                    <td class="small"><?= fecha($p['fecha'], true) ?></td>
                    <td><a href="<?= url('clientes/ver/' . $p['cliente_id']) ?>" class="text-body text-decoration-none fw-semibold"><?= e($p['cliente']) ?></a><div class="small text-secondary"><?= e($p['cedula']) ?></div></td>
                    <td><a href="<?= url('creditos/ver/' . $p['credito_id']) ?>" class="text-decoration-none"><?= e($p['codigo']) ?></a></td>
                    <?php if ($esAdmin): ?><td class="small"><?= e($p['cobrador'] ?? '—') ?></td><?php endif; ?>
                    <td class="small"><?= e(metodo_label($p['metodo'])) ?></td>
                    <td class="text-end fw-bold money <?= $p['anulado'] ? 'text-decoration-line-through' : 'text-success' ?>"><?= money($p['monto']) ?></td>
                    <td class="text-end"><a href="<?= url('pagos/recibo/' . $p['id']) ?>" target="_blank" class="btn btn-sm btn-light border" title="Recibo" aria-label="Ver recibo"><i class="bi bi-printer"></i></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pagos): ?>
                <tr><td colspan="8"><div class="empty"><i class="bi bi-receipt"></i>No hay pagos en ese rango.</div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
