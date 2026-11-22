<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * En la demo (APP_DEMO=true) la cuenta es una sola y la comparten todos los
 * visitantes. Facturar, cargar clientes, duplicar, mandar por email (al log) y
 * cambiar el texto del email sí: es lo que se quiere mostrar, y se resiembra de
 * noche. Lo que dejaría la demo rota para el siguiente, no:
 *
 * - la cuenta: registrarse, Google, recuperar o cambiar la clave, borrarla, y
 *   sobre todo **2FA y passkeys**, que dejarían afuera a todos hasta el reset;
 * - los datos fiscales, el certificado y los puntos de venta: pasar a
 *   producción, cambiar el CUIT o desactivar el punto de venta deja a todos
 *   sin poder emitir.
 */
class ProtegerDemo
{
    // Pantallas que no existen en la demo: van al login.
    private const PANTALLAS = [
        'register', 'register.store', 'google.redirect', 'google.callback',
        'password.request', 'password.reset',
    ];

    // Cambios de la cuenta: vuelven atrás con un aviso.
    private const CUENTA = [
        'password.email', 'password.update', 'user-password.update',
        'profile.update', 'profile.destroy', 'verification.send',
        'two-factor.enable', 'two-factor.disable', 'two-factor.confirm',
        'two-factor.regenerate-recovery-codes',
        'passkey.registration-options', 'passkey.store', 'passkey.destroy',
    ];

    // La configuración fiscal de la emisora de la demo.
    private const FISCAL = [
        'emisor.update',
        'certificados.store', 'certificados.update', 'certificados.destroy',
        'puntos-de-venta.store', 'puntos-de-venta.update', 'puntos-de-venta.destroy',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('demo.enabled')) {
            return $next($request);
        }

        $ruta = $request->route()?->getName();

        if (in_array($ruta, self::PANTALLAS, true)) {
            return redirect()->route('login');
        }

        if (in_array($ruta, self::CUENTA, true)) {
            return $this->rechazar($request, 'En la demo no se puede cambiar la cuenta.');
        }

        if (in_array($ruta, self::FISCAL, true)) {
            return $this->rechazar($request, 'En la demo los datos fiscales, el certificado y los puntos de venta quedan fijos.');
        }

        return $next($request);
    }

    private function rechazar(Request $request, string $mensaje): Response
    {
        // Las passkeys y el 2FA se piden por fetch: un redirect no le sirve a nadie.
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $mensaje], 403);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $mensaje]);

        return back();
    }
}
