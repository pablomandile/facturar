<?php

declare(strict_types=1);

namespace App\Support;

use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as UsuarioDeSocialite;

/**
 * Lo único que FacturAR necesita saber de una cuenta de Google. Deja a
 * Socialite en el borde y permite probar el alta sin hablar con Google.
 */
final readonly class CuentaDeGoogle
{
    public function __construct(
        public string $id,
        public string $email,
        public ?string $nombre,
        public bool $emailVerificado,
    ) {}

    public static function desdeSocialite(UsuarioDeSocialite $usuario): self
    {
        return new self(
            id: (string) $usuario->getId(),
            email: (string) $usuario->getEmail(),
            nombre: $usuario->getName(),
            emailVerificado: self::emailVerificado($usuario),
        );
    }

    /**
     * Google manda el flag en el payload crudo, que solo expone `AbstractUser`.
     *
     * Si no viene, el email NO se da por verificado: con un email sin verificar,
     * cualquiera podría declarar la dirección de otra persona, quedar vinculado
     * a su cuenta y facturar con su certificado.
     */
    private static function emailVerificado(UsuarioDeSocialite $usuario): bool
    {
        if (! $usuario instanceof AbstractUser) {
            return false;
        }

        $crudo = $usuario->getRaw();

        // Las dos claves aparecen según qué endpoint de Google haya respondido.
        foreach (['verified_email', 'email_verified'] as $clave) {
            if (array_key_exists($clave, $crudo)) {
                return filter_var($crudo[$clave], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return false;
    }
}
