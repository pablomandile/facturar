<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Support\SessionKey;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as UsuarioDeSocialite;

function conCredencialesDeGoogle(): void
{
    config([
        'services.google.client_id' => 'id-de-prueba.apps.googleusercontent.com',
        'services.google.client_secret' => 'secreto-de-prueba',
        'services.google.redirect' => 'http://localhost/auth/google/callback',
    ]);
}

/**
 * Lo que devolvería Socialite, con el flag de verificado en el payload crudo,
 * que es donde lo manda Google.
 */
function respuestaDeGoogle(string $id, string $email, ?string $nombre = 'Ana Pérez', bool $emailVerificado = true): void
{
    $cuenta = new UsuarioDeSocialite;
    $cuenta->map(['id' => $id, 'email' => $email, 'name' => $nombre]);
    $cuenta->setRaw(['email_verified' => $emailVerificado]);

    Socialite::shouldReceive('driver')->with('google')->andReturn(
        Mockery::mock()->shouldReceive('user')->andReturn($cuenta)->getMock(),
    );
}

/** @return array{type?: string, message?: string} */
function toastDeGoogle(): array
{
    return session()->get(SessionKey::FLASH_DATA)['toast'] ?? [];
}

function usuarioDeGoogleSinClave(): User
{
    $usuario = User::factory()->create();
    $usuario->forceFill(['password' => null, 'google_id' => 'sub-123'])->save();

    return $usuario;
}

// --- Sin credenciales, la opción no existe ---------------------------------

it('da 404 en las rutas de Google si no hay credenciales', function (): void {
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

    $this->get(route('google.redirect'))->assertNotFound();
    $this->get(route('google.callback'))->assertNotFound();
});

it('ofrece el botón de Google solo si hay credenciales', function (): void {
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
    $this->get(route('login'))->assertInertia(fn ($p) => $p->where('googleHabilitado', false));

    conCredencialesDeGoogle();
    $this->get(route('login'))->assertInertia(fn ($p) => $p->where('googleHabilitado', true));
});

it('manda a Google al pedir el ingreso', function (): void {
    conCredencialesDeGoogle();

    $this->get(route('google.redirect'))->assertRedirectContains('accounts.google.com');
});

// --- Alta y vínculo -------------------------------------------------------

it('crea la cuenta sin contraseña, con el email verificado y sin términos aceptados', function (): void {
    conCredencialesDeGoogle();
    respuestaDeGoogle('sub-123', 'ana@example.com', 'Ana Pérez');

    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

    $usuario = User::firstWhere('email', 'ana@example.com');

    expect($usuario)->not->toBeNull()
        ->and($usuario->name)->toBe('Ana Pérez')
        ->and($usuario->google_id)->toBe('sub-123')
        ->and($usuario->password)->toBeNull()
        ->and($usuario->hasVerifiedEmail())->toBeTrue()
        ->and($usuario->aceptoTerminosVigentes())->toBeFalse();

    $this->assertAuthenticatedAs($usuario);

    // Sin la casilla del registro: lo frena AceptoTerminos en el primer pedido.
    $this->get(route('dashboard'))->assertRedirect(route('terminos.aceptar'));
});

it('usa la parte del email como nombre si Google no manda ninguno', function (): void {
    conCredencialesDeGoogle();
    respuestaDeGoogle('sub-123', 'ana.perez@example.com', nombre: null);

    $this->get(route('google.callback'));

    expect(User::firstWhere('email', 'ana.perez@example.com')->name)->toBe('ana.perez');
});

it('vincula la cuenta existente en vez de crear otra, y le conserva contraseña y términos', function (): void {
    conCredencialesDeGoogle();
    $existente = User::factory()->create(['email' => 'ana@example.com', 'password' => 'clave-de-antes']);
    respuestaDeGoogle('sub-123', 'ana@example.com');

    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

    $existente->refresh();

    expect(User::count())->toBe(1)
        ->and($existente->google_id)->toBe('sub-123')
        ->and(Hash::check('clave-de-antes', (string) $existente->password))->toBeTrue()
        ->and($existente->aceptoTerminosVigentes())->toBeTrue();

    $this->assertAuthenticatedAs($existente);
});

