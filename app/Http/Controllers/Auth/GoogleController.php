<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\IngresoConGoogle;
use App\Support\CuentaDeGoogle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as RespuestaDeSocialite;
use Throwable;

/**
 * Ingreso con Google. Sin credenciales en el `.env` las dos rutas dan 404:
 * la app se comporta como si la opción no existiera.
 */
class GoogleController extends Controller
{
    public function __construct(private readonly IngresoConGoogle $ingreso) {}

    public function redirigir(): RespuestaDeSocialite|RedirectResponse
    {
        abort_unless(IngresoConGoogle::configurado(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function volver(Request $request): RedirectResponse
    {
        abort_unless(IngresoConGoogle::configurado(), 404);

        // Tocó "Cancelar" en Google: hizo lo que quería, no es un error.
        if ($request->has('error')) {
            return redirect()->route('login');
        }

        try {
            $usuario = $this->ingreso->resolver(
                CuentaDeGoogle::desdeSocialite(Socialite::driver('google')->user()),
            );
        } catch (Throwable $e) {
            // El detalle al log: los mensajes de Socialite traen partes de la
            // respuesta de Google, que no le dicen nada a la persona.
            Log::warning('Falló el ingreso con Google.', ['excepcion' => $e->getMessage()]);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No pudimos entrar con Google. Probá de nuevo, o entrá con tu email y contraseña.',
            ]);

            return redirect()->route('login');
        }

        /*
         * Quien activó la verificación en dos pasos la eligió para su cuenta,
         * no para una forma de entrar: Google no la saltea. Es lo mismo que
         * hace Fortify después de la contraseña.
         */
        if ($usuario->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $usuario->getKey(),
                'login.remember' => true,
            ]);

            TwoFactorAuthenticationChallenged::dispatch($usuario);

            return redirect()->route('two-factor.login');
        }

        Auth::login($usuario, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
