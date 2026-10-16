<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="apex-copia" content="0">
    <title><?= e($title ?? '') ?> · APEX</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= asset('css/documento.css') ?>" rel="stylesheet">
</head>
<body>
    <div class="acciones">
        <button type="button" class="btn primario" data-print>Imprimir / Guardar PDF</button>
        <?php [$volverUrl, $volverTexto] = $volver ?? ['creditos/ver/' . (int) ($cr['id'] ?? 0), 'Volver al crédito']; ?>
        <a class="btn" href="<?= url($volverUrl) ?>"><?= e($volverTexto) ?></a>
    </div>
    <main class="hoja">
        <header class="membrete">
            <div>
                <div class="empresa"><?= e(empresa()) ?></div>
                <div class="datos">
                    <?= setting('empresa_nit') ? 'NIT ' . e(setting('empresa_nit')) . ' · ' : '' ?>
                    <?= e(setting('empresa_direccion', '')) ?>
                    <?= setting('empresa_telefono') ? ' · Tel. ' . e(setting('empresa_telefono')) : '' ?>
                </div>
            </div>
            <div class="marca">A</div>
        </header>
        <?= $content ?>
        <footer class="pie">Documento generado el <?= fecha(date('Y-m-d H:i:s'), true) ?> · APEX · Desarrollado por Ing. Antony Pérez</footer>
    </main>
    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
