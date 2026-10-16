<?php
use App\Core\Auth;

$esAdmin = Auth::isAdmin();
$esHoy = $fecha === date('Y-m-d');
$metaRef = $meta > 0 ? $meta : $tot['a_cobrar'] + $tot['cobrado'];
$pct = min(100, porcentaje($tot['cobrado'], $metaRef));
// Los clientes llegan en el orden de visita que definió el cobrador (ver Credito::ruta)
$puedeOrdenar = $cobradorId && count($items) > 1;
$hojaUrl = url('ruta/hoja?' . http_build_query(['fecha' => $fecha, 'cobrador_id' => $esAdmin ? $cobradorId : null]));
?>
<div class="alert alert-warning d-flex gap-2 align-items-start d-none" id="avisoSinConexion" role="status">
    <i class="bi bi-wifi-off fs-5"></i>
    <div>
        <strong>Estás sin conexión.</strong> Lo que cobres o registres queda guardado en este celular y se envía solo cuando vuelva la señal.
    </div>
</div>

<?php if ($ultimoRecibo): ?>
    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i> Pago registrado.
        <a href="<?= url('pagos/recibo/' . $ultimoRecibo) ?>" target="_blank" class="ms-auto btn btn-sm btn-success"><i class="bi bi-printer"></i> Ver recibo</a>
    </div>
<?php endif; ?>

