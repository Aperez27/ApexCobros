<div class="auth-wrap">
    <div class="auth-hero d-none d-lg-flex">
        <div>
            <div class="brand-mark brand-mark-lg mb-4">A</div>
            <h1 class="display-6 fw-bold text-white mb-3">Control total de tu cartera de cobros.</h1>
            <p class="text-white-50 fs-5 mb-4">Clientes, créditos, cuotas y cobradores en un solo lugar. Sabe en tiempo real cuánto dinero tiene cada cobrador en la calle.</p>
            <ul class="list-unstyled text-white-50 d-grid gap-2">
                <li><i class="bi bi-check2-circle text-success me-2"></i>Plan de cuotas automático: diario, semanal, quincenal o mensual</li>
                <li><i class="bi bi-check2-circle text-success me-2"></i>Ruta de cobro del día y control de mora</li>
                <li><i class="bi bi-check2-circle text-success me-2"></i>Recibos imprimibles y reportes a Excel</li>
            </ul>
        </div>
        <div class="small text-white-50">© <?= date('Y') ?> APEX · Desarrollado por Ing. Antony Pérez</div>
    </div>
    <div class="auth-panel">
        <div class="auth-card">
            <div class="d-flex align-items-center gap-2 mb-4">
                <span class="brand-mark">A</span>
                <div>
                    <div class="fw-bold fs-5 lh-1">APEX</div>
                    <div class="small text-secondary">Sistema de control de cobros</div>
                </div>
            </div>
            <h2 class="h4 fw-bold mb-1">Iniciar sesión</h2>
            <p class="text-secondary mb-4">Ingresa con tu usuario o correo.</p>

            <?php $modoFlash = 'inline'; require APP_PATH . '/views/partials/flashes.php'; ?>

            <form method="post" action="<?= url('auth/login') ?>">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="usuario">Usuario</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input class="form-control" id="usuario" name="usuario" value="<?= e(old('usuario')) ?>" required autofocus autocomplete="username">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
                        <button class="btn btn-outline-secondary" type="button" id="verClave" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
                <button class="btn btn-primary w-100 btn-lg">Entrar <i class="bi bi-arrow-right"></i></button>
            </form>
            <div class="text-center small text-secondary mt-4 d-lg-none">© <?= date('Y') ?> APEX · Desarrollado por Ing. Antony Pérez</div>
        </div>
    </div>
</div>
<script nonce="<?= csp_nonce() ?>">
document.getElementById('verClave').addEventListener('click', function () {
    const p = document.getElementById('password');
    p.type = p.type === 'password' ? 'text' : 'password';
});
</script>