it('da por verificado el email de quien se había registrado sin confirmarlo', function (): void {
    conCredencialesDeGoogle();
    $existente = User::factory()->unverified()->create(['email' => 'ana@example.com']);
    respuestaDeGoogle('sub-123', 'ana@example.com');

    $this->get(route('google.callback'));

    expect($existente->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('reconoce por el sub de Google y actualiza el email si cambió', function (): void {
    conCredencialesDeGoogle();
    $existente = User::factory()->create(['email' => 'viejo@example.com']);
    $existente->forceFill(['google_id' => 'sub-123'])->save();
    respuestaDeGoogle('sub-123', 'nuevo@example.com');

    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

    expect(User::count())->toBe(1)
        ->and($existente->fresh()->email)->toBe('nuevo@example.com');
});

it('no saltea la verificación en dos pasos de quien la activó', function (): void {
    conCredencialesDeGoogle();
    $usuario = User::factory()->withTwoFactor()->create(['email' => 'ana@example.com']);
    respuestaDeGoogle('sub-123', 'ana@example.com');

    $this->get(route('google.callback'))
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $usuario->id);

    $this->assertGuest();
});

// --- Lo que se rechaza ----------------------------------------------------

it('rechaza la cuenta si Google no verificó el email', function (): void {
    conCredencialesDeGoogle();
    respuestaDeGoogle('sub-123', 'ana@example.com', emailVerificado: false);

    $this->get(route('google.callback'))->assertRedirect(route('login'));

    expect(toastDeGoogle()['type'] ?? null)->toBe('error')
        ->and(User::count())->toBe(0);
    $this->assertGuest();
});

it('rechaza la cuenta si Google no devolvió email', function (): void {
    conCredencialesDeGoogle();
    respuestaDeGoogle('sub-123', '');

    $this->get(route('google.callback'))->assertRedirect(route('login'));

    expect(User::count())->toBe(0);
});

it('vuelve al login sin aviso de error si la persona canceló en Google', function (): void {
    conCredencialesDeGoogle();

    $this->get(route('google.callback', ['error' => 'access_denied']))->assertRedirect(route('login'));

    expect(toastDeGoogle())->toBe([]);
    $this->assertGuest();
});

it('no muestra el detalle del error de Socialite', function (): void {
    conCredencialesDeGoogle();

    Socialite::shouldReceive('driver')->with('google')->andReturn(
        Mockery::mock()->shouldReceive('user')
            ->andThrow(new RuntimeException('client_secret invalido: GOCSPX-secreto'))
            ->getMock(),
    );

    $this->get(route('google.callback'))->assertRedirect(route('login'));

    expect(toastDeGoogle()['message'] ?? '')->not->toContain('GOCSPX');
});

it('no expone el google_id al navegador', function (): void {
    $this->actingAs(usuarioDeGoogleSinClave())
        ->get(route('dashboard'))
        ->assertInertia(fn ($p) => $p->missing('auth.user.google_id')->where('auth.tieneContrasena', false));
});

// --- La cuenta sin contraseña ---------------------------------------------

it('NO deja entrar con email y contraseña a una cuenta que no tiene ninguna', function (string $intento): void {
    $usuario = usuarioDeGoogleSinClave();

    $this->post(route('login.store'), ['email' => $usuario->email, 'password' => $intento]);

    $this->assertGuest();
})->with([
    'contraseña vacía' => '',
    'cualquier contraseña' => 'la-que-sea',
]);

it('deja ver la pantalla de seguridad sin confirmar una contraseña que no existe', function (): void {
    $this->actingAs(usuarioDeGoogleSinClave())
        ->get(route('security.edit'))
        ->assertOk();
});

it('deja activar el 2FA a una cuenta sin contraseña', function (): void {
    $usuario = usuarioDeGoogleSinClave();

    $this->actingAs($usuario)
        ->post(route('two-factor.enable'))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($usuario->fresh()->two_factor_secret)->not->toBeNull();
});

it('le sigue pidiendo confirmar la contraseña a quien la tiene', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('security.edit'))
        ->assertRedirect(route('password.confirm'));

    $this->actingAs(User::factory()->create())
        ->post(route('two-factor.enable'))
        ->assertRedirect(route('password.confirm'));
});

it('deja definir una primera contraseña sin pedir la anterior', function (): void {
    $usuario = usuarioDeGoogleSinClave();

    $this->actingAs($usuario)
        ->put(route('user-password.update'), [
            'password' => 'Clave-Nueva-2026',
            'password_confirmation' => 'Clave-Nueva-2026',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('Clave-Nueva-2026', (string) $usuario->fresh()->password))->toBeTrue();
});

it('le sigue exigiendo la contraseña actual a quien ya tiene una', function (): void {
    $this->actingAs(User::factory()->create(['password' => 'clave-de-antes']))
        ->put(route('user-password.update'), [
            'password' => 'Clave-Nueva-2026',
            'password_confirmation' => 'Clave-Nueva-2026',
        ])
        ->assertSessionHasErrors('current_password');
});

it('deja eliminar la cuenta a quien no tiene contraseña', function (): void {
    $usuario = usuarioDeGoogleSinClave();

    $this->actingAs($usuario)->delete(route('profile.destroy'))->assertRedirect('/');

    expect(User::find($usuario->id))->toBeNull();
});

it('le sigue pidiendo la contraseña para eliminar la cuenta a quien la tiene', function (): void {
    $usuario = User::factory()->create(['password' => 'clave-de-antes']);

    $this->actingAs($usuario)
        ->delete(route('profile.destroy'), ['password' => 'equivocada'])
        ->assertSessionHasErrors('password');

    expect(User::find($usuario->id))->not->toBeNull();
});
