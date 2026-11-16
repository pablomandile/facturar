<?php

use App\Enums\Arca\Ambiente;
use App\Enums\Arca\EstadoCertificado;
use App\Models\Certificado;
use App\Models\Emisor;
use App\Models\PuntoDeVenta;
use App\Models\User;
use Database\Factories\CertificadoFactory;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

function datosFiscales(array $cambios = []): array
{
    return [
        'cuit' => '20-11111111-2',
        'razon_social' => 'Ana Pérez',
        'domicilio_fiscal' => 'Av. Siempreviva 742, CABA',
        'ingresos_brutos' => 'Exento',
        'inicio_actividades' => '2020-03-01',
        'ambiente' => 'homologacion',
        ...$cambios,
    ];
}

it('pide iniciar sesión', function () {
    $this->get(route('emisor.edit'))->assertRedirect(route('login'));
});

it('muestra la configuración vacía a una cuenta nueva', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('emisor.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $pagina) => $pagina
            ->component('emisor/Configuracion')
            ->where('emisor', null)
            ->where('pasos', ['perfil' => false, 'certificado' => false, 'punto_de_venta' => false]));
});

it('da de alta el perfil fiscal con el CUIT normalizado', function () {
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->put(route('emisor.update'), datosFiscales())
        ->assertRedirect(route('emisor.edit'))
        ->assertSessionHasNoErrors();

    // fresh(): la instancia del test es la misma que usó el request, que
    // dejó cacheada la relación vacía al autorizar.
    expect($usuario->fresh()?->emisor)
        ->cuit->toBe('20111111112')
        ->ambiente->toBe(Ambiente::Homologacion);
});

it('valida el CUIT, su unicidad y la fecha de inicio', function (array $cambios, string $campo) {
    Emisor::factory()->create(['cuit' => '30500010912']);

    $this->actingAs(User::factory()->create())
        ->put(route('emisor.update'), datosFiscales($cambios))
        ->assertSessionHasErrors($campo);
})->with([
    'verificador mal' => [['cuit' => '20-11111111-1'], 'cuit'],
    'CUIT de otra cuenta' => [['cuit' => '30-50001091-2'], 'cuit'],
    'inicio en el futuro' => [['inicio_actividades' => now()->addDay()->toDateString()], 'inicio_actividades'],
    'ambiente inventado' => [['ambiente' => 'staging'], 'ambiente'],
]);

it('no deja cambiar el CUIT si ya hay certificados', function () {
    $emisor = Emisor::factory()->create();
    Certificado::factory()->for($emisor)->create();

    $this->actingAs($emisor->usuario)
        ->put(route('emisor.update'), datosFiscales(['cuit' => '30-50001091-2']))
        ->assertSessionHasErrors(['cuit' => 'No se puede cambiar el CUIT: ya tiene certificados de ARCA cargados.']);
});

it('genera el pedido y lo deja bajar como .csr', function () {
    $emisor = Emisor::factory()->create();

    $this->actingAs($emisor->usuario)
        ->post(route('certificados.store'), ['alias' => 'FacturAR'])
        ->assertRedirect(route('emisor.edit'));

    $pendiente = $emisor->certificadoPendiente();
    expect($pendiente)->not->toBeNull()->and($pendiente?->alias)->toBe('facturar');

    $this->get(route('certificados.csr', $pendiente))
        ->assertOk()
        ->assertDownload("facturar-{$emisor->cuit}-homologacion.csr");
});

it('rechaza un alias con espacios o acentos', function () {
    $emisor = Emisor::factory()->create();

    $this->actingAs($emisor->usuario)
        ->post(route('certificados.store'), ['alias' => 'mi certificado'])
        ->assertSessionHasErrors('alias');
});

