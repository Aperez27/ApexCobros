<?php
$editando = !empty($u['id']);
$v = fn (string $k) => old($k, $u[$k] ?? '');
$rolSel = $v('rol') ?: 'cobrador';
?>
<form method="post" action="<?= $editando ? url('usuarios/actualizar/' . $u['id']) : url('usuarios/guardar') ?>" class="row justify-content-center">
    <?= csrf_field() ?>
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person-gear text-primary"></i> <?= $editando ? 'Editar ' . e($u['nombre']) : 'Nuevo usuario' ?></div>
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label d-block">Rol</label>
                    <div class="btn-group" role="group" aria-label="Rol">
                        <?php foreach (App\Models\Usuario::roles() as $k => $lbl): ?>
                            <input type="radio" class="btn-check" name="rol" id="rol_<?= $k ?>" value="<?= $k ?>" <?= $rolSel === $k ? 'checked' : '' ?>>
                            <label class="btn btn-outline-primary" for="rol_<?= $k ?>"><i class="bi bi-<?= $k === 'admin' ? 'shield-lock' : 'person-badge' ?>"></i> <?= $lbl ?></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text">El <strong>cobrador</strong> solo ve sus clientes, su ruta y registra pagos. El <strong>administrador</strong> gestiona todo.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="nombre">Nombre completo <span class="text-danger">*</span></label>
                    <input class="form-control" id="nombre" name="nombre" value="<?= e($v('nombre')) ?>" required maxlength="120" autofocus>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="cedula">Cédula <span class="text-danger js-cob">*</span></label>
                    <input class="form-control" id="cedula" name="cedula" value="<?= e($v('cedula')) ?>" maxlength="20" inputmode="numeric">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="telefono">Teléfono <span class="text-danger js-cob">*</span></label>
                    <input class="form-control" id="telefono" name="telefono" value="<?= e($v('telefono')) ?>" maxlength="20" inputmode="tel">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Correo</label>
                    <input class="form-control" type="email" id="email" name="email" value="<?= e($v('email')) ?>" maxlength="120">
                </div>
                <div class="col-md-6 js-cob-field">
                    <label class="form-label" for="zona">Zona / ruta</label>
                    <input class="form-control" id="zona" name="zona" value="<?= e($v('zona')) ?>" maxlength="100" placeholder="Ej: Norte, Centro, Barrio San José">
                </div>
                <div class="col-md-6 js-cob-field">
                    <label class="form-label" for="meta_diaria">Meta de recaudo diario</label>
                    <div class="input-group"><span class="input-group-text">$</span>
                        <input class="form-control" id="meta_diaria" name="meta_diaria" data-money inputmode="numeric" value="<?= e((int) $v('meta_diaria') ?: '') ?>" placeholder="Opcional"></div>
                    <div class="form-text">Si se deja vacío, la meta es lo que vence ese día.</div>
                </div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-key text-primary"></i> Acceso al sistema</div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="usuario">Usuario <span class="text-danger">*</span></label>
                    <input class="form-control" id="usuario" name="usuario" value="<?= e($v('usuario')) ?>" required maxlength="50" pattern="[A-Za-z0-9._\-]{3,50}" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password">Contraseña <?= $editando ? '<span class="text-secondary fw-normal">(vacío = no cambiar)</span>' : '<span class="text-danger">*</span>' ?></label>
                    <input class="form-control" type="password" id="password" name="password" minlength="<?= App\Core\Password::MIN ?>" maxlength="<?= App\Core\Password::MAX ?>" <?= $editando ? '' : 'required' ?> autocomplete="new-password" data-strength="#fuerza">
                    <div class="form-text">El usuario deberá cambiarla por una propia en su primer ingreso.</div>
                </div>
                <div class="col-12"><?php require APP_PATH . '/views/partials/fuerza_clave.php'; ?></div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" <?= $v('activo') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activo">Usuario activo (puede iniciar sesión)</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 justify-content-end">
            <a href="<?= url('usuarios') ?>" class="btn btn-light border">Cancelar</a>
            <button class="btn btn-primary px-4"><i class="bi bi-check2-circle"></i> Guardar</button>
        </div>
    </div>
</form>
<?php ob_start(); ?>
<script nonce="<?= csp_nonce() ?>">
(function () {
    const upd = () => {
        const cob = document.getElementById('rol_cobrador').checked;
        document.querySelectorAll('.js-cob').forEach((el) => el.classList.toggle('d-none', !cob));
        document.querySelectorAll('.js-cob-field').forEach((el) => el.classList.toggle('d-none', !cob));
        document.getElementById('cedula').required = cob;
        document.getElementById('telefono').required = cob;
    };
    document.querySelectorAll('[name=rol]').forEach((r) => r.addEventListener('change', upd));
    upd();
})();
</script>
<?php $scripts = ob_get_clean(); ?>
