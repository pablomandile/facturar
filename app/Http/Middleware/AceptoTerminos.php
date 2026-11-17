<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Frena a quien no aceptó la versión vigente de los términos y la privacidad:
 * las cuentas de antes de que existieran los textos y todos cuando se sube
 * `config('legales.version')`. El alta nueva acepta en el registro.
 *
 * Va en todo el grupo `web` y no ruta por ruta: así una ruta nueva nace
 * cubierta, y lo que se exceptúa está a la vista en una sola lista.
 */
class AceptoTerminos
{
    /**
     * Lo que se puede hacer sin aceptar: leer los textos, salir, verificar el
     * email, y los dos derechos que no pueden depender de aceptar nada:
     * llevarse los datos y borrar la cuenta.
     *
     * @var list<string>
     */
    public const EXENTAS = [
        'terminos',
        'terminos.*',
        'privacidad',
        'cookies',
        'home',
        'offline',
        'logout',
        'profile.destroy',
        'exportacion.datos',
        'verification.*',
        // Agrandar la letra para poder leer los términos que hay que aceptar.
        'tamanio-texto.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario instanceof User || $usuario->aceptoTerminosVigentes() || $this->exenta($request)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Antes de seguir tenés que aceptar los términos y la política de privacidad.',
            ], 409);
        }

        // `guest()` guarda la URL pedida, si era un GET, para volver ahí al aceptar.
        return redirect()->guest(route('terminos.aceptar'));
    }

    private function exenta(Request $request): bool
    {
        $nombre = $request->route()?->getName();

        // Sin nombre: rutas de paquetes (.well-known, health), no pantallas.
        return $nombre === null || Str::is(self::EXENTAS, $nombre);
    }
}
