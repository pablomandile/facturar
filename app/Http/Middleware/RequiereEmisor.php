<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clientes y comprobantes cuelgan del emisor: sin datos fiscales cargados no
 * hay a nombre de quién facturar. Manda a completarlos primero.
 */
class RequiereEmisor
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = $peticion->user();

        if ($usuario instanceof User && $usuario->emisor === null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Primero completá tus datos fiscales.']);

            return to_route('emisor.edit');
        }

        return $siguiente($peticion);
    }
}
