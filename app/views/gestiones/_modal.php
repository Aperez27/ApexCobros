<?php
/** Modal para registrar una gestión de cobro (visita/llamada sin pago o promesa). Variable opcional: $volverGestion */
use App\Models\Gestion;
?>
<div class="modal fade" id="modalGestion" tabindex="-1" aria-labelledby="modalGestionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= url('gestiones/guardar') ?>"<?= ($volverGestion ?? '') === 'ruta' ? ' data-offline="gestion"' : '' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="op_uuid" value="">
            <input type="hidden" name="credito_id" value="">
            <input type="hidden" name="volver" value="<?= e($volverGestion ?? '') ?>">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5 fw-bold" id="modalGestionLabel">Registrar gestión de cobro</h2>
                    <div class="small text-secondary" data-g="cliente"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">¿Cómo se contactó?</label>
                <div class="btn-group w-100 mb-3" role="group" aria-label="Tipo de gestión">
                    <?php foreach (Gestion::tipos() as $k => $v): ?>
                        <input type="radio" class="btn-check" name="tipo" id="gt_<?= $k ?>" value="<?= $k ?>" <?= $k === 'visita' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-primary" for="gt_<?= $k ?>"><?= e($v) ?></label>
                    <?php endforeach; ?>
                </div>
                <label class="form-label" for="gResultado">Resultado</label>
                <select class="form-select mb-3" id="gResultado" name="resultado" required>
                    <?php foreach (Gestion::resultados() as $k => $v): ?>
                        <option value="<?= $k ?>"><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="row g-2 mb-3" data-g="promesa">
                    <div class="col-6">
                        <label class="form-label" for="gFecha">¿Cuándo pagará?</label>
                        <input type="date" class="form-control" id="gFecha" name="fecha_promesa" value="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                               min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="gMonto">¿Cuánto?</label>
                        <div class="input-group"><span class="input-group-text">$</span>
                            <input class="form-control" id="gMonto" name="monto_promesa" data-money inputmode="numeric" placeholder="Opcional"></div>
                    </div>
                    <div class="col-12 form-text">El sistema marcará la promesa como cumplida si el cliente paga ese valor a más tardar en esa fecha.</div>
                </div>
                <label class="form-label" for="gNota">Observación</label>
                <input class="form-control" id="gNota" name="nota" maxlength="255" placeholder="Ej: dice que le pagan el viernes">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary"><i class="bi bi-journal-check"></i> Guardar gestión</button>
            </div>
        </form>
    </div>
</div>
