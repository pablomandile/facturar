<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PuntoDeVentaGuardarRequest;
use App\Models\PuntoDeVenta;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Los puntos de venta "Web Services" del emisor. Se cargan a mano: tienen
 * que existir en ARCA con el mismo número, y en homologación ARCA ni
 * siquiera los lista.
 */
class PuntoDeVentaController extends Controller
{
    public function store(PuntoDeVentaGuardarRequest $peticion): RedirectResponse
    {
        $usuario = $peticion->user();
        assert($usuario instanceof User && $usuario->emisor !== null);
        $emisor = $usuario->emisor;

        $punto = new PuntoDeVenta($peticion->validated());
        $punto->emisor_id = $emisor->id;
        $punto->ambiente = $emisor->ambiente;
        $punto->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Se agregó el punto de venta {$punto->numero}."]);

        return to_route('emisor.edit');
    }

    public function update(PuntoDeVentaGuardarRequest $peticion, PuntoDeVenta $puntoDeVenta): RedirectResponse
    {
        $puntoDeVenta->update($peticion->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Se guardaron los cambios.']);

        return to_route('emisor.edit');
    }

    public function destroy(PuntoDeVenta $puntoDeVenta): RedirectResponse
    {
        $this->authorize('delete', $puntoDeVenta);
        $puntoDeVenta->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Se quitó el punto de venta {$puntoDeVenta->numero}."]);

        return to_route('emisor.edit');
    }
}
