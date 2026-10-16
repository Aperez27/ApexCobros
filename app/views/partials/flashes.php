<?php
/**
 * Mensajes del sistema.
 *  $modoFlash = 'toast'  → notificaciones flotantes (pantallas internas)
 *  $modoFlash = 'inline' → aviso dentro del formulario (login)
 * Sin JavaScript se siguen viendo; el JS solo agrega el cierre automático.
 */
$tonos = [
    'success' => ['check-circle-fill', 'Operación exitosa', 5500],
    'info'    => ['info-circle-fill', 'Información', 6000],
    'warning' => ['exclamation-triangle-fill', 'Atención', 8000],
    'danger'  => ['x-octagon-fill', 'No se pudo completar', 9000],
];
$lista = flashes();
$modoFlash ??= 'toast';
?>
<?php if ($modoFlash === 'inline'): ?>
    <?php foreach ($lista as $f): [$icono, $titulo] = $tonos[$f['type']] ?? $tonos['info']; ?>
        <div class="alert alert-<?= e($f['type']) ?> aviso" role="<?= in_array($f['type'], ['danger', 'warning'], true) ? 'alert' : 'status' ?>">
            <i class="bi bi-<?= $icono ?>"></i>
            <div><div class="aviso-titulo"><?= e($titulo) ?></div><?= e($f['message']) ?></div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="toast-stack" id="toastStack" aria-live="polite" aria-atomic="false">
        <?php foreach ($lista as $f): [$icono, $titulo, $ms] = $tonos[$f['type']] ?? $tonos['info']; ?>
            <div class="apex-toast <?= e($f['type']) ?>" role="<?= in_array($f['type'], ['danger', 'warning'], true) ? 'alert' : 'status' ?>" data-timeout="<?= $ms ?>">
                <div class="apex-toast-icon"><i class="bi bi-<?= $icono ?>"></i></div>
                <div class="min-w-0">
                    <div class="apex-toast-title"><?= e($titulo) ?></div>
                    <div class="apex-toast-msg"><?= e($f['message']) ?></div>
                </div>
                <button type="button" class="apex-toast-close" aria-label="Cerrar notificación"><i class="bi bi-x-lg"></i></button>
                <div class="apex-toast-progress"></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
