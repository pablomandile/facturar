<?php

use App\Http\Controllers\LegalesController;
use App\Http\Middleware\AceptoTerminos;
use App\Models\Certificado;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Emisor;
use App\Models\LlamadaArca;
use App\Models\PuntoDeVenta;
use App\Models\TicketDeAcceso;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

/*
 * Términos y privacidad: una versión vigente en `config('legales.version')`,
 * la constancia de quién la aceptó en `users`, y el middleware AceptoTerminos
 * que frena a quien no la aceptó, salvo para lo que tiene que poder hacer
 * igual: leer los textos, salir, llevarse sus datos y borrar su cuenta.
 *
 * Y lo que los textos prometen, atado al código.
 */

function datosDeRegistro(array $extra = []): array
{
    return [
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        ...$extra,
    ];
}

it('no deja registrarse sin aceptar', function () {
    $this->post(route('register.store'), datosDeRegistro())->assertSessionHasErrors('terminos');

    $this->assertGuest();
});

it('al registrarse con la casilla deja la constancia y no vuelve a preguntar', function () {
    $this->post(route('register.store'), datosDeRegistro(['terminos' => '1']));

    $usuario = User::query()->where('email', 'ana@example.com')->firstOrFail();
    expect($usuario->terminos_version)->toBe(config('legales.version'))
        ->and($usuario->terminos_aceptados_en)->not->toBeNull();

    $usuario->markEmailAsVerified();
    $this->actingAs($usuario)->get(route('dashboard'))->assertOk();
});

it('a una cuenta sin aceptar la manda a aceptar, y después vuelve a donde iba', function () {
    $usuario = User::factory()->sinAceptarTerminos()->create();

    $this->actingAs($usuario)->get(route('emisor.edit'))->assertRedirect(route('terminos.aceptar'));

    $this->get(route('terminos.aceptar'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('auth/AceptarTerminos')->where('primeraVez', true));

    $this->post(route('terminos.aceptar.store'), ['terminos' => '1'])->assertRedirect(route('emisor.edit'));
    expect($usuario->fresh()?->aceptoTerminosVigentes())->toBeTrue();
});

it('aceptar exige la casilla', function () {
    $this->actingAs(User::factory()->sinAceptarTerminos()->create())
        ->post(route('terminos.aceptar.store'))
        ->assertSessionHasErrors('terminos');
});

it('subir la versión vuelve a preguntar', function () {
    $usuario = User::factory()->create();
    config(['legales.version' => '2099-01-01']);

    $this->actingAs($usuario)->get(route('dashboard'))->assertRedirect(route('terminos.aceptar'));
    $this->get(route('terminos.aceptar'))->assertInertia(fn (AssertableInertia $p) => $p->where('primeraVez', false));
});

it('sin aceptar igual puede leer, salir, descargar sus datos y borrar la cuenta', function () {
    $usuario = User::factory()->sinAceptarTerminos()->create();
    $this->actingAs($usuario);

    $this->get(route('terminos'))->assertOk();
    $this->get(route('privacidad'))->assertOk();
    $this->get(route('cookies'))->assertOk();
    $this->get(route('exportacion.datos'))->assertOk()->assertDownload();
    $this->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

    expect(User::query()->find($usuario->id))->toBeNull();
});

it('un JSON sin aceptar recibe 409', function () {
    $this->actingAs(User::factory()->sinAceptarTerminos()->create())
        ->getJson(route('dashboard'))
        ->assertStatus(409);
});

it('las rutas con sesión que no piden aceptar son exactamente las listadas', function () {
    $exentasConSesion = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($ruta) => collect($ruta->gatherMiddleware())->contains(fn ($m) => is_string($m) && Str::startsWith($m, 'auth')))
        ->map(fn ($ruta) => $ruta->getName())
        ->filter(fn (?string $nombre) => $nombre !== null && Str::is(AceptoTerminos::EXENTAS, $nombre))
        ->sort()
        ->values()
        ->all();

    expect($exentasConSesion)->toBe([
        'exportacion.datos',
        'logout',
        'profile.destroy',
        'terminos.aceptar',
        'terminos.aceptar.store',
        'verification.notice',
        'verification.send',
        'verification.verify',
    ]);
});

