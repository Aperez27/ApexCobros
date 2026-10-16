<?php

namespace App\Models;

use App\Core\Model;

/**
 * Identificadores únicos de las operaciones hechas desde la ruta (cobros y gestiones).
 * El celular genera el uuid antes de enviar; si la operación llega dos veces —porque se reintentó
 * tras perder la señal o se sincronizó desde dos pestañas— solo la primera se registra.
 */
class OperacionOffline extends Model
{
    protected string $table = 'operaciones_offline';

    public static function uuidValido(string $uuid): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid);
    }

    public function buscar(string $uuid): ?array
    {
        return $this->fetchOne('SELECT * FROM operaciones_offline WHERE uuid = ?', [$uuid]);
    }

    /**
     * Reserva el uuid. Debe llamarse dentro de la transacción que crea el pago o la gestión,
     * así ambos quedan guardados o ninguno. Lanza OperacionDuplicada si ya existía.
     */
    public function reservar(string $uuid, ?int $usuarioId, string $tipo, ?string $creadoEn, bool $sinConexion): void
    {
        try {
            $this->db->prepare('INSERT INTO operaciones_offline (uuid, usuario_id, tipo, creado_en, sin_conexion, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())')->execute([$uuid, $usuarioId, $tipo, $creadoEn, (int) $sinConexion]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new OperacionDuplicada($this->buscar($uuid));
            }
            throw $e;
        }
    }

    public function completar(string $uuid, int $entidadId): void
    {
        $this->db->prepare('UPDATE operaciones_offline SET entidad_id = ? WHERE uuid = ?')->execute([$entidadId, $uuid]);
    }
}
