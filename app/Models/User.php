<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $terminos_version
 * @property Carbon|null $terminos_aceptados_en
 * @property-read Emisor|null $emisor
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'terminos_aceptados_en' => 'datetime',
        ];
    }

    /**
     * Deja constancia de que aceptó la versión vigente de los términos y la
     * privacidad. Las dos columnas no son fillable a propósito: solo se
     * escriben acá.
     */
    public function aceptarTerminos(): void
    {
        $this->forceFill([
            'terminos_version' => config('legales.version'),
            'terminos_aceptados_en' => now(),
        ])->save();
    }

    public function aceptoTerminosVigentes(): bool
    {
        return $this->terminos_version !== null && $this->terminos_version === config('legales.version');
    }

    /**
     * El perfil fiscal: con qué CUIT factura esta cuenta. Null hasta que
     * completa la configuración.
     *
     * @return HasOne<Emisor, $this>
     */
    public function emisor(): HasOne
    {
        return $this->hasOne(Emisor::class, 'usuario_id');
    }
}
