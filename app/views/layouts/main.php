<?php
use App\Core\Auth;

$user = Auth::user();
$menu = [
    ['Operación', null],
    ['dashboard', 'grid-1x2', 'Panel', null],
    ['ruta', 'signpost-split', 'Ruta de cobro', null],
    ['clientes', 'people', 'Clientes', null],
    ['creditos', 'cash-coin', 'Créditos', null],
    ['pagos', 'receipt', 'Pagos', null],
    ['caja', 'wallet2', 'Caja', null],
    ['Administración', ['admin']],
    ['cobradores', 'person-badge', 'Cobradores y cartera', ['admin']],
    ['reportes', 'bar-chart-line', 'Reportes', ['admin']],
    ['usuarios', 'person-gear', 'Usuarios', ['admin']],
    ['auditoria', 'shield-check', 'Auditoría', ['admin']],
    ['respaldo', 'database-down', 'Respaldo', ['admin']],
    ['configuracion', 'sliders', 'Configuración', ['admin']],
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <meta name="base-url" content="<?= e(url()) ?>">
    <meta name="usuario-id" content="<?= (int) Auth::id() ?>">
    <meta name="usuario-rol" content="<?= e($user['rol'] ?? '') ?>">
    <meta name="apex-copia" content="0">
    <meta name="theme-color" content="#3730a3">
    <title><?= e($title ?? '') ?> · APEX</title>
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="manifest" href="<?= url('manifest.webmanifest') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="<?= url('dashboard') ?>">
            <span class="brand-mark">A</span>
            <span class="min-w-0">
                <span class="brand-name">APEX</span>
                <span class="brand-sub text-truncate"><?= e(empresa()) ?></span>
            </span>
        </a>
        <nav class="side-nav">
            <?php foreach ($menu as $item): ?>
                <?php if (count($item) === 2): [$label, $roles] = $item; ?>
                    <?php if ($roles === null || Auth::hasRole(...$roles)): ?>
                        <div class="nav-section"><?= e($label) ?></div>
                    <?php endif; ?>
                <?php else: [$path, $icon, $label, $roles] = $item; ?>
                    <?php if ($roles === null || Auth::hasRole(...$roles)): ?>
                        <a class="side-link <?= is_active($path) ?>" href="<?= url($path) ?>">
                            <i class="bi bi-<?= $icon ?>"></i><span><?= e($label) ?></span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!Auth::isAdmin()): ?>
                <a class="side-link <?= is_active('cobradores') ?>" href="<?= url('cobradores/ver/' . Auth::id()) ?>">
                    <i class="bi bi-briefcase"></i><span>Mi cartera</span>
                </a>
            <?php endif; ?>
            <a class="side-link <?= is_active('ayuda') ?>" href="<?= url('ayuda') ?>">
                <i class="bi bi-question-circle"></i><span>Ayuda</span>
            </a>
        </nav>
        <div class="side-user">
            <a href="<?= url('perfil') ?>" class="d-flex align-items-center gap-2 text-decoration-none min-w-0">
                <span class="avatar avatar-sm"><?= e(iniciales($user['nombre'])) ?></span>
                <span class="min-w-0">
                    <span class="d-block fw-semibold text-white text-truncate"><?= e($user['nombre']) ?></span>
                    <span class="d-block small text-white-50"><?= e(App\Models\Usuario::roles()[$user['rol']] ?? $user['rol']) ?></span>
                </span>
            </a>
            <form method="post" action="<?= url('auth/logout') ?>" class="ms-auto">
                <?= csrf_field() ?>
                <button class="btn btn-icon-dark" title="Cerrar sesión" aria-label="Cerrar sesión"><i class="bi bi-box-arrow-right"></i></button>
            </form>
        </div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main">
        <header class="topbar">
            <button class="btn btn-link text-body d-lg-none p-0 me-2" id="toggleSidebar" aria-label="Menú">
                <i class="bi bi-list fs-3"></i>
            </button>
            <div class="min-w-0">
                <h1 class="page-title text-truncate"><?= e($title ?? '') ?></h1>
                <div class="page-date d-none d-sm-block"><?= e(ucfirst(fecha_larga(date('Y-m-d')))) ?></div>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a href="<?= url('ruta') ?>#pendientesSync" class="btn btn-sm conexion-pill d-none" id="estadoConexion" role="status" aria-live="polite"></a>
                <a href="<?= url('clientes/crear') ?>" class="btn btn-sm btn-light border"><i class="bi bi-person-plus"></i> <span class="d-none d-md-inline">Cliente</span></a>
                <?php if (Auth::isAdmin()): ?>
                    <a href="<?= url('creditos/crear') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> <span class="d-none d-sm-inline">Nuevo crédito</span></a>
                <?php else: ?>
                    <a href="<?= url('ruta') ?>" class="btn btn-sm btn-primary"><i class="bi bi-signpost-split"></i> <span class="d-none d-sm-inline">Mi ruta</span></a>
                <?php endif; ?>
            </div>
        </header>

        <main class="content">
            <?php $modoFlash = 'toast'; require APP_PATH . '/views/partials/flashes.php'; ?>

            <?= $content ?>
        </main>

        <footer class="app-footer">
            © <?= date('Y') ?> <strong>APEX</strong> · Sistema de control de cobros · Desarrollado por <strong>Ing. Antony Pérez</strong>
        </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($charts)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<?php endif; ?>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/offline.js') ?>"></script>
<?= $scripts ?? '' ?>
</body>
</html>
