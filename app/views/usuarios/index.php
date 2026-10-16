<?php use App\Core\Auth; ?>
<form class="filters row g-2 align-items-end" method="get" action="<?= url('usuarios') ?>">
    <div class="col-md-5"><label class="form-label" for="q">Buscar</label><input class="form-control" id="q" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Nombre, usuario, cédula o zona"></div>
    <div class="col-md-3">
        <label class="form-label" for="rol">Rol</label>
        <select class="form-select" id="rol" name="rol"><option value="">Todos</option>
            <?php foreach (App\Models\Usuario::roles() as $k => $v): ?><option value="<?= $k ?>" <?= $rol === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filtrar</button></div>
    <div class="col-md-2"><a href="<?= url('usuarios/crear') ?>" class="btn btn-outline-primary w-100"><i class="bi bi-person-plus"></i> Nuevo</a></div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Usuario</th><th>Acceso</th><th>Rol</th><th>Zona</th><th class="text-center">Clientes</th><th>Último acceso</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr class="<?= $u['activo'] ? '' : 'row-muted' ?>">
                    <td><div class="d-flex align-items-center gap-2"><span class="avatar"><?= e(iniciales($u['nombre'])) ?></span>
                        <div><div class="fw-semibold"><?= e($u['nombre']) ?></div><div class="small text-secondary"><?= e($u['cedula'] ? 'C.C. ' . $u['cedula'] : '') ?> <?= e($u['telefono'] ?? '') ?></div></div></div></td>
                    <td class="small"><code><?= e($u['usuario']) ?></code><div class="text-secondary"><?= e($u['email'] ?? '') ?></div></td>
                    <td><span class="badge <?= $u['rol'] === 'admin' ? 'badge-soft-primary' : 'badge-soft-info' ?>"><?= e(App\Models\Usuario::roles()[$u['rol']]) ?></span></td>
                    <td class="small"><?= e($u['zona'] ?? '—') ?></td>
                    <td class="text-center"><?= $u['rol'] === 'cobrador' ? (int) $u['clientes'] : '—' ?></td>
                    <td class="small"><?= $u['ultimo_acceso'] ? fecha($u['ultimo_acceso'], true) : 'Nunca' ?></td>
                    <td><?= badge($u['activo'] ? 'activo' : 'inactivo') ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($u['rol'] === 'cobrador'): ?><a href="<?= url('cobradores/ver/' . $u['id']) ?>" class="btn btn-sm btn-light border" title="Cartera" aria-label="Ver cartera"><i class="bi bi-wallet2"></i></a><?php endif; ?>
                        <a href="<?= url('usuarios/editar/' . $u['id']) ?>" class="btn btn-sm btn-light border" title="Editar" aria-label="Editar"><i class="bi bi-pencil"></i></a>
                        <?php if ((int) $u['id'] !== Auth::id()): ?>
                            <form method="post" action="<?= url('usuarios/estado/' . $u['id']) ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-light border" title="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>" aria-label="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>"><i class="bi bi-<?= $u['activo'] ? 'pause-circle' : 'play-circle' ?>"></i></button>
                            </form>
                            <form method="post" action="<?= url('usuarios/eliminar/' . $u['id']) ?>" class="d-inline" data-confirm="Se eliminará el usuario de <?= e($u['nombre']) ?>. Si tiene clientes o pagos asociados, el sistema no lo permitirá y deberás desactivarlo." data-confirm-title="¿Eliminar este usuario?" data-confirm-btn="Sí, eliminar">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-light border text-danger" title="Eliminar" aria-label="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$usuarios): ?><tr><td colspan="8"><div class="empty"><i class="bi bi-person-gear"></i>Sin usuarios.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
