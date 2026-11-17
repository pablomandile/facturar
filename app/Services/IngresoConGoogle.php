<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\CuentaDeGoogle;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Alta y vínculo de cuentas que entran con Google.
 *
 * **El email manda**: Google ya lo verificó, así que si coincide con una cuenta
 * que existe es la misma persona y se le vincula el `google_id` en vez de crear
 * otra. Dos cuentas con el mismo email serían dos emisores separados del mismo
 * CUIT, y el UNIQUE de `emisores.cuit` impediría cargar el segundo.
 */
class IngresoConGoogle
{
    /**
     * Sin las credenciales en el `.env` la opción no existe: no hay botón y las
     * rutas dan 404. `Config::get` y no `Config::string`, que con null explota.
     */
    public static function configurado(): bool
    {
        return filled(Config::get('services.google.client_id'))
            && filled(Config::get('services.google.client_secret'));
    }

    public function resolver(CuentaDeGoogle $cuenta): User
    {
        if (blank($cuenta->id) || blank($cuenta->email)) {
            throw new RuntimeException('Google no devolvió el email de la cuenta.');
        }

        if (! $cuenta->emailVerificado) {
            throw new RuntimeException('La cuenta de Google no tiene el email verificado.');
        }

        return DB::transaction(function () use ($cuenta): User {
            /*
             * Se reconoce por el `sub` y no por el email: el email de una cuenta
             * de Google se puede cambiar, el identificador no.
             */
            $porGoogle = User::where('google_id', $cuenta->id)->first();

            if ($porGoogle !== null) {
                $porGoogle->email = $cuenta->email;
                $porGoogle->save();

                return $porGoogle;
            }

            $porEmail = User::where('email', $cuenta->email)->first();

            if ($porEmail !== null) {
                $porEmail->forceFill(['google_id' => $cuenta->id])->save();

                // Se había registrado con este email sin confirmarlo: Google
                // acaba de confirmarlo por él.
                if (! $porEmail->hasVerifiedEmail()) {
                    $porEmail->markEmailAsVerified();
                }

                return $porEmail;
            }

            return $this->crear($cuenta);
        });
    }

    /**
     * Cuenta nueva, sin contraseña y sin términos aceptados: `AceptoTerminos`
     * la frena en el primer pedido, igual que a cualquier cuenta sin aceptar.
     */
    private function crear(CuentaDeGoogle $cuenta): User
    {
        $usuario = new User;

        // forceFill: google_id, password y email_verified_at no son fillable.
        $usuario->forceFill([
            'name' => $cuenta->nombre ?: (string) str($cuenta->email)->before('@'),
            'email' => $cuenta->email,
            'google_id' => $cuenta->id,
            'password' => null,
            'email_verified_at' => $usuario->freshTimestamp(),
        ]);

        $usuario->save();

        return $usuario;
    }
}
