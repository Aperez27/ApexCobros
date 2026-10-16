<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Auditoria;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Gestion;
use App\Models\OperacionDuplicada;
use App\Models\OperacionOffline;
use App\Models\Pago;

/**
 * Sincronización de lo que el cobrador registró sin internet (assets/js/offline.js).
 * El celular envía las operaciones una a una, en el orden en que se hicieron. Cada una lleva un uuid:
 * si llega repetida se responde "ya registrada" sin volver a guardarla.
 *
 * Respuestas: {ok:true, mensaje}  |  {ok:false, definitivo:true, mensaje}  (el servidor la rechaza:
 * el cobrador debe revisarla)  |  {redirect}  (sesión vencida: hay que volver a ingresar).
 */
class SincronizarController extends Controller
{
    /** Máximo tiempo que puede pasar entre el cobro en la calle y su sincronización para conservar su hora. */
    private const DIAS_MAXIMOS = 7;

    /** Confirma que la sesión sigue activa y entrega un token CSRF fresco para sincronizar. */
    public function estado(): void
    {
        $this->requireLogin();
        $this->json(['ok' => true, 'usuario_id' => Auth::id(), 'csrf' => csrf_token(), 'hora' => date('Y-m-d H:i:s')]);
    }

    public function operacion(): void
    {
        $this->requireLogin();
        $this->requirePost();
        $uuid = (string) $this->input('uuid', '');
        $tipo = (string) $this->input('tipo', '');
        $datos = json_decode((string) $this->input('datos', ''), true);
        if (!is_array($datos) || !in_array($tipo, ['pago', 'gestion', 'orden'], true)
            || ($tipo !== 'orden' && !OperacionOffline::uuidValido($uuid))) {
            $this->rechazar('La operación guardada en el celular está incompleta.');
        }
        $creadoEn = $this->creadoEn((string) $this->input('creado_en', ''));

        if ($tipo !== 'orden' && ($previa = (new OperacionOffline())->buscar($uuid))) {
            $this->yaRegistrada($previa);
        }
        try {
            match ($tipo) {
                'pago'    => $this->pago($uuid, $datos, $creadoEn),
                'gestion' => $this->gestion($uuid, $datos, $creadoEn),
                'orden'   => $this->orden($datos),
            };
        } catch (OperacionDuplicada $e) {
            $this->yaRegistrada($e->operacion);
        }
    }

    private function pago(string $uuid, array $d, ?string $creadoEn): never
    {
        $cr = $this->credito((int) ($d['credito_id'] ?? 0));
        $monto = parse_money((string) ($d['monto'] ?? ''));
        if ($monto === null || $monto <= 0) {
            $this->rechazar('El valor del pago no es válido.');
        }
        $metodo = (string) ($d['metodo'] ?? 'efectivo');
        if (!array_key_exists($metodo, metodos_pago())) {
            $metodo = 'efectivo';
        }
        $cobradorId = Auth::id();
        if (Auth::isAdmin()) {
            $cobradorId = (int) ($d['cobrador_id'] ?? 0) ?: ((int) $cr['cobrador_id'] ?: Auth::id());
        }

        // El pago conserva la hora en que se cobró en la calle, salvo que ese día ya se haya cerrado la caja:
        // en ese caso entra con la hora actual para no descuadrar un cierre ya entregado.
        $nota = 'Registrado sin conexión';
        $fecha = date('Y-m-d H:i:s');
        if ($creadoEn) {
            if ((new Caja())->diaCerrado((int) $cobradorId, substr($creadoEn, 0, 10))) {
                $nota .= ', cobrado el ' . fecha($creadoEn, true) . ' (ese día ya tenía cierre de caja)';
            } else {
                $fecha = $creadoEn;
            }
        }
        $extra = trim((string) ($d['nota'] ?? ''));
        $nota = mb_substr($nota . ($extra !== '' ? ' · ' . $extra : ''), 0, 255);

        try {
            $id = (new Pago())->registrar((int) $cr['id'], $monto, $metodo, $cobradorId, $fecha, $nota,
                ['uuid' => $uuid, 'usuario_id' => Auth::id(), 'creado_en' => $creadoEn, 'sin_conexion' => true]);
        } catch (\DomainException $e) {
            $this->rechazar($e->getMessage());
        }
        Auditoria::log('pago_registrado', $cr['codigo'] . ' · ' . money($monto) . ' · ' . $metodo . ' · fecha ' . $fecha
            . ' · sin conexión (sincronizado ' . date('Y-m-d H:i:s') . ')', 'pago', $id);
        $this->json(['ok' => true, 'mensaje' => 'Pago de ' . money($monto) . ' a ' . $cr['cliente'] . ' registrado.',
            'recibo' => sprintf('RC-%06d', $id), 'pago_id' => $id]);
    }

