<?php

namespace App\Models;

use App\Core\Auth;
use App\Core\Model;
use App\Core\Security;

/**
 * Bitácora de auditoría: quién hizo qué, cuándo y desde dónde.
 */
class Auditoria extends Model
{
    protected string $table = 'auditoria';

    public static function acciones(): array
    {
        return [
            'login_ok'          => 'Inicio de sesión',
            'login_fallido'     => 'Inicio de sesión fallido',
            'login_bloqueado'   => 'Ingreso bloqueado (fuerza bruta)',
            'logout'            => 'Cierre de sesión',
            'sesion_expirada'   => 'Sesión expirada / invalidada',
            'clave_cambiada'    => 'Cambio de contraseña',
            'usuario_creado'    => 'Usuario creado',
            'usuario_editado'   => 'Usuario editado',
            'usuario_estado'    => 'Usuario activado/desactivado',
            'usuario_eliminado' => 'Usuario eliminado',
            'cliente_creado'    => 'Cliente creado',
            'cliente_editado'   => 'Cliente editado',
            'cliente_eliminado' => 'Cliente eliminado',
            'cliente_asignado'  => 'Asignación de cartera',
            'credito_creado'    => 'Crédito creado',
            'credito_anulado'   => 'Crédito anulado',
            'pago_registrado'   => 'Pago registrado',
            'pago_anulado'      => 'Pago anulado',
            'config_editada'    => 'Configuración modificada',
            'exportacion'       => 'Exportación de datos',
            'credito_renovado'  => 'Crédito renovado',
            'gestion_registrada' => 'Gestión de cobro',
            'gasto_registrado'  => 'Gasto registrado',
            'gasto_anulado'     => 'Gasto anulado',
            'caja_cerrada'      => 'Cierre de caja',
            'respaldo'          => 'Respaldo de datos',
            'acceso_denegado'   => 'Acceso denegado',
        ];
    }

    /** Registra un evento. Nunca interrumpe la operación principal si falla. */
    public static function log(string $accion, ?string $detalle = null, ?string $entidad = null, ?int $entidadId = null, ?int $usuarioId = null): void
    {
        try {
            $db = \App\Core\Database::connection();
            $db->prepare('INSERT INTO auditoria (usuario_id, accion, detalle, entidad, entidad_id, ip, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())')
                ->execute([$usuarioId ?? Auth::id(), $accion, $detalle !== null ? mb_substr($detalle, 0, 500) : null,
                    $entidad, $entidadId, PHP_SAPI === 'cli' ? 'cli' : Security::ip(), PHP_SAPI === 'cli' ? 'cli' : Security::userAgent()]);
        } catch (\Throwable $e) {
            error_log('Auditoría: ' . $e->getMessage());
        }
    }

    public function listar(array $f, int $limit = 500): array
    {
        $sql = 'SELECT a.*, u.nombre, u.usuario FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id WHERE 1=1';
        $p = [];
        if (!empty($f['desde'])) {
            $sql .= ' AND a.created_at >= ?';
            $p[] = $f['desde'] . ' 00:00:00';
        }
        if (!empty($f['hasta'])) {
            $sql .= ' AND a.created_at <= ?';
            $p[] = $f['hasta'] . ' 23:59:59';
        }
        if (!empty($f['usuario_id'])) {
            $sql .= ' AND a.usuario_id = ?';
            $p[] = (int) $f['usuario_id'];
        }
        if (!empty($f['accion'])) {
            $sql .= ' AND a.accion = ?';
            $p[] = $f['accion'];
        }
        if (!empty($f['q'])) {
            $sql .= ' AND (a.detalle LIKE ? OR a.ip LIKE ?)';
            array_push($p, '%' . $f['q'] . '%', '%' . $f['q'] . '%');
        }
        return $this->fetchAll($sql . ' ORDER BY a.id DESC LIMIT ' . (int) $limit, $p);
    }

    /** Resumen de seguridad de las últimas 24 h. */
    public function resumen24h(): array
    {
        return $this->fetchOne("SELECT
                SUM(accion = 'login_ok') AS ingresos,
                SUM(accion = 'login_fallido') AS fallidos,
                SUM(accion = 'login_bloqueado') AS bloqueos,
                SUM(accion = 'acceso_denegado') AS denegados
            FROM auditoria WHERE created_at >= NOW() - INTERVAL 1 DAY") ?? [];
    }
}
