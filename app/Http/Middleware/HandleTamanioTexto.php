<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\TamanioTexto;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el tamaño de letra antes de renderizar, para que `data-texto` ya
 * viaje en el HTML de arranque: sin script inline y sin parpadeo.
 */
class HandleTamanioTexto
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('tamanioTexto', $this->resolver($request));

        return $next($request);
    }

    /**
     * Manda la cuenta (sigue a la persona a otro dispositivo), después la
     * cookie (cubre a quien todavía no inició sesión), después el default.
     */
    private function resolver(Request $request): TamanioTexto
    {
        $usuario = $request->user();

        if ($usuario !== null) {
            return $usuario->tamanio_texto;
        }

        // Una cookie puede llegar como array si alguien la manda con corchetes.
        $cookie = $request->cookie('tamanio_texto');

        return TamanioTexto::desde(is_string($cookie) ? $cookie : null);
    }
}
