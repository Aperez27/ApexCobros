<div class="certificado">
    <h1>PAZ Y SALVO</h1>
    <div class="sub">N.º PS-<?= e(substr($cr['codigo'], 3)) ?>-<?= date('Ymd') ?></div>

    <p style="margin-top:30px"><?= e(empresa()) ?><?= setting('empresa_nit') ? ', identificada con NIT ' . e(setting('empresa_nit')) . ',' : '' ?>
        <strong>CERTIFICA</strong> que el(la) señor(a) <strong><?= e(mb_strtoupper($cr['cliente'])) ?></strong>, identificado(a) con cédula de ciudadanía
        número <strong><?= e($cr['cedula']) ?></strong>, se encuentra a <strong>PAZ Y SALVO</strong> por todo concepto con relación al crédito
        <strong><?= e($cr['codigo']) ?></strong>, otorgado el <?= fecha_larga($cr['fecha_inicio']) ?> por un valor de
        <strong><?= money($cr['monto']) ?></strong>, el cual fue cancelado en su totalidad
        (<?= money($cr['total']) ?> incluidos los intereses pactados)<?= $ultimoPago ? ', con último pago registrado el ' . fecha_larga($ultimoPago) : '' ?>.</p>

    <p>Por lo anterior, el(la) titular no tiene obligaciones pendientes derivadas de este crédito con nuestra entidad.</p>

    <p>Se expide a solicitud del interesado el <?= fecha_larga(date('Y-m-d')) ?>.</p>

    <div class="sello">PAGADO</div>

    <div class="firma">
        <div class="linea"><?= e(empresa()) ?></div>
        <div style="color:#64748b">Firma autorizada<?= setting('empresa_telefono') ? ' · Tel. ' . e(setting('empresa_telefono')) : '' ?></div>
    </div>
</div>
