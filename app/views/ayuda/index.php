<div class="row g-3 mb-3">
    <?php foreach ($manuales as $clave => $m): ?>
        <div class="col-xl-4 col-md-6">
            <div class="card cob-card h-100">
                <div class="card-body d-flex gap-3">
                    <span class="stat-icon position-static tone-primary flex-shrink-0"><i class="bi bi-<?= e($m['icono']) ?>"></i></span>
                    <div class="d-flex flex-column">
                        <div class="fw-bold"><?= e($m['titulo']) ?></div>
                        <div class="small text-secondary mb-3"><?= e($m['desc']) ?></div>
                        <div class="d-flex gap-2 mt-auto">
                            <a href="<?= url('ayuda/ver/' . $clave) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-primary"><i class="bi bi-book"></i> Abrir</a>
                            <a href="<?= url('ayuda/ver/' . $clave . '?descargar=1') ?>" class="btn btn-sm btn-light border"><i class="bi bi-download"></i> Descargar</a>
                            <span class="small text-secondary align-self-center ms-auto">PDF · <?= number_format($m['kb'] / 1024, 1, ',', '.') ?> MB</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$manuales): ?>
        <div class="col-12"><div class="card"><div class="empty"><i class="bi bi-book"></i>Los manuales no están disponibles en este servidor.</div></div></div>
    <?php endif; ?>
</div>
<div class="alert alert-info">
    <strong>¿Necesitas ayuda?</strong> Comunícate con el administrador del sistema. Para problemas técnicos, consulta el manual de instalación o al soporte técnico.
</div>
