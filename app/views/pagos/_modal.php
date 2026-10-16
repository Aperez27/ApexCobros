<?php
/** Modal reutilizable para registrar un pago. Variables opcionales: $cobradores, $volver */
use App\Core\Auth;
?>
<div class="modal fade" id="modalPago" tabindex="-1" aria-labelledby="modalPagoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= url('pagos/guardar') ?>"<?= ($volver ?? '') === 'ruta' ? ' data-offline="pago"' : '' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="op_uuid" value="">
            <input type="hidden" name="credito_id" value="">
            <input type="hidden" name="volver" value="<?= e($volver ?? '') ?>">
            <input type="hidden" name="ruta_cobrador" value="<?= (int) ($cobradorId ?? 0) ?>">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5 fw-bold" id="modalPagoLabel">Registrar pago</h2>
                    <div class="small text-secondary"><span data-f="cliente"></span> · <span data-f="codigo"></span></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 tone-primary mb-3">
                    <span class="fw-semibold">Saldo pendiente</span>
                    <span class="fs-5 fw-bold money" data-f="saldo"></span>
                </div>
                <label class="form-label" for="pagoMonto">Valor recibido</label>
                <div class="input-group input-group-lg mb-2">
                    <span class="input-group-text">$</span>
                    <input class="form-control fw-bold" id="pagoMonto" name="monto" data-money inputmode="numeric" required autocomplete="off">
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-light border" data-quick="10000">$ 10.000</button>
                    <button type="button" class="btn btn-sm btn-light border" data-quick="20000">$ 20.000</button>
                    <button type="button" class="btn btn-sm btn-light border" data-quick="50000">$ 50.000</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-quick="saldo">Saldo total</button>
                </div>
                <div class="row g-2">
                    <div class="col-sm-6">
                        <label class="form-label" for="pagoMetodo">Método</label>
                        <select class="form-select" id="pagoMetodo" name="metodo">
                            <?php foreach (metodos_pago() as $k => $v): ?>
                                <option value="<?= $k ?>"><?= e($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (Auth::isAdmin()): ?>
                        <div class="col-sm-6">
                            <label class="form-label" for="pagoFecha">Fecha</label>
                            <input type="date" class="form-control" id="pagoFecha" name="fecha" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="pagoCobrador">Recibido por</label>
                            <select class="form-select" id="pagoCobrador" name="cobrador_id">
                                <option value="">Cobrador asignado al cliente</option>
                                <?php foreach ($cobradores ?? [] as $cb): ?>
                                    <option value="<?= $cb['id'] ?>"><?= e($cb['nombre']) ?></option>
                                <?php endforeach; ?>
                                <option value="<?= Auth::id() ?>">Yo (<?= e(Auth::user()['nombre']) ?>) en oficina</option>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <label class="form-label" for="pagoNota">Nota (opcional)</label>
                        <input class="form-control" id="pagoNota" name="nota" maxlength="255" placeholder="Ej: pagó adelantado la semana">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary px-4"><i class="bi bi-check2-circle"></i> Registrar pago</button>
            </div>
        </form>
    </div>
</div>
