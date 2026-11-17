<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confirmación de contraseña, salteada para quien no tiene ninguna.
 *
 * Reemplaza al alias `password.confirm` en `bootstrap/app.php`, así que cubre
 * la pantalla de seguridad y también las rutas de 2FA y llaves de acceso de
 * Fortify. Con el `RequirePassword` original, una cuenta que entra con Google
 * nunca podría confirmar nada: quedaría afuera del 2FA y sin poder definirse
 * una contraseña. A quien tiene contraseña se le sigue pidiendo igual.
 */
class ConfirmarClaveSiLaTiene extends RequirePassword
{
    public function handle(
        $request,
        Closure $next,
        $redirectToRoute = null,
        $passwordTimeoutSeconds = null,
    ): Response {
        if ($this->sinContrasena($request)) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute, $passwordTimeoutSeconds);
    }

    private function sinContrasena(Request $request): bool
    {
        $usuario = $request->user();

        return $usuario !== null && blank($usuario->getAuthPassword());
    }
}
