<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PlantillaEmailRequest;
use App\Models\User;
use App\Services\Facturas\TextoDelEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cómo le habla la cuenta a sus clientes: por ahora, el asunto y el texto
 * del email con que salen las facturas (ver TextoDelEmail).
 */
class ComunicacionController extends Controller
{
    public function edit(Request $peticion): Response
    {
        $emisor = $this->usuario($peticion)->emisor;

        return Inertia::render('comunicacion/Index', [
            'email' => [
                'asunto' => $emisor?->asunto_email,
                'asunto_por_defecto' => TextoDelEmail::ASUNTO_POR_DEFECTO,
                'asunto_maximo' => TextoDelEmail::ASUNTO_MAXIMO,
                'plantilla' => $emisor?->plantilla_email,
                'por_defecto' => TextoDelEmail::POR_DEFECTO,
                'maximo' => TextoDelEmail::PLANTILLA_MAXIMA,
                'marcadores' => TextoDelEmail::MARCADORES,
            ],
        ]);
    }

    public function update(PlantillaEmailRequest $peticion): RedirectResponse
    {
        $emisor = $this->usuario($peticion)->emisor;
        assert($emisor !== null);

        $emisor->forceFill([
            'asunto_email' => $peticion->asunto(),
            'plantilla_email' => $peticion->plantilla(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Se guardaron el asunto y el texto del email.']);

        return to_route('comunicacion.edit');
    }

    private function usuario(Request $peticion): User
    {
        $usuario = $peticion->user();
        assert($usuario instanceof User);

        return $usuario;
    }
}
