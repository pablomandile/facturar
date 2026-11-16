<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Arca\ProbadorDeConexion;
use App\Support\Arca\ChequeoDeEntorno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * "Probar la conexión" en Datos fiscales. El detalle de cada paso vuelve
 * como flash (`pruebaConexion`) y el resumen como toast.
 */
class ConexionController extends Controller
{
    public function store(Request $peticion, ProbadorDeConexion $probador): RedirectResponse
    {
        $usuario = $peticion->user();
        abort_unless($usuario instanceof User && $usuario->emisor !== null, 403);

        // Hasta tres llamadas a ARCA en serie, la primera con pedido de ticket.
        set_time_limit(120);

        $pasos = $probador->probar($usuario->emisor);
        $todoBien = array_reduce($pasos, fn (bool $ok, ChequeoDeEntorno $paso): bool => $ok && $paso->ok, true);

        Inertia::flash('pruebaConexion', array_map(fn (ChequeoDeEntorno $paso): array => [
            'nombre' => $paso->nombre,
            'ok' => $paso->ok,
            'detalle' => $paso->detalle,
        ], $pasos));

        Inertia::flash('toast', $todoBien
            ? ['type' => 'success', 'message' => 'La conexión con ARCA anda. Ya podés facturar.']
            : ['type' => 'error', 'message' => 'La conexión con ARCA tiene un problema: mirá el detalle.']);

        return to_route('emisor.edit');
    }
}