<form class="filters row g-2 align-items-end" method="get" action="<?= url('ruta') ?>">
    <div class="col-md-3 col-6">
        <label class="form-label" for="fecha">Fecha</label>
        <input type="date" class="form-control" id="fecha" name="fecha" value="<?= e($fecha) ?>">
    </div>
    <?php if ($esAdmin): ?>
    <div class="col-md-4 col-6">
        <label class="form-label" for="cobrador_id">Cobrador</label>
        <select class="form-select" id="cobrador_id" name="cobrador_id">
            <option value="">Todos los cobradores</option>
            <?php foreach ($cobradores as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $cobradorId == $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="col-md-3">
        <label class="form-label" for="filtroRuta">Buscar en la ruta</label>
        <input class="form-control" id="filtroRuta" type="search" placeholder="Nombre, barrio…">
    </div>
    <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-arrow-repeat"></i> Ver</button></div>
</form>

<div class="row g-3 mb-3">
    <div class="col-lg-3 col-6"><div class="stat"><div class="stat-label">Clientes a visitar</div><div class="stat-value"><?= $tot['clientes'] ?></div><div class="stat-foot"><?= $tot['visitados'] ?> ya pagaron</div></div></div>
    <div class="col-lg-3 col-6"><div class="stat"><div class="stat-label">Pendiente por cobrar</div><div class="stat-value"><?= money($tot['a_cobrar']) ?></div><div class="stat-foot">incluye <?= money($tot['atrasado']) ?> atrasado</div></div></div>
    <div class="col-lg-3 col-6"><div class="stat"><div class="stat-label">Cobrado <?= $esHoy ? 'hoy' : 'ese día' ?></div><div class="stat-value text-success"><?= money($tot['cobrado']) ?></div><div class="stat-foot">en esta ruta</div></div></div>
    <div class="col-lg-3 col-6"><div class="stat stat-hero"><div class="stat-label"><?= $meta > 0 ? 'Meta diaria' : 'Avance' ?></div><div class="stat-value"><?= $pct ?> %</div>
        <div class="progress mt-2" style="background:rgba(255,255,255,.2)"><div class="progress-bar bg-warning" style="width: <?= $pct ?>%"></div></div>
        <div class="stat-foot mt-1">de <?= money($metaRef) ?></div></div></div>
</div>

<?php if ($enMano !== null || $promesas): ?>
<div class="row g-3 mb-3">
    <?php if ($enMano !== null): ?>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body d-flex align-items-center gap-3">
            <span class="stat-icon position-static tone-warning"><i class="bi bi-wallet2"></i></span>
            <div class="min-w-0"><div class="stat-label">Efectivo en tu poder</div><div class="fs-4 fw-bold money"><?= money($enMano) ?></div></div>
            <a href="<?= url('caja') ?>" class="btn btn-sm btn-outline-primary ms-auto text-nowrap"><i class="bi bi-fuel-pump"></i> Gastos / caja</a>
        </div></div>
    </div>
    <?php endif; ?>
    <?php if ($promesas): ?>
    <div class="col-lg-<?= $enMano !== null ? 8 : 12 ?>">
        <div class="card h-100">
            <div class="card-header py-2"><i class="bi bi-calendar-check text-primary"></i> Promesas de pago para <?= $esHoy ? 'hoy' : fecha($fecha) ?> (<?= count($promesas) ?>)</div>
            <div class="list-group list-group-flush" style="max-height:170px; overflow:auto">
                <?php foreach ($promesas as $pr): ?>
                    <a href="<?= url('creditos/ver/' . $pr['credito_id']) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2">
                        <span class="fw-semibold text-truncate"><?= e($pr['cliente']) ?></span>
                        <span class="small text-secondary d-none d-md-inline"><?= e($pr['nota'] ?? '') ?></span>
                        <span class="ms-auto money fw-semibold"><?= $pr['monto_promesa'] ? money($pr['monto_promesa']) : '' ?></span>
                        <span class="badge badge-soft-<?= ['cumplida' => 'success', 'incumplida' => 'danger', 'pendiente' => 'info'][$pr['estado_promesa']] ?>"><?= ucfirst($pr['estado_promesa']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card mb-3 d-none sync-panel" id="pendientesSync">
    <div class="card-header py-2 d-flex align-items-center gap-2">
        <i class="bi bi-cloud-arrow-up text-warning"></i>
        <span class="fw-semibold">Guardado en este celular, por enviar</span>
        <span class="badge text-bg-warning" data-sync="cantidad">0</span>
        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" data-sync="ahora"><i class="bi bi-arrow-repeat"></i> Enviar ahora</button>
    </div>
    <div class="small px-3 pt-2 text-danger d-none" data-sync="login">
        Tu sesión se cerró. <a href="<?= url('auth/login') ?>">Inicia sesión</a> con tu usuario para enviar lo guardado; no se pierde nada.
    </div>
    <ul class="list-group list-group-flush" data-sync="lista"></ul>
</div>

<?php if (!$items): ?>
    <div class="card"><div class="empty"><i class="bi bi-cup-hot"></i>No hay cobros programados para esta fecha.</div></div>
<?php else: ?>
    <div class="ruta-toolbar d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="me-auto min-w-0">
            <div class="fw-bold"><i class="bi bi-signpost-2 text-primary"></i> Orden de visita</div>
            <div class="small text-secondary" id="ayudaOrden">
                <?= $puedeOrdenar ? 'Pulsa «Organizar ruta» y arrastra cada cliente a su lugar. Los clientes nuevos llegan al final.'
                    : ($cobradorId ? 'Los clientes nuevos llegan al final de la ruta.' : 'Elige un cobrador para organizar su ruta.') ?>
            </div>
        </div>
        <a href="<?= e($hojaUrl) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" id="btnHoja">
            <i class="bi bi-file-earmark-arrow-down"></i> Hoja de ruta (PDF)
        </a>
        <?php if ($puedeOrdenar): ?>
            <button type="button" class="btn btn-sm btn-primary" id="btnOrdenar"><i class="bi bi-arrows-move"></i> Organizar ruta</button>
            <button type="button" class="btn btn-sm btn-success d-none" id="btnGuardarOrden"><i class="bi bi-check2"></i> Guardar orden</button>
            <button type="button" class="btn btn-sm btn-light border d-none" id="btnCancelarOrden">Cancelar</button>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row g-3" id="listaRuta" data-cobrador="<?= (int) $cobradorId ?>" data-fecha="<?= e($fecha) ?>">
    <?php $num = 0; $grupo = null;
    foreach ($items as $i):
        // Con todos los cobradores la numeración arranca de nuevo en cada ruta
        if (!$cobradorId && $grupo !== ($i['cobrador'] ?? '')) { $grupo = $i['cobrador'] ?? ''; $num = 0; }
        $num++;
        $cobrado = $i['pagado_hoy'] > 0;
        $pendiente = (float) $i['a_cobrar'];
        $sugerido = $pendiente > 0 ? min((float) $i['saldo'], $pendiente) : (float) $i['valor_cuota']; ?>
        <div class="col-xl-4 col-md-6 ruta-col" data-cliente="<?= (int) $i['cliente_id'] ?>" data-credito="<?= (int) $i['id'] ?>" data-texto="<?= e(mb_strtolower($i['cliente'] . ' ' . $i['barrio'] . ' ' . $i['direccion'] . ' ' . $i['cedula'])) ?>">
            <div class="ruta-item <?= $cobrado ? 'cobrado' : '' ?> <?= $i['atrasado'] > 0 ? 'atrasado' : '' ?>">
                <div class="ruta-mover">
                    <i class="bi bi-grip-vertical"></i> Arrastra para mover
                    <span class="ms-auto d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-light border py-0" data-mover="-1" aria-label="Subir"><i class="bi bi-arrow-up"></i></button>
                        <button type="button" class="btn btn-sm btn-light border py-0" data-mover="1" aria-label="Bajar"><i class="bi bi-arrow-down"></i></button>
                    </span>
                </div>
                <div class="d-flex align-items-start gap-2 mb-2">
                    <span class="ruta-num" title="Orden de visita"><?= $num ?></span>
                    <span class="avatar"><?= e(iniciales($i['cliente'])) ?></span>
                    <div class="min-w-0">
                        <a href="<?= url('creditos/ver/' . $i['id']) ?>" class="fw-bold text-body text-decoration-none d-block text-truncate"><?= e($i['cliente']) ?></a>
                        <div class="small text-secondary text-truncate"><i class="bi bi-geo-alt"></i> <?= e($i['direccion']) ?><?= $i['barrio'] ? ', ' . e($i['barrio']) : '' ?></div>
                    </div>
                    <?php if ($cobrado): ?><span class="badge badge-soft-success ms-auto">Pagó <?= money($i['pagado_hoy']) ?></span><?php endif; ?>
                </div>
                <div class="d-flex justify-content-between small mb-1"><span class="text-secondary">Cuota</span><span class="money"><?= money($i['valor_cuota']) ?> · <?= e(frecuencias()[$i['frecuencia']]) ?></span></div>
                <div class="d-flex justify-content-between small mb-1"><span class="text-secondary">Pendiente a la fecha</span><strong class="money"><?= money($pendiente) ?></strong></div>
                <?php if ($i['atrasado'] > 0): ?>
                    <div class="d-flex justify-content-between small mb-1 text-danger"><span>Atrasado (<?= (int) $i['cuotas_atrasadas'] ?> cuota<?= $i['cuotas_atrasadas'] == 1 ? '' : 's' ?>)</span><strong class="money"><?= money($i['atrasado']) ?></strong></div>
                <?php endif; ?>
                <div class="d-flex justify-content-between small mb-2"><span class="text-secondary">Saldo total</span><span class="money"><?= money($i['saldo']) ?></span></div>
                <?php $g = $gestiones[$i['id']] ?? null; if ($g && ($g['resultado'] === 'promesa' && $g['estado_promesa'] === 'pendiente' || substr($g['created_at'], 0, 10) === $fecha)): ?>
                    <div class="small mb-2 px-2 py-1 rounded-2 <?= $g['resultado'] === 'promesa' ? 'tone-info' : 'tone-warning' ?>">
                        <i class="bi bi-journal-text"></i>
                        <?php if ($g['resultado'] === 'promesa'): ?>
                            Prometió pagar el <strong><?= fecha($g['fecha_promesa']) ?></strong><?= $g['monto_promesa'] ? ' ' . money($g['monto_promesa']) : '' ?>
                        <?php else: ?>
                            Hoy: <?= e(App\Models\Gestion::resultados()[$g['resultado']]) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="mb-1"></div>
                <?php if ($esAdmin && !$cobradorId): ?><div class="small text-secondary mb-2"><i class="bi bi-person-badge"></i> <?= e($i['cobrador'] ?? 'Sin cobrador') ?></div><?php endif; ?>
                <div class="d-flex gap-2 mt-auto">
                    <a href="tel:<?= e($i['telefono1']) ?>" class="btn btn-sm btn-light border" title="Llamar" aria-label="Llamar"><i class="bi bi-telephone"></i></a>
                    <a href="<?= wa_link($i['telefono1'], 'Hola ' . $i['cliente'] . ', le recordamos su cuota de ' . money($i['valor_cuota']) . ' con ' . empresa() . '. Saldo pendiente a la fecha: ' . money($pendiente) . '.') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-light border text-success" title="WhatsApp" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                    <?php if ($i['saldo'] > 0 && $esHoy): ?>
                        <button type="button" class="btn btn-sm <?= $cobrado ? 'btn-outline-success' : 'btn-success' ?> flex-fill" data-bs-toggle="modal" data-bs-target="#modalPago"
                                data-credito="<?= $i['id'] ?>" data-cliente="<?= e($i['cliente']) ?>" data-codigo="<?= e($i['codigo']) ?>"
                                data-saldo="<?= (int) $i['saldo'] ?>" data-sugerido="<?= (int) $sugerido ?>">
                            <i class="bi bi-cash"></i> <?= $cobrado ? 'Otro abono' : 'Cobrar' ?>
                        </button>
                        <?php if (!$cobrado): ?>
                            <button type="button" class="btn btn-sm btn-light border text-danger" data-bs-toggle="modal" data-bs-target="#modalGestion"
                                    data-credito="<?= $i['id'] ?>" data-cliente="<?= e($i['cliente']) ?>" data-codigo="<?= e($i['codigo']) ?>"
                                    data-sugerido="<?= (int) $sugerido ?>" title="Registrar que no pagó" aria-label="No pagó">
                                <i class="bi bi-x-circle"></i> No pagó
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php
$volver = 'ruta';
require APP_PATH . '/views/pagos/_modal.php';
$volverGestion = 'ruta';
require APP_PATH . '/views/gestiones/_modal.php';
ob_start(); ?>
<?php if ($puedeOrdenar): ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<?php endif; ?>
<script nonce="<?= csp_nonce() ?>">
const filtroRuta = document.getElementById('filtroRuta');
filtroRuta.addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.ruta-col').forEach((el) => { el.classList.toggle('d-none', q !== '' && !el.dataset.texto.includes(q)); });
});

