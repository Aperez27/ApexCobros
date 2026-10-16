<?php
$sel = (int) old('cliente_id', $clienteId);
$hoy = date('Y-m-d');
?>
<form method="post" action="<?= url('creditos/guardar') ?>" class="row g-3" id="formCredito">
    <?= csrf_field() ?>
    <div class="col-xl-5">
        <?php if ($renovar): $calR = $calificaciones[(int) $renovar['cliente_id']] ?? null; ?>
            <input type="hidden" name="renovar_id" value="<?= (int) $renovar['id'] ?>">
            <input type="hidden" name="cliente_id" value="<?= (int) $renovar['cliente_id'] ?>">
            <div class="card mb-3 border-primary">
                <div class="card-header"><i class="bi bi-arrow-repeat text-primary"></i> Renovación del crédito <?= e($renovar['codigo']) ?></div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="avatar"><?= e(iniciales($renovar['cliente'])) ?></span>
                        <div><div class="fw-semibold"><?= e($renovar['cliente']) ?> <?= calificacion_badge($calR, true) ?></div><div class="small text-secondary">C.C. <?= e($renovar['cedula']) ?> · <?= e($renovar['cobrador'] ?? '') ?></div></div>
                    </div>
                    <div class="d-flex justify-content-between"><span class="text-secondary">Saldo pendiente actual</span><strong class="money" id="saldoRenovar" data-saldo="<?= (int) $renovar['saldo'] ?>"><?= money($renovar['saldo']) ?></strong></div>
                    <div class="d-flex justify-content-between"><span class="text-secondary">Cuotas pagadas</span><span><?= (int) $renovar['cuotas_pagadas'] ?> de <?= (int) $renovar['num_cuotas'] ?></span></div>
                    <div class="d-flex justify-content-between fs-5 border-top mt-2 pt-2"><span class="fw-semibold">Efectivo a entregar</span><strong class="money text-success" id="netoRenovar">—</strong></div>
                    <div class="form-text">El saldo pendiente se descuenta del nuevo monto y el crédito <?= e($renovar['codigo']) ?> queda cancelado.</div>
                    <?php if (($calR['letra'] ?? '') === 'D'): ?><div class="alert alert-danger py-2 small mt-2 mb-0"><i class="bi bi-exclamation-triangle"></i> Cliente con calificación D (riesgoso). Revisa su historial antes de renovar.</div><?php endif; ?>
                </div>
            </div>
        <?php else: ?>
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person text-primary"></i> Cliente</div>
            <div class="card-body">
                <label class="form-label" for="cliente_id">Cliente <span class="text-danger">*</span></label>
                <input class="form-control mb-2" type="search" id="buscarCliente" placeholder="Escribe para filtrar por nombre o cédula…" aria-label="Filtrar clientes">
                <select class="form-select" id="cliente_id" name="cliente_id" required size="6">
                    <?php foreach ($clientes as $c): $cal = $calificaciones[(int) $c['id']] ?? null; $letra = $cal['letra'] ?? 'N'; ?>
                        <option value="<?= $c['id'] ?>" data-calif="<?= $letra ?>" <?= $sel === (int) $c['id'] ? 'selected' : '' ?> <?= $c['estado'] === 'bloqueado' || !$c['cobrador_id'] ? 'disabled' : '' ?>>
                            <?= e($c['nombre']) ?> · <?= e($c['cedula']) ?> · Calif. <?= $letra ?><?= $c['estado'] === 'bloqueado' ? ' (bloqueado)' : (!$c['cobrador_id'] ? ' (sin cobrador)' : '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="alert alert-danger py-2 small mt-2 mb-0 d-none" id="alertaCalif"><i class="bi bi-exclamation-triangle"></i> Este cliente tiene calificación <strong>D (riesgoso)</strong>: mora alta o incumplimientos. Se recomienda no prestarle.</div>
                <div class="form-text">¿No está? <a href="<?= url('clientes/crear') ?>">Registrar cliente nuevo</a>. Los clientes sin cobrador no se pueden seleccionar. Calificación: A excelente · B bueno · C regular · D riesgoso · N sin historial.</div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-calculator text-primary"></i> Condiciones</div>
            <div class="card-body row g-3">
                <div class="col-sm-6">
                    <label class="form-label" for="monto">Monto a prestar <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input class="form-control fw-bold" id="monto" name="monto" data-money inputmode="numeric" required value="<?= e(old('monto')) ?>" placeholder="0">
                    </div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="interes">Interés total (%)</label>
                    <div class="input-group">
                        <input class="form-control" id="interes" name="interes" type="number" step="0.01" min="0" max="500" value="<?= e(old('interes', setting('interes_defecto', '20'))) ?>">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="frecuencia">Frecuencia de pago</label>
                    <select class="form-select" id="frecuencia" name="frecuencia">
                        <?php foreach (frecuencias() as $k => $v): ?>
                            <option value="<?= $k ?>" <?= old('frecuencia', setting('frecuencia_defecto', 'diaria')) === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="num_cuotas">Número de cuotas</label>
                    <input class="form-control" id="num_cuotas" name="num_cuotas" type="number" min="1" max="365" required value="<?= e(old('num_cuotas', setting('cuotas_defecto', '30'))) ?>">
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="fecha_inicio">Fecha del préstamo</label>
                    <input class="form-control" id="fecha_inicio" name="fecha_inicio" type="date" value="<?= e(old('fecha_inicio', $hoy)) ?>" required>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="fecha_primera_cuota">Primera cuota</label>
                    <input class="form-control" id="fecha_primera_cuota" name="fecha_primera_cuota" type="date" value="<?= e(old('fecha_primera_cuota', date('Y-m-d', strtotime('+1 day')))) ?>" required>
                </div>
                <div class="col-12" id="wrapDomingos">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="excluir_domingos" name="excluir_domingos" value="1" <?= old('excluir_domingos', '1') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="excluir_domingos">No cobrar los domingos</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="observaciones">Observaciones</label>
                    <textarea class="form-control" id="observaciones" name="observaciones" rows="2" maxlength="2000"><?= e(old('observaciones')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card">
            <div class="card-header d-flex align-items-center"><i class="bi bi-calendar3 text-primary me-1"></i> Plan de pagos <span class="small text-secondary ms-2 fw-normal">(vista previa)</span></div>
            <div class="card-body">
                <div class="row g-2 mb-3 text-center">
                    <div class="col-6 col-md-3"><div class="p-2 rounded-3 tone-dark"><div class="small">Capital</div><div class="fw-bold money" id="pCapital">—</div></div></div>
                    <div class="col-6 col-md-3"><div class="p-2 rounded-3 tone-warning"><div class="small">Interés</div><div class="fw-bold money" id="pInteres">—</div></div></div>
                    <div class="col-6 col-md-3"><div class="p-2 rounded-3 tone-primary"><div class="small">Total a pagar</div><div class="fw-bold money" id="pTotal">—</div></div></div>
                    <div class="col-6 col-md-3"><div class="p-2 rounded-3 tone-success"><div class="small">Valor cuota</div><div class="fw-bold money" id="pCuota">—</div></div></div>
                </div>
                <div class="small text-secondary mb-2" id="pRango"></div>
                <div class="alert alert-danger d-none py-2" id="pError"></div>
                <div class="table-responsive" style="max-height: 380px; overflow:auto">
                    <table class="table table-sm">
                        <thead class="sticky-top"><tr><th>#</th><th>Fecha</th><th>Día</th><th class="text-end">Valor</th></tr></thead>
                        <tbody id="pCuotas"><tr><td colspan="4" class="text-secondary text-center py-4">Ingresa el monto para ver el plan.</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-transparent d-flex gap-2 justify-content-end">
                <a href="<?= url('creditos') ?>" class="btn btn-light border">Cancelar</a>
                <button class="btn btn-primary btn-lg px-4"><i class="bi bi-check2-circle"></i> <?= $renovar ? 'Renovar crédito' : 'Crear crédito' ?></button>
            </div>
        </div>
    </div>
</form>

<?php ob_start(); ?>
<script nonce="<?= csp_nonce() ?>">
(function () {
    const form = document.getElementById('formCredito');
    const $ = (id) => document.getElementById(id);
    const dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    const fmtFecha = (s) => { const [y, m, d] = s.split('-'); return d + '/' + m + '/' + y; };
    let timer;

    // Filtro del select de clientes y alerta de calificación
    if ($('buscarCliente')) {
        $('buscarCliente').addEventListener('input', function () {
            const q = this.value.toLowerCase();
            [...$('cliente_id').options].forEach((o) => { o.hidden = q && !o.text.toLowerCase().includes(q); });
        });
        const alerta = () => {
            const o = $('cliente_id').selectedOptions[0];
            $('alertaCalif').classList.toggle('d-none', !o || o.dataset.calif !== 'D');
        };
        $('cliente_id').addEventListener('change', alerta);
        alerta();
    }

    // Renovación: efectivo a entregar = nuevo monto − saldo pendiente
    if ($('saldoRenovar')) {
        const saldo = Number($('saldoRenovar').dataset.saldo);
        const neto = () => {
            const m = Number(($('monto').value || '').replace(/\D/g, ''));
            const n = m - saldo;
            $('netoRenovar').textContent = m ? (n > 0 ? apexMoney(n) : 'El monto debe superar el saldo') : '—';
            $('netoRenovar').className = 'money ' + (n > 0 ? 'text-success' : 'text-danger');
        };
        $('monto').addEventListener('input', neto);
        neto();
    }

    // Primera cuota = día siguiente al préstamo (si el usuario no la cambió a mano)
    let primeraManual = false;
    $('fecha_primera_cuota').addEventListener('change', () => { primeraManual = true; });
    $('fecha_inicio').addEventListener('change', () => {
        if (primeraManual) return;
        const d = new Date($('fecha_inicio').value + 'T12:00:00');
        const f = $('frecuencia').value;
        d.setDate(d.getDate() + (f === 'diaria' ? 1 : f === 'semanal' ? 7 : f === 'quincenal' ? 15 : 30));
        $('fecha_primera_cuota').value = d.toISOString().slice(0, 10);
    });
    $('frecuencia').addEventListener('change', () => {
        $('wrapDomingos').classList.toggle('d-none', $('frecuencia').value !== 'diaria');
        $('fecha_inicio').dispatchEvent(new Event('change'));
    });
    $('wrapDomingos').classList.toggle('d-none', $('frecuencia').value !== 'diaria');

    function simular() {
        const fd = new FormData(form);
        fd.delete('_csrf');
        const params = new URLSearchParams(fd);
        if (!$('monto').value) return;
        fetch('<?= url('creditos/simular') ?>?' + params.toString(), { headers: { 'Accept': 'application/json' } })
            .then((r) => r.json())
            .then((r) => {
                $('pError').classList.toggle('d-none', r.ok);
                if (!r.ok) { $('pError').textContent = r.error; return; }
                const p = r.plan;
                $('pCapital').textContent = apexMoney(p.total - p.interes_valor);
                $('pInteres').textContent = apexMoney(p.interes_valor);
                $('pTotal').textContent = apexMoney(p.total);
                $('pCuota').textContent = apexMoney(p.valor_cuota);
                $('pRango').textContent = p.cuotas.length + ' cuotas, del ' + fmtFecha(p.cuotas[0].fecha) + ' al ' + fmtFecha(p.fecha_fin) + '.';
                $('pCuotas').innerHTML = p.cuotas.map((c) => {
                    const d = new Date(c.fecha + 'T12:00:00');
                    return '<tr><td>' + c.numero + '</td><td>' + fmtFecha(c.fecha) + '</td><td class="text-secondary">' + dias[d.getDay()] + '</td><td class="text-end money">' + apexMoney(c.valor) + '</td></tr>';
                }).join('');
            })
            .catch(() => {});
    }
    form.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(simular, 300); });
    form.addEventListener('change', () => { clearTimeout(timer); timer = setTimeout(simular, 150); });
    simular();
})();
</script>
<?php $scripts = ob_get_clean(); ?>
