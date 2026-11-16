<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Comprobante;
use App\Models\User;

/**
 * Los comprobantes son del emisor de la cuenta. Uno autorizado no se edita
 * ni se borra (se anula con una nota de crédito, en una etapa posterior);
 * uno pendiente tampoco, hasta saber qué hizo ARCA con él.
 */
class ComprobantePolicy
{
    public function view(User $usuario, Comprobante $comprobante): bool
    {
        return $comprobante->emisor->usuario_id === $usuario->id;
    }

    public function update(User $usuario, Comprobante $comprobante): bool
    {
        return $this->view($usuario, $comprobante) && $comprobante->estado->esEditable();
    }

    public function emitir(User $usuario, Comprobante $comprobante): bool
    {
        return $this->view($usuario, $comprobante) && $comprobante->estado->esEmitible();
    }

    public function delete(User $usuario, Comprobante $comprobante): bool
    {
        return $this->update($usuario, $comprobante);
    }
}
