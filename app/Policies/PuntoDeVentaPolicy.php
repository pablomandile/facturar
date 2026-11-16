<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PuntoDeVenta;
use App\Models\User;

/** Los puntos de venta son del emisor de la cuenta. */
class PuntoDeVentaPolicy
{
    public function update(User $usuario, PuntoDeVenta $puntoDeVenta): bool
    {
        return $puntoDeVenta->emisor->usuario_id === $usuario->id;
    }

    public function delete(User $usuario, PuntoDeVenta $puntoDeVenta): bool
    {
        return $this->update($usuario, $puntoDeVenta);
    }
}
