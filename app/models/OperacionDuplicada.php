<?php

namespace App\Models;

/** La operación (mismo uuid) ya se había registrado: no se vuelve a guardar. */
class OperacionDuplicada extends \RuntimeException
{
    public function __construct(public readonly ?array $operacion)
    {
        parent::__construct('Esta operación ya estaba registrada.');
    }
}