describe('lo que los textos prometen', function () {
    it('completa responsable, contacto y retención desde la config, sin llaves sueltas', function () {
        config(['legales.responsable' => 'Responsable de Prueba', 'legales.contacto' => 'contacto@prueba.test']);

        foreach (['terminos', 'privacidad'] as $texto) {
            $markdown = LegalesController::markdown($texto);
            expect($markdown)->not->toMatch('/\{[a-z_]+\}/')
                ->and($markdown)->toContain('contacto@prueba.test');
        }

        expect(LegalesController::markdown('privacidad'))->toContain('**'.config('arca.retencion_llamadas_dias').' días**');
    });

    it('sin configurar, el hueco se ve en vez de quedar vacío', function () {
        config(['legales.responsable' => null, 'legales.contacto' => null]);

        expect(LegalesController::markdown('privacidad'))->toContain('[falta configurar LEGALES_CONTACTO]');
    });

    it('«Descargar mis datos» existe donde la política dice', function () {
        expect(LegalesController::markdown('privacidad'))->toContain('Configuración → Perfil, **Descargar mis datos**')
            ->and((string) file_get_contents(resource_path('js/pages/settings/Profile.vue')))->toContain('Descargar mis datos');
    });

    it('eliminar la cuenta borra todo en el momento, como dice la política', function () {
        $emisor = Emisor::factory()->create();
        Certificado::factory()->vigente()->for($emisor)->create();
        $punto = PuntoDeVenta::factory()->for($emisor)->create();
        Cliente::factory()->for($emisor)->create();
        Comprobante::factory()->paraPunto($punto)->autorizado(1)->create();
        TicketDeAcceso::query()->create(['emisor_id' => $emisor->id, 'ambiente' => 'homologacion', 'servicio' => 'wsfe', 'token' => 't', 'sign' => 's']);
        LlamadaArca::query()->create(['emisor_id' => $emisor->id, 'ambiente' => 'homologacion', 'servicio' => 'wsfe', 'metodo' => 'FEDummy', 'resultado' => 'ok', 'duracion_ms' => 1]);
        $usuario = $emisor->usuario;

        $this->actingAs($usuario)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

        foreach (['users', 'emisores', 'certificados', 'puntos_de_venta', 'clientes', 'comprobantes', 'comprobante_items', 'tickets_de_acceso', 'llamadas_arca'] as $tabla) {
            expect(DB::table($tabla)->count())->toBe(0, "Quedaron filas en {$tabla}");
        }
    });

    it('la descarga lleva los datos y los PDF, pero nunca la clave privada', function () {
        $emisor = Emisor::factory()->create();
        Certificado::factory()->vigente()->for($emisor)->create();
        $punto = PuntoDeVenta::factory()->for($emisor)->create(['numero' => 2]);
        Cliente::factory()->for($emisor)->create(['nombre' => 'Cliente Exportado']);
        Comprobante::factory()->paraPunto($punto)->autorizado(7)->create();
        Comprobante::factory()->paraPunto($punto)->create();

        $respuesta = $this->actingAs($emisor->usuario)->get(route('exportacion.datos'))->assertOk();
        $ruta = $respuesta->getFile()->getPathname();

        $zip = new ZipArchive;
        expect($zip->open($ruta))->toBeTrue();
        $datos = json_decode((string) $zip->getFromName('datos.json'), true);
        $todo = (string) $zip->getFromName('datos.json');

        expect($datos['emisor']['cuit'])->toBe($emisor->cuit)
            ->and($datos['clientes'][0]['nombre'])->toBe('Cliente Exportado')
            ->and($datos['comprobantes'])->toHaveCount(2)
            ->and($zip->locateName('facturas-de-prueba/Factura-C-00002-00000007.pdf'))->not->toBeFalse()
            ->and($todo)->not->toContain('PRIVATE KEY');
        $zip->close();
    });
});
