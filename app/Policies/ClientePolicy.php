<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

/** Los clientes son del emisor de la cuenta. */
class ClientePolicy
{
    public function update(User $usuario, Cliente $cliente): bool
    {
        return $cliente->emisor->usuario_id === $usuario->id;
    }

    public function delete(User $usuario, Cliente $cliente): bool
    {
        return $this->update($usuario, $cliente);
    }
}