    private function gestion(string $uuid, array $d, ?string $creadoEn): never
    {
        $cr = $this->credito((int) ($d['credito_id'] ?? 0));
        $tipo = (string) ($d['tipo'] ?? 'visita');
        $resultado = (string) ($d['resultado'] ?? '');
        if (!array_key_exists($tipo, Gestion::tipos()) || !array_key_exists($resultado, Gestion::resultados())) {
            $this->rechazar('El tipo o el resultado de la gestión no es válido.');
        }
        $fechaPromesa = null;
        $montoPromesa = null;
        if ($resultado === 'promesa') {
            // Las fechas se validan contra el día en que se hizo la gestión, no contra el de sincronización
            $base = substr($creadoEn ?? date('Y-m-d'), 0, 10);
            $fechaPromesa = (string) ($d['fecha_promesa'] ?? '');
            $f = \DateTime::createFromFormat('Y-m-d', $fechaPromesa);
            if (!$f || $f->format('Y-m-d') !== $fechaPromesa || $fechaPromesa < $base
                || $fechaPromesa > date('Y-m-d', strtotime($base . ' +30 days'))) {
                $this->rechazar('La fecha de la promesa debe estar dentro de los 30 días siguientes a la visita.');
            }
            $montoPromesa = parse_money((string) ($d['monto_promesa'] ?? ''));
            if ($montoPromesa === null || $montoPromesa > (float) $cr['saldo']) {
                $this->rechazar('El valor prometido no es válido o supera el saldo.');
            }
        }
        $nota = mb_substr(trim((string) ($d['nota'] ?? '')), 0, 255);
        $id = (new Gestion())->registrar((int) $cr['id'], Auth::id(), $tipo, $resultado, $fechaPromesa, $montoPromesa ?: null, $nota,
            ['uuid' => $uuid, 'usuario_id' => Auth::id(), 'creado_en' => $creadoEn, 'sin_conexion' => true]);
        Auditoria::log('gestion_registrada', $cr['codigo'] . ' · ' . Gestion::resultados()[$resultado] . ' · sin conexión', 'gestion', $id);
        $this->json(['ok' => true, 'mensaje' => 'Gestión de ' . $cr['cliente'] . ' registrada.']);
    }

    private function orden(array $d): never
    {
        $cobradorId = Auth::isAdmin() ? (int) ($d['cobrador_id'] ?? 0) : (int) Auth::id();
        $ids = array_filter(array_map('intval', explode(',', (string) ($d['clientes'] ?? ''))));
        if ($cobradorId <= 0 || !$ids || count($ids) > 2000) {
            $this->rechazar('El orden de la ruta guardado no es válido.');
        }
        $n = (new Cliente())->ordenarRuta($cobradorId, $ids);
        Auditoria::log('ruta_ordenada', "Orden de ruta actualizado ({$n} clientes) · sin conexión", 'usuarios', $cobradorId);
        $this->json(['ok' => true, 'mensaje' => 'Orden de la ruta guardado.']);
    }

    /** Crédito activo que el usuario puede gestionar; si no, rechaza la operación. */
    private function credito(int $id): array
    {
        $cr = (new Credito())->detalle($id);
        if (!$cr || (!Auth::isAdmin() && (int) $cr['cobrador_id'] !== Auth::id())) {
            $this->rechazar('El crédito ya no está asignado a tu ruta.');
        }
        if ($cr['estado'] !== 'activo') {
            $this->rechazar('El crédito ' . $cr['codigo'] . ' ya no está activo (' . $cr['estado'] . ').');
        }
        return $cr;
    }

    /** Hora del celular: se acepta solo si es coherente (no futura ni demasiado antigua). */
    private function creadoEn(string $valor): ?string
    {
        $d = \DateTime::createFromFormat('Y-m-d H:i:s', $valor);
        if (!$d || $d->format('Y-m-d H:i:s') !== $valor) {
            return null;
        }
        $ts = $d->getTimestamp();
        return $ts <= time() + 600 && $ts >= time() - self::DIAS_MAXIMOS * 86400 ? min($valor, date('Y-m-d H:i:s')) : null;
    }

    private function yaRegistrada(?array $op): never
    {
        $this->json(['ok' => true, 'duplicado' => true, 'mensaje' => 'Ya estaba registrada.',
            'recibo' => ($op['tipo'] ?? '') === 'pago' && $op['entidad_id'] ? sprintf('RC-%06d', $op['entidad_id']) : null]);
    }

    private function rechazar(string $mensaje): never
    {
        $this->json(['ok' => false, 'definitivo' => true, 'mensaje' => $mensaje], 422);
    }
}
