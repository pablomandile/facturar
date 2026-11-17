<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TamanioTextoUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;

class TamanioTextoController extends Controller
{
    /**
     * Se guarda en los dos lados: la cookie evita el salto de tamaño en la
     * próxima carga y la cuenta lo lleva a otro dispositivo. No pide sesión:
     * quien no llega a leer el login es quien más necesita agrandar la letra.
     */
    public function update(TamanioTextoUpdateRequest $request): RedirectResponse
    {
        $tamanio = $request->tamanio();

        $request->user()?->forceFill(['tamanio_texto' => $tamanio])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Listo, cambiamos el tamaño de la letra.']);

        return back()->withCookie(Cookie::forever('tamanio_texto', $tamanio->value));
    }
}
