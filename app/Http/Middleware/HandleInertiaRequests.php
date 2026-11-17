<?php

namespace App\Http\Middleware;

use App\Services\IngresoConGoogle;
use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Una misma URL de Inertia contesta dos cuerpos distintos según lleve o no
     * el header X-Inertia: el HTML de arranque, o el JSON de la página.
     *
     * Lo único que separa esas dos respuestas para una caché HTTP es
     * `Vary: X-Inertia`. En Hostinger el CDN lo BORRA al comprimir con brotli,
     * y el `Cache-Control: no-cache` que Symfony pone por defecto permite
     * guardar (solo obliga a revalidar). Cuando Chrome restaura una pestaña
     * descartada, esa navegación reusa lo guardado sin revalidar: se ve el
     * JSON crudo en pantalla y la app nunca arranca (lo vivió misalud; ver la
     * skill inertia-json-crudo).
     *
     * `no-store` en la respuesta XHR prohíbe guardarla. En el documento HTML
     * no: desactivaría el back/forward cache de Chrome.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        $response->headers->set('Vary', Header::INERTIA.', Accept-Encoding');

        if ($request->header(Header::INERTIA)) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                // Falso en las cuentas que entran con Google sin haber definido
                // una: la pantalla de seguridad y la de eliminar la cuenta no
                // le piden la que no tiene.
                'tieneContrasena' => filled($request->user()?->getAuthPassword()),
            ],
            // Sin credenciales en el .env el botón de Google no se dibuja.
            'googleHabilitado' => IngresoConGoogle::configurado(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