// ---------------------------------------------------------------- organizar el orden de visita
(function () {
    const btn = document.getElementById('btnOrdenar');
    if (!btn) return;
    const lista = document.getElementById('listaRuta');
    const guardar = document.getElementById('btnGuardarOrden');
    const cancelar = document.getElementById('btnCancelarOrden');
    const ayuda = document.getElementById('ayudaOrden');
    const textoAyuda = ayuda.textContent;
    let sortable = null, original = null;

    const numerar = () => lista.querySelectorAll('.ruta-num').forEach((el, i) => { el.textContent = i + 1; });
    const modo = (activo) => {
        lista.classList.toggle('ordenando', activo);
        btn.classList.toggle('d-none', activo);
        guardar.classList.toggle('d-none', !activo);
        cancelar.classList.toggle('d-none', !activo);
        document.getElementById('btnHoja').classList.toggle('d-none', activo);
        ayuda.textContent = activo ? 'Arrastra los clientes (o usa las flechas) y pulsa «Guardar orden».' : textoAyuda;
        if (sortable) sortable.option('disabled', !activo);
    };

    btn.addEventListener('click', () => {
        // Con un filtro activo habría clientes ocultos: se muestran todos para ordenar sin confusiones
        filtroRuta.value = '';
        filtroRuta.dispatchEvent(new Event('input'));
        original = Array.from(lista.children);
        if (!sortable && window.Sortable) {
            sortable = Sortable.create(lista, {
                animation: 150,
                draggable: '.ruta-col',
                filter: '[data-mover]',
                preventOnFilter: false,
                delay: 180, delayOnTouchOnly: true,   // en el celular: mantener presionado para arrastrar, deslizar para hacer scroll
                ghostClass: 'ruta-ghost',
                chosenClass: 'ruta-chosen',
                onEnd: numerar,
            });
        }
        modo(true);
    });

    lista.addEventListener('click', (ev) => {
        const b = ev.target.closest('[data-mover]');
        if (!b || !lista.classList.contains('ordenando')) return;
        const col = b.closest('.ruta-col');
        if (b.dataset.mover === '-1' && col.previousElementSibling) {
            lista.insertBefore(col, col.previousElementSibling);
        } else if (b.dataset.mover === '1' && col.nextElementSibling) {
            lista.insertBefore(col.nextElementSibling, col);
        }
        numerar();
        b.focus();
    });

    cancelar.addEventListener('click', () => {
        original.forEach((el) => lista.appendChild(el));
        numerar();
        modo(false);
    });

    guardar.addEventListener('click', async () => {
        const ids = Array.from(new Set(Array.from(lista.querySelectorAll('.ruta-col')).map((el) => el.dataset.cliente)));
        const cobrador = <?= json_encode((string) ($cobradorId ?? '')) ?>;
        // Sin señal (o con la sesión vencida) el orden se guarda en el celular y se envía al volver la conexión
        const guardarEnCelular = async () => {
            await apexOffline.encolar('orden', { cobrador_id: cobrador, clientes: ids.join(',') }, { texto: 'Nuevo orden de la ruta (' + ids.length + ' clientes)' });
            apexToast('warning', 'Sin conexión: el orden quedó guardado en este celular y se enviará cuando vuelva el internet.', 'Guardado en el celular');
            modo(false);
        };
        guardar.disabled = true;
        try {
            if (!navigator.onLine && window.apexOffline) { await guardarEnCelular(); return; }
            const datos = new FormData();
            datos.append('clientes', ids.join(','));
            datos.append('cobrador_id', cobrador);
            let r;
            try {
                r = await fetch(<?= json_encode(url('ruta/ordenar')) ?>, {
                    method: 'POST',
                    body: datos,
                    headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json', 'X-Apex-Fetch': '1' },
                    credentials: 'same-origin',
                });
            } catch (e) {
                if (window.apexOffline) { await guardarEnCelular(); return; }
                throw new Error('No fue posible guardar el orden. Revisa tu conexión.');
            }
            const res = await r.json().catch(() => null);
            if ((!res || res.redirect) && window.apexOffline) { await guardarEnCelular(); return; }
            if (!res || !res.ok) throw new Error((res && res.mensaje) || 'No fue posible guardar el orden.');
            apexToast('success', res.mensaje || 'Orden guardado.');
            modo(false);
        } catch (e) {
            apexToast('danger', e.message || 'No fue posible guardar el orden. Revisa tu conexión.');
        } finally {
            guardar.disabled = false;
        }
    });
})();
</script>
<?php $scripts = ob_get_clean(); ?>
