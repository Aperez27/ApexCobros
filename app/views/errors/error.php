<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · APEX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth-panel">
    <div class="auth-card text-center">
        <div class="brand-mark brand-mark-lg mx-auto mb-3">A</div>
        <h1 class="h4 fw-bold"><?= e($title) ?></h1>
        <p class="text-secondary"><?= e($message) ?></p>
        <a href="<?= url('dashboard') ?>" class="btn btn-primary">Volver al inicio</a>
    </div>
</div>
</body>
</html>
