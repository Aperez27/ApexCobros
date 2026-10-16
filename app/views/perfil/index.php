<?php use App\Core\Password; ?>
<?php if (!empty($u['debe_cambiar_clave'])): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start">
        <i class="bi bi-shield-lock fs-4"></i>
        <div><strong>Cambio de contraseña obligatorio.</strong> Tu contraseña fue asignada por el administrador o es la contraseña inicial del sistema. Crea una contraseña personal y segura para continuar.</div>
    </div>
<?php endif; ?>
<div class="row g-3 justify-content-center">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body text-center">
                <span class="avatar avatar-lg mb-2"><?= e(iniciales($u['nombre'])) ?></span>
                <h2 class="h5 fw-bold mb-0"><?= e($u['nombre']) ?></h2>
                <div class="text-secondary mb-3"><?= e(App\Models\Usuario::roles()[$u['rol']]) ?> · <?= e($u['usuario']) ?></div>
                <dl class="dl-grid text-start">
                    <dt>Cédula</dt><dd><?= e($u['cedula'] ?: '—') ?></dd>
                    <dt>Teléfono</dt><dd><?= e($u['telefono'] ?: '—') ?></dd>
                    <dt>Correo</dt><dd><?= e($u['email'] ?: '—') ?></dd>
                    <?php if ($u['rol'] === 'cobrador'): ?><dt>Zona</dt><dd><?= e($u['zona'] ?: '—') ?></dd><?php endif; ?>
                    <dt>Último acceso</dt><dd><?= fecha($u['ultimo_acceso'], true) ?></dd>
                    <dt>Clave cambiada</dt><dd><?= $u['clave_actualizada_at'] ? fecha($u['clave_actualizada_at'], true) : 'Nunca' ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <form class="card h-100" method="post" action="<?= url('perfil/clave') ?>">
            <?= csrf_field() ?>
            <div class="card-header"><i class="bi bi-key text-primary"></i> Cambiar contraseña</div>
            <div class="card-body d-grid gap-3">
                <div><label class="form-label" for="actual">Contraseña actual</label><input class="form-control" type="password" id="actual" name="actual" required autocomplete="current-password" maxlength="<?= Password::MAX ?>"></div>
                <div>
                    <label class="form-label" for="nueva">Nueva contraseña</label>
                    <input class="form-control" type="password" id="nueva" name="nueva" required minlength="<?= Password::MIN ?>" maxlength="<?= Password::MAX ?>" autocomplete="new-password" data-strength="#fuerza">
                </div>
                <div><label class="form-label" for="confirmar">Confirmar nueva contraseña</label><input class="form-control" type="password" id="confirmar" name="confirmar" required minlength="<?= Password::MIN ?>" maxlength="<?= Password::MAX ?>" autocomplete="new-password"></div>
                <?php require APP_PATH . '/views/partials/fuerza_clave.php'; ?>
                <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Actualizar contraseña</button>
            </div>
        </form>
    </div>
</div>
