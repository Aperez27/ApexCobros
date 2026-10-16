<?php
use App\Models\Auditoria;

$acciones = Auditoria::acciones();
$tono = [
    'login_fallido' => 'warning', 'login_bloqueado' => 'danger', 'acceso_denegado' => 'danger', 'sesion_expirada' => 'secondary',
    'login_ok' => 'success', 'logout' => 'secondary', 'pago_anulado' => 'danger', 'credito_anulado' => 'danger',
    'usuario_eliminado' => 'danger', 'cliente_eliminado' => 'danger', 'exportacion' => 'info', 'clave_cambiada' => 'primary',
];
?>
<div class="row g-3 mb-3">
    <div class="col-md-3 col-6"><div class="stat"><span class="stat-icon tone-success"><i class="bi bi-box-arrow-in-right"></i></span><div class="stat-label">Ingresos (24 h)</div><div class="stat-value"><?= (int) ($resumen['ingresos'] ?? 0) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="stat"><span class="stat-icon tone-warning"><i class="bi bi-person-x"></i></span><div class="stat-label">Ingresos fallidos (24 h)</div><div class="stat-value text-warning"><?= (int) ($resumen['fallidos'] ?? 0) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="stat"><span class="stat-icon tone-danger"><i class="bi bi-shield-x"></i></span><div class="stat-label">Bloqueos por fuerza bruta</div><div class="stat-value text-danger"><?= (int) ($resumen['bloqueos'] ?? 0) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="stat"><span class="stat-icon tone-danger"><i class="bi bi-sign-stop"></i></span><div class="stat-label">Accesos denegados</div><div class="stat-value"><?= (int) ($resumen['denegados'] ?? 0) ?></div></div></div>
</div>

<form class="filters row g-2 align-items-end" method="get" action="<?= url('auditoria') ?>">
    <div class="col-lg-2 col-6"><label class="form-label" for="desde">Desde</label><input type="date" class="form-control" id="desde" name="desde" value="<?= e($f['desde']) ?>"></div>
    <div class="col-lg-2 col-6"><label class="form-label" for="hasta">Hasta</label><input type="date" class="form-control" id="hasta" name="hasta" value="<?= e($f['hasta']) ?>"></div>
    <div class="col-lg-2 col-md-4">
        <label class="form-label" for="usuario_id">Usuario</label>
        <select class="form-select" id="usuario_id" name="usuario_id"><option value="">Todos</option>
            <?php foreach ($usuarios as $u): ?><option value="<?= $u['id'] ?>" <?= $f['usuario_id'] == $u['id'] ? 'selected' : '' ?>><?= e($u['nombre']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-lg-3 col-md-4">
        <label class="form-label" for="accion">Acción</label>
        <select class="form-select" id="accion" name="accion"><option value="">Todas</option>
            <?php foreach ($acciones as $k => $v): ?><option value="<?= $k ?>" <?= $f['accion'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-lg-2 col-md-4"><label class="form-label" for="q">Buscar</label><input class="form-control" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Detalle o IP"></div>
    <div class="col-lg-1"><button class="btn btn-primary w-100" aria-label="Filtrar"><i class="bi bi-funnel"></i></button></div>
</form>

<div class="card">
    <div class="card-header"><?= count($eventos) ?> evento(s) <span class="small text-secondary fw-normal">· máximo 500 por consulta</span></div>
    <div class="table-responsive">
        <table class="table table-hover table-sm">
            <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($eventos as $ev): ?>
                <tr>
                    <td class="small text-nowrap"><?= fecha($ev['created_at'], true) ?></td>
                    <td class="small"><?= $ev['nombre'] ? e($ev['nombre']) . '<div class="text-secondary">' . e($ev['usuario']) . '</div>' : '<span class="text-secondary">—</span>' ?></td>
                    <td><span class="badge badge-soft-<?= $tono[$ev['accion']] ?? 'secondary' ?>"><?= e($acciones[$ev['accion']] ?? $ev['accion']) ?></span></td>
                    <td class="small" style="max-width:420px"><?= e($ev['detalle']) ?></td>
                    <td class="small text-nowrap" title="<?= e($ev['user_agent']) ?>"><code><?= e($ev['ip']) ?></code></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$eventos): ?><tr><td colspan="5"><div class="empty"><i class="bi bi-journal-check"></i>Sin eventos en el periodo.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
