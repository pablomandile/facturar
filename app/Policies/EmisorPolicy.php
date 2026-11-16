<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Emisor;
use App\Models\User;

/** Cada cuenta ve y toca solo su propio perfil fiscal. */
class EmisorPolicy
{
    public function update(User $usuario, Emisor $emisor): bool
    {
        return $emisor->usuario_id === $usuario->id;
    }
}
