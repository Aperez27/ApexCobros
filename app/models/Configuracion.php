<?php

namespace App\Models;

use App\Core\Model;

class Configuracion extends Model
{
    protected string $table = 'configuracion';

    public function todas(): array
    {
        $rows = $this->fetchAll('SELECT clave, valor FROM configuracion');
        return array_column($rows, 'valor', 'clave');
    }

    public function guardar(array $valores): void
    {
        $st = $this->db->prepare('INSERT INTO configuracion (clave, valor) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
        foreach ($valores as $k => $v) {
            $st->execute([$k, $v]);
        }
    }
}
