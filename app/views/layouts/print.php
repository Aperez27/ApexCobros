<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="apex-copia" content="0">
    <title><?= e($title ?? '') ?> · APEX</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= asset('css/print.css') ?>" rel="stylesheet">
</head>
<body>
    <?= $content ?>
    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
