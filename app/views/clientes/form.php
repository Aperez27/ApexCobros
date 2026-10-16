<?php
use App\Core\Auth;

$editando = !empty($c['id']);
$v = fn (string $k) => old($k, $c[$k] ?? '');
$action = $editando ? url('clientes/actualizar/' . $c['id']) : url('clientes/guardar');
?>
<form method="post" action="<?= $action ?>" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person-vcard text-primary"></i> Datos personales</div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="cedula">Cédula <span class="text-danger">*</span></label>
                    <input class="form-control" id="cedula" name="cedula" value="<?= e($v('cedula')) ?>" required maxlength="20" inputmode="numeric" autofocus>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="nombres">Nombres <span class="text-danger">*</span></label>
                    <input class="form-control" id="nombres" name="nombres" value="<?= e($v('nombres')) ?>" required maxlength="80">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="apellidos">Apellidos <span class="text-danger">*</span></label>
                    <input class="form-control" id="apellidos" name="apellidos" value="<?= e($v('apellidos')) ?>" required maxlength="80">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="telefono1">Teléfono principal <span class="text-danger">*</span></label>
                    <input class="form-control" id="telefono1" name="telefono1" value="<?= e($v('telefono1')) ?>" required maxlength="20" inputmode="tel" placeholder="300 123 4567">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="telefono2">Teléfono secundario</label>
                    <input class="form-control" id="telefono2" name="telefono2" value="<?= e($v('telefono2')) ?>" maxlength="20" inputmode="tel">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="email">Correo <span class="text-secondary fw-normal">(opcional)</span></label>
                    <input class="form-control" type="email" id="email" name="email" value="<?= e($v('email')) ?>" maxlength="120">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="fecha_nacimiento">Fecha de nacimiento</label>
                    <input class="form-control" type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= e($v('fecha_nacimiento')) ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="ocupacion">Ocupación / actividad</label>
                    <input class="form-control" id="ocupacion" name="ocupacion" value="<?= e($v('ocupacion')) ?>" maxlength="100" placeholder="Ej: comerciante, tendero, empleado">
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-geo-alt text-primary"></i> Ubicación</div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="direccion">Dirección de residencia <span class="text-danger">*</span></label>
                    <input class="form-control" id="direccion" name="direccion" value="<?= e($v('direccion')) ?>" required maxlength="180" placeholder="Calle 10 # 20-30">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="barrio">Barrio</label>
                    <input class="form-control" id="barrio" name="barrio" value="<?= e($v('barrio')) ?>" maxlength="100">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="ciudad">Ciudad</label>
                    <input class="form-control" id="ciudad" name="ciudad" value="<?= e($v('ciudad')) ?>" maxlength="80">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="negocio_nombre">Nombre del negocio / trabajo</label>
                    <input class="form-control" id="negocio_nombre" name="negocio_nombre" value="<?= e($v('negocio_nombre')) ?>" maxlength="120">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="negocio_direccion">Dirección del negocio / trabajo</label>
                    <input class="form-control" id="negocio_direccion" name="negocio_direccion" value="<?= e($v('negocio_direccion')) ?>" maxlength="180">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-people text-primary"></i> Referencia personal</div>
            <div class="card-body row g-3">
                <div class="col-md-5">
                    <label class="form-label" for="referencia_nombre">Nombre</label>
                    <input class="form-control" id="referencia_nombre" name="referencia_nombre" value="<?= e($v('referencia_nombre')) ?>" maxlength="120">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="referencia_telefono">Teléfono</label>
                    <input class="form-control" id="referencia_telefono" name="referencia_telefono" value="<?= e($v('referencia_telefono')) ?>" maxlength="20" inputmode="tel">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="referencia_parentesco">Parentesco</label>
                    <input class="form-control" id="referencia_parentesco" name="referencia_parentesco" value="<?= e($v('referencia_parentesco')) ?>" maxlength="50" list="parentescos">
                    <datalist id="parentescos"><option>Familiar</option><option>Esposo(a)</option><option>Hermano(a)</option><option>Amigo(a)</option><option>Vecino(a)</option><option>Compañero de trabajo</option></datalist>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person-badge text-primary"></i> Asignación</div>
            <div class="card-body">
                <?php if (Auth::isAdmin()): ?>
                    <label class="form-label" for="cobrador_id">Cobrador responsable</label>
                    <select class="form-select mb-1" id="cobrador_id" name="cobrador_id">
                        <option value="">— Sin asignar —</option>
                        <?php foreach ($cobradores as $cb): ?>
                            <option value="<?= $cb['id'] ?>" <?= (string) $v('cobrador_id') === (string) $cb['id'] ? 'selected' : '' ?>>
                                <?= e($cb['nombre']) ?><?= $cb['zona'] ? ' · ' . e($cb['zona']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text mb-3">El cobrador verá a este cliente en su ruta y su cartera.</div>

                    <label class="form-label" for="estado">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <?php foreach (App\Models\Cliente::estados() as $k => $lbl): ?>
                            <option value="<?= $k ?>" <?= $v('estado') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar"><?= e(iniciales(Auth::user()['nombre'])) ?></span>
                        <div><div class="fw-semibold"><?= e(Auth::user()['nombre']) ?></div><div class="small text-secondary">El cliente queda en tu cartera</div></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-journal-text text-primary"></i> Observaciones</div>
            <div class="card-body">
                <textarea class="form-control" name="observaciones" rows="5" maxlength="2000" aria-label="Observaciones" placeholder="Horario para cobrar, puntos de referencia, comportamiento de pago…"><?= e($v('observaciones')) ?></textarea>
            </div>
        </div>

        <div class="d-grid gap-2">
            <button class="btn btn-primary btn-lg"><i class="bi bi-check2-circle"></i> <?= $editando ? 'Guardar cambios' : 'Registrar cliente' ?></button>
            <a href="<?= $editando ? url('clientes/ver/' . $c['id']) : url('clientes') ?>" class="btn btn-light border">Cancelar</a>
        </div>
    </div>
</form>
