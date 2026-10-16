<form method="post" action="<?= url('configuracion/guardar') ?>" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-building text-primary"></i> Datos de la empresa</div>
            <div class="card-body row g-3">
                <div class="col-12"><label class="form-label" for="empresa_nombre">Nombre comercial</label><input class="form-control" id="empresa_nombre" name="empresa_nombre" value="<?= e($cfg['empresa_nombre'] ?? '') ?>" maxlength="120"></div>
                <div class="col-md-6"><label class="form-label" for="empresa_nit">NIT / documento</label><input class="form-control" id="empresa_nit" name="empresa_nit" value="<?= e($cfg['empresa_nit'] ?? '') ?>" maxlength="40"></div>
                <div class="col-md-6"><label class="form-label" for="empresa_telefono">Teléfono</label><input class="form-control" id="empresa_telefono" name="empresa_telefono" value="<?= e($cfg['empresa_telefono'] ?? '') ?>" maxlength="40"></div>
                <div class="col-12"><label class="form-label" for="empresa_direccion">Dirección</label><input class="form-control" id="empresa_direccion" name="empresa_direccion" value="<?= e($cfg['empresa_direccion'] ?? '') ?>" maxlength="180"></div>
                <div class="col-12"><label class="form-label" for="recibo_mensaje">Mensaje al pie del recibo</label><input class="form-control" id="recibo_mensaje" name="recibo_mensaje" value="<?= e($cfg['recibo_mensaje'] ?? '') ?>" maxlength="200"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-sliders text-primary"></i> Valores por defecto de los créditos</div>
            <div class="card-body row g-3">
                <div class="col-md-4"><label class="form-label" for="interes_defecto">Interés (%)</label><input class="form-control" type="number" step="0.01" min="0" max="500" id="interes_defecto" name="interes_defecto" value="<?= e($cfg['interes_defecto'] ?? '20') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="cuotas_defecto">N.º de cuotas</label><input class="form-control" type="number" min="1" max="365" id="cuotas_defecto" name="cuotas_defecto" value="<?= e($cfg['cuotas_defecto'] ?? '30') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="frecuencia_defecto">Frecuencia</label>
                    <select class="form-select" id="frecuencia_defecto" name="frecuencia_defecto">
                        <?php foreach (frecuencias() as $k => $l): ?><option value="<?= $k ?>" <?= ($cfg['frecuencia_defecto'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-12">
                    <div class="alert alert-info small mb-0">
                        <strong>¿Cómo se calcula?</strong> Interés simple sobre el capital: un préstamo de $ 100.000 al 20 % genera un total de $ 120.000, que se divide en cuotas iguales (redondeadas a la centena; la última ajusta la diferencia). Estos valores solo se proponen al crear un crédito; se pueden cambiar en cada uno.
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary px-4"><i class="bi bi-check2-circle"></i> Guardar configuración</button></div>
</form>
