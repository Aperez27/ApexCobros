<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="stat-icon position-static tone-primary"><i class="bi bi-database-down"></i></span>
                    <div><h2 class="h5 fw-bold mb-0">Copia de seguridad</h2>
                        <div class="small text-secondary">Último respaldo: <?= $ultimo ? fecha($ultimo['created_at'], true) . ' por ' . e($ultimo['nombre'] ?? '—') : 'nunca' ?></div></div>
                </div>
                <p>Descarga un archivo <code>.sql</code> con <strong>toda la información</strong> del sistema: clientes, créditos, cuotas, pagos, cobradores, caja y auditoría.</p>
                <ul class="small text-secondary">
                    <li>Haz un respaldo <strong>al menos una vez al día</strong> y guárdalo fuera de este computador (USB, Google Drive, correo).</li>
                    <li>El archivo contiene datos personales y contraseñas cifradas: guárdalo en un lugar seguro.</li>
                    <li>Para restaurar: phpMyAdmin → base de datos <code>apex_cobros_db</code> → Importar → selecciona el archivo.</li>
                </ul>
                <form method="post" action="<?= url('respaldo/descargar') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary btn-lg w-100"><i class="bi bi-download"></i> Descargar respaldo ahora</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Contenido de la base de datos</div>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Tabla</th><th class="text-end">Registros (aprox.)</th><th class="text-end">Tamaño</th></tr></thead>
                    <tbody>
                    <?php foreach ($tablas as $t): ?>
                        <tr><td><code><?= e($t['nombre']) ?></code></td><td class="text-end"><?= number_format((int) $t['filas'], 0, ',', '.') ?></td><td class="text-end"><?= number_format((int) $t['kb'], 0, ',', '.') ?> KB</td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
