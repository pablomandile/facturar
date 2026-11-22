<?php

use App\Contracts\Arca\FabricaDeTransportes;
use App\Enums\Arca\Ambiente;
use App\Enums\EstadoComprobante;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\EnvioDeComprobante;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\Arca\Demo\FabricaDeTransportesDemo;
use App\Services\Arca\EmisorDeComprobantes;
use Database\Seeders\DemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * La demo del portfolio (config/demo.php). Con el flag apagado, la app es la
 * de siempre: eso lo cubre el resto de la suite.
 */

function sembrarDemo(): User
{
    config(['demo.enabled' => true]);
    test()->seed(DemoSeeder::class);

    return User::query()->where('email', config('demo.email'))->sole();
}

it('siembra una emisora de homologación con clientes, facturas y envíos', function () {
    $usuario = sembrarDemo();
    $emisor = $usuario->emisor;

    expect($emisor?->ambiente)->toBe(Ambiente::Homologacion)
        ->and($emisor?->certificadoVigente())->not->toBeNull()
        ->and(Cliente::query()->count())->toBe(3)
        ->and(Comprobante::query()->where('estado', EstadoComprobante::Autorizado)->orderBy('numero')->pluck('numero')->all())->toBe(range(1, 9))
        ->and(Comprobante::query()->where('estado', EstadoComprobante::Borrador)->count())->toBe(1)
        ->and(Comprobante::query()->where('estado', EstadoComprobante::Rechazado)->count())->toBe(1)
        ->and(EnvioDeComprobante::query()->count())->toBe(6)
        ->and($usuario->terminos_version)->toBe(config('legales.version'));

    // Lo autorizado nunca es posterior a ayer: el reset corre de madrugada.
    expect(Comprobante::query()->where('estado', EstadoComprobante::Autorizado)->max('fecha_emision'))
        ->toBeLessThan(now('America/Argentina/Buenos_Aires')->toDateString());
});

it('el login muestra los datos de la demo solo con el flag', function () {
    $this->get(route('login'))->assertInertia(fn (Assert $p) => $p->where('demo', null));

    config(['demo.enabled' => true]);

    $this->get(route('login'))->assertInertia(fn (Assert $p) => $p
        ->where('demo.email', config('demo.email'))
        ->where('demo.password', config('demo.password')));
});

it('con la cuenta de la demo se entra y se ve el panel', function () {
    sembrarDemo();

    $this->post(route('login.store'), ['email' => config('demo.email'), 'password' => config('demo.password')])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->get(route('dashboard'))->assertOk();
});

it('emite el borrador contra la ARCA simulada, sin salir a la red', function () {
    sembrarDemo();
    app()->instance(FabricaDeTransportes::class, new FabricaDeTransportesDemo);
    $borrador = Comprobante::query()->where('estado', EstadoComprobante::Borrador)->sole();

    app(EmisorDeComprobantes::class)->emitir($borrador);

    expect($borrador->fresh())
        ->estado->toBe(EstadoComprobante::Autorizado)
        ->numero->toBe(10)
        ->cae->toMatch('/^7\d{13}$/');
});

it('la app usa la ARCA simulada cuando la demo está prendida', function () {
    config(['demo.enabled' => true]);
    app()->forgetInstance(FabricaDeTransportes::class);
    app()->offsetUnset(FabricaDeTransportes::class);
    (new AppServiceProvider(app()))->register();

    expect(app(FabricaDeTransportes::class))->toBeInstanceOf(FabricaDeTransportesDemo::class);
});

describe('lo que la demo no deja tocar', function () {
    beforeEach(function () {
        $this->actingAs(sembrarDemo());
    });

    it('no hay registro ni recuperación de clave', function () {
        auth()->logout();

        $this->get(route('register'))->assertRedirect(route('login'));
        $this->get(route('password.request'))->assertRedirect(route('login'));
    });

    it('la cuenta no se cambia ni se borra', function () {
        $this->from(route('profile.edit'))->patch(route('profile.update'), ['name' => 'Otra', 'email' => 'otra@x.test'])
            ->assertRedirect(route('profile.edit'));
        $this->from(route('profile.edit'))->delete(route('profile.destroy'), ['password' => config('demo.password')]);

        expect(User::query()->where('email', config('demo.email'))->value('name'))->toBe('Lucía Fernández');
    });

    it('ni el 2FA, que dejaría afuera a todos', function () {
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->postJson(route('two-factor.enable'))
            ->assertForbidden();

        expect(User::query()->where('email', config('demo.email'))->value('two_factor_secret'))->toBeNull();
    });

    it('ni los datos fiscales, el certificado o los puntos de venta', function () {
        $emisor = User::query()->where('email', config('demo.email'))->sole()->emisor;
        $punto = $emisor?->puntosDeVenta()->sole();

        // Datos válidos: lo que tiene que frenar es la demo, no la validación.
        $this->from(route('emisor.edit'))->put(route('emisor.update'), [
            'cuit' => $emisor?->cuit,
            'razon_social' => 'Otra',
            'domicilio_fiscal' => 'Otro 123',
            'inicio_actividades' => '2021-03-01',
            'ambiente' => 'produccion',
        ])->assertRedirect(route('emisor.edit'));
        $this->from(route('emisor.edit'))->put(route('puntos-de-venta.update', $punto), ['numero' => 1, 'activo' => '0']);

        expect($emisor?->fresh()?->ambiente)->toBe(Ambiente::Homologacion)
            ->and($punto?->fresh()?->activo)->toBeTrue();
    });

    it('facturar, cargar clientes y cambiar el texto del email, sí', function () {
        $this->put(route('emisor.plantilla-email'), ['plantilla_email' => 'Hola {alias}'])->assertSessionHasNoErrors();

        expect(User::query()->where('email', config('demo.email'))->sole()->emisor?->plantilla_email)->toBe('Hola {alias}');
    });
});

it('demo:reset no borra nada sin el flag', function () {
    $usuario = User::factory()->create();

    $this->artisan('demo:reset')->assertFailed();

    expect($usuario->fresh())->not->toBeNull();
});
