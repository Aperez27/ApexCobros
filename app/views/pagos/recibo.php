<?php
$msg = 'Recibo ' . $p['recibo'] . ' - ' . empresa() . "\n"
    . 'Cliente: ' . $p['cliente'] . "\n"
    . 'Pago: ' . money($p['monto']) . ' (' . fecha($p['fecha'], true) . ")\n"
    . 'Crédito ' . $p['codigo'] . ' - saldo actual: ' . money($cr['saldo']) . "\n"
    . setting('recibo_mensaje', '');
?>
<div class="ticket">
    <?php if ($p['anulado']): ?><div class="anulado">PAGO ANULADO</div><?php endif; ?>
    <div class="center">
        <div class="brand"><?= e(empresa()) ?></div>
        <?php if (setting('empresa_nit')): ?><div class="muted">NIT <?= e(setting('empresa_nit')) ?></div><?php endif; ?>
        <?php if (setting('empresa_direccion')): ?><div class="muted"><?= e(setting('empresa_direccion')) ?></div><?php endif; ?>
        <?php if (setting('empresa_telefono')): ?><div class="muted">Tel. <?= e(setting('empresa_telefono')) ?></div><?php endif; ?>
    </div>
    <hr class="sep">
    <div class="center"><strong>RECIBO DE PAGO</strong><br><span class="muted"><?= e($p['recibo']) ?></span></div>
    <hr class="sep">
    <div class="row"><span>Fecha</span><strong><?= fecha($p['fecha'], true) ?></strong></div>
    <div class="row"><span>Cliente</span><strong><?= e($p['cliente']) ?></strong></div>
    <div class="row"><span>Cédula</span><span><?= e($p['cedula']) ?></span></div>
    <div class="row"><span>Crédito</span><span><?= e($p['codigo']) ?></span></div>
    <div class="row"><span>Método</span><span><?= e(metodo_label($p['metodo'])) ?></span></div>
    <div class="row"><span>Recibió</span><span><?= e($p['cobrador'] ?? '—') ?></span></div>
    <hr class="sep">
    <div class="center muted">VALOR PAGADO</div>
    <div class="total"><?= money($p['monto']) ?></div>
    <hr class="sep">
    <div class="row"><span>Total crédito</span><span><?= money($cr['total']) ?></span></div>
    <div class="row"><span>Total pagado</span><span><?= money($cr['total_pagado']) ?></span></div>
    <div class="row"><span>Saldo actual</span><strong><?= money($cr['saldo']) ?></strong></div>
    <div class="row"><span>Cuotas pagadas</span><span><?= (int) $cr['cuotas_pagadas'] ?> de <?= (int) $cr['num_cuotas'] ?></span></div>
    <?php if ($p['nota']): ?><div class="row"><span>Nota</span><span><?= e($p['nota']) ?></span></div><?php endif; ?>
    <hr class="sep">
    <?php if (setting('recibo_mensaje')): ?><div class="center"><?= e(setting('recibo_mensaje')) ?></div><?php endif; ?>
    <div class="center muted" style="margin-top:10px">APEX · Desarrollado por Ing. Antony Pérez</div>
</div>
<div class="actions">
    <button class="primary" data-print>Imprimir</button>
    <a href="<?= wa_link($p['telefono1'], $msg) ?>" target="_blank" rel="noopener">Enviar por WhatsApp</a>
    <a href="<?= url('creditos/ver/' . $p['credito_id']) ?>">Volver</a>
</div>
