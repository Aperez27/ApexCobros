<?php
/** Hoja de ruta del día: el cobrador la imprime o la guarda en PDF por si se queda sin internet. */
$conCobrador = !$cobradorId;
$pendientes = count(array_filter($items, fn ($i) => $i['a_cobrar'] > 0 && $i['pagado_hoy'] <= 0));
?>
<style>
    @page { size: letter landscape; margin: 10mm; }
    .hoja { max-width: 1100px; }
    .acciones { max-width: 1100px; }
    .tabla-ruta { overflow-x: auto; }
    .tabla-ruta table { min-width: 860px; }
    .tabla-ruta td { vertical-align: top; }
    .tabla-ruta .r, .tabla-ruta tfoot td { white-space: nowrap; }
    .tabla-ruta .num { font-weight: 800; font-size: 14px; color: #3730a3; }
    .tabla-ruta .pagado td { color: #64748b; }
    .tabla-ruta .casilla { display: inline-block; width: 14px; height: 14px; border: 1.5px solid #475569; border-radius: 3px; }
    .tabla-ruta .linea { border-bottom: 1px solid #94a3b8; min-width: 90px; height: 18px; }
    .muted { color: #64748b; font-size: 10.5px; }
    .rojo { color: #b91c1c; font-weight: 700; }
    @media print { .tabla-ruta { overflow: visible; } .tabla-ruta table { min-width: 0; } }
</style>

<h1>HOJA DE RUTA · <?= e(mb_strtoupper(fecha_larga($fecha))) ?></h1>
<div class="sub">
    Cobrador: <strong><?= e($cobrador ?: 'Todos los cobradores') ?></strong>
    · Generada el <?= fecha(date('Y-m-d H:i:s'), true) ?>
    · Orden de visita definido en la ruta de cobro
</div>

<div class="kpis">
    <div class="kpi"><small>Clientes en ruta</small><b><?= $tot['clientes'] ?></b></div>
    <div class="kpi warn"><small>Por cobrar (<?= $pendientes ?>)</small><b><?= money($tot['a_cobrar']) ?></b></div>
    <div class="kpi bad"><small>De eso, atrasado</small><b><?= money($tot['atrasado']) ?></b></div>
    <div class="kpi ok"><small>Ya cobrado</small><b><?= money($tot['cobrado']) ?></b></div>
</div>

<?php if (!$items): ?>
    <p>No hay cobros programados para esta fecha.</p>
<?php else: ?>
<div class="tabla-ruta">
<table>
    <thead>
    <tr>
        <th>#</th><th>Cliente</th><th>Dirección</th><th>Teléfono</th>
        <th class="r">Cuota</th><th class="r">Atrasado</th><th class="r">A cobrar</th><th class="r">Saldo</th>
        <th>Recibido</th><th>Observación</th><th class="c">✓</th>
    </tr>
    </thead>
    <tbody>
    <?php $n = 0; $cobradorActual = null;
    foreach ($items as $i):
        $pagado = $i['pagado_hoy'] > 0;
        if ($conCobrador && $cobradorActual !== ($i['cobrador'] ?? '')):
            $cobradorActual = $i['cobrador'] ?? ''; $n = 0; ?>
            <tr><td colspan="11"><strong><?= e($cobradorActual ?: 'Sin cobrador') ?></strong></td></tr>
        <?php endif; $n++; ?>
        <tr class="<?= $pagado ? 'pagado' : '' ?>">
            <td class="num"><?= $n ?></td>
            <td>
                <strong><?= e($i['cliente']) ?></strong>
                <div class="muted">C.C. <?= e($i['cedula']) ?> · <?= e($i['codigo']) ?></div>
            </td>
            <td><?= e($i['direccion']) ?><?php if ($i['barrio']): ?><div class="muted"><?= e($i['barrio']) ?></div><?php endif; ?></td>
            <td><?= e($i['telefono1']) ?></td>
            <td class="r"><?= money($i['valor_cuota']) ?><div class="muted"><?= e(frecuencias()[$i['frecuencia']]) ?></div></td>
            <td class="r <?= $i['atrasado'] > 0 ? 'rojo' : '' ?>">
                <?= $i['atrasado'] > 0 ? money($i['atrasado']) : '—' ?>
                <?php if ($i['cuotas_atrasadas'] > 0): ?><div class="muted"><?= (int) $i['cuotas_atrasadas'] ?> cuota<?= $i['cuotas_atrasadas'] == 1 ? '' : 's' ?></div><?php endif; ?>
            </td>
            <td class="r"><strong><?= money($i['a_cobrar']) ?></strong></td>
            <td class="r"><?= money($i['saldo']) ?></td>
            <td><?= $pagado ? 'Pagó ' . money($i['pagado_hoy']) : '<div class="linea"></div>' ?></td>
            <td><div class="linea"></div></td>
            <td class="c"><?= $pagado ? '✔' : '<span class="casilla"></span>' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
    <tr>
        <td colspan="5">Totales</td>
        <td class="r"><?= money($tot['atrasado']) ?></td>
        <td class="r"><?= money($tot['a_cobrar']) ?></td>
        <td></td>
        <td colspan="3">Total recibido: ______________</td>
    </tr>
    </tfoot>
</table>
</div>
<?php endif; ?>

<?php if ($promesas): ?>
<h2>Promesas de pago para este día</h2>
<table>
    <thead><tr><th>Cliente</th><th>Nota</th><th class="r">Monto prometido</th></tr></thead>
    <tbody>
    <?php foreach ($promesas as $pr): ?>
        <tr><td><?= e($pr['cliente']) ?></td><td><?= e($pr['nota'] ?? '') ?></td><td class="r"><?= $pr['monto_promesa'] ? money($pr['monto_promesa']) : '—' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<p class="muted" style="margin-top:14px">
    Los cobros anotados en esta hoja deben registrarse en APEX en cuanto haya conexión, para generar el recibo y cuadrar la caja.
</p>
