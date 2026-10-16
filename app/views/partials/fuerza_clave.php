<?php use App\Core\Password; ?>
<div id="fuerza" class="small">
    <div class="progress mb-2" style="height:.4rem" role="progressbar" aria-label="Fortaleza de la contraseña"><div class="progress-bar" data-bar style="width:0%"></div></div>
    <div class="fw-semibold mb-1" data-label>Escribe una contraseña</div>
    <ul class="list-unstyled mb-0 d-grid gap-1">
        <li data-req="len"><i class="bi bi-circle"></i> Mínimo <?= Password::MIN ?> caracteres</li>
        <li data-req="case"><i class="bi bi-circle"></i> Mayúsculas y minúsculas</li>
        <li data-req="num"><i class="bi bi-circle"></i> Al menos un número</li>
        <li data-req="sym"><i class="bi bi-circle"></i> Al menos un símbolo (# $ % & * ! ?)</li>
        <li class="text-secondary"><i class="bi bi-info-circle"></i> Sin tu usuario, nombre o cédula, ni palabras comunes</li>
    </ul>
</div>