it('carga el certificado pegado como texto o subido como archivo', function (bool $comoArchivo) {
    $emisor = Emisor::factory()->create();
    $pendiente = Certificado::factory()->for($emisor)->create();
    $pem = CertificadoFactory::certificadoPara($emisor);
    $datos = $comoArchivo ? ['archivo' => UploadedFile::fake()->createWithContent('cert.crt', $pem)] : ['texto' => $pem];

    $this->actingAs($emisor->usuario)
        ->post(route('certificados.update', $pendiente), $datos)
        ->assertRedirect(route('emisor.edit'))
        ->assertSessionHasNoErrors();

    expect($pendiente->fresh()?->estado)->toBe(EstadoCertificado::Vigente);
})->with(['texto' => false, 'archivo' => true]);

it('muestra el error del certificado junto al campo que se usó', function () {
    $emisor = Emisor::factory()->create();
    $pendiente = Certificado::factory()->for($emisor)->create();

    $this->actingAs($emisor->usuario)
        ->post(route('certificados.update', $pendiente), ['texto' => 'esto no es un certificado'])
        ->assertSessionHasErrors('texto');
});

it('no deja subir un certificado sobre uno que ya está vigente', function () {
    $certificado = Certificado::factory()->vigente()->create();

    $this->actingAs($certificado->emisor->usuario)
        ->post(route('certificados.update', $certificado), ['texto' => 'x'])
        ->assertForbidden();
});

it('carga, edita y quita puntos de venta en el ambiente del emisor', function () {
    $emisor = Emisor::factory()->create();
    $this->actingAs($emisor->usuario);

    $this->post(route('puntos-de-venta.store'), ['numero' => 3, 'descripcion' => 'Web'])->assertSessionHasNoErrors();
    $punto = PuntoDeVenta::query()->sole();
    expect($punto->ambiente)->toBe(Ambiente::Homologacion)->and($punto->emisor_id)->toBe($emisor->id);

    $this->post(route('puntos-de-venta.store'), ['numero' => 3])->assertSessionHasErrors(['numero' => 'Ya cargaste ese punto de venta.']);

    $this->put(route('puntos-de-venta.update', $punto), ['numero' => 4, 'activo' => false])->assertSessionHasNoErrors();
    expect($punto->fresh())->numero->toBe(4)->activo->toBeFalse();

    $this->delete(route('puntos-de-venta.destroy', $punto))->assertRedirect(route('emisor.edit'));
    expect(PuntoDeVenta::query()->count())->toBe(0);
});

it('el mismo número de punto de venta puede existir en el otro ambiente', function () {
    $emisor = Emisor::factory()->create();
    PuntoDeVenta::factory()->for($emisor)->create(['numero' => 1, 'ambiente' => Ambiente::Produccion]);

    $this->actingAs($emisor->usuario)
        ->post(route('puntos-de-venta.store'), ['numero' => 1])
        ->assertSessionHasNoErrors();
});

describe('aislamiento entre cuentas', function () {
    it('no deja bajar, cargar ni descartar el certificado de otro', function () {
        $ajeno = Certificado::factory()->create();
        $this->actingAs(Emisor::factory()->create()->usuario);

        $this->get(route('certificados.csr', $ajeno))->assertForbidden();
        $this->post(route('certificados.update', $ajeno), ['texto' => 'x'])->assertForbidden();
        $this->delete(route('certificados.destroy', $ajeno))->assertForbidden();
    });

    it('no deja tocar los puntos de venta de otro', function () {
        $ajeno = PuntoDeVenta::factory()->create();
        $this->actingAs(Emisor::factory()->create()->usuario);

        $this->put(route('puntos-de-venta.update', $ajeno), ['numero' => 9])->assertForbidden();
        $this->delete(route('puntos-de-venta.destroy', $ajeno))->assertForbidden();
    });

    it('sin perfil fiscal no se puede generar certificado ni cargar puntos de venta', function () {
        $this->actingAs(User::factory()->create());

        $this->post(route('certificados.store'), ['alias' => 'facturar'])->assertForbidden();
        $this->post(route('puntos-de-venta.store'), ['numero' => 1])->assertForbidden();
    });
});
