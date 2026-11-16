<?php

use App\Exceptions\Arca\FaultDeArca;
use App\Models\Certificado;
use App\Models\Emisor;
use App\Models\PuntoDeVenta;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Support\SessionKey;
use Tests\Support\Arca\RespuestasArca;

/** @return list<array{nombre: string, ok: bool, detalle: string}> */
function pasosDeLaPrueba(TestResponse $respuesta): array
{
    $respuesta->assertRedirect();

    return session()->get(SessionKey::FLASH_DATA)['pruebaConexion'] ?? [];
}

function emisorListo(): Emisor
{
    $emisor = Emisor::factory()->create();
    Certificado::factory()->vigente()->for($emisor)->create();
    PuntoDeVenta::factory()->for($emisor)->create(['numero' => 1]);

    return $emisor;
}

it('recorre servidores, certificado y puntos de venta', function () {
    $arca = arcaFalso()->conTicket();
    $arca->wsfe->responder('FEDummy', RespuestasArca::dummy())
        ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(7));
    $emisor = emisorListo();

    $respuesta = $this->actingAs($emisor->usuario)
        ->post(route('conexion.store'))
        ->assertRedirect(route('emisor.edit'))
        ->assertInertiaFlash('toast.type', 'success');

    $pasos = pasosDeLaPrueba($respuesta);

    expect(array_column($pasos, 'nombre'))->toBe(['Servidores de ARCA', 'Certificado y permiso para facturar', 'Punto de venta 00001'])
        ->and(array_column($pasos, 'ok'))->toBe([true, true, true])
        ->and($pasos[1]['detalle'])->toStartWith('ARCA aceptó el certificado')
        ->and($pasos[2]['detalle'])->toBe('Última factura C: 7. La próxima va a ser la 8.');
});

it('se corta en el certificado y dice qué hacer', function () {
    $arca = arcaFalso();
    $arca->wsfe->responder('FEDummy', RespuestasArca::dummy());
    $arca->wsaa->fallar('loginCms', new FaultDeArca('ns1:coe.notAuthorized', 'Computador no autorizado'));
    $emisor = emisorListo();

    $respuesta = $this->actingAs($emisor->usuario)
        ->post(route('conexion.store'))
        ->assertInertiaFlash('toast.type', 'error');

    $pasos = pasosDeLaPrueba($respuesta);

    expect(array_column($pasos, 'ok'))->toBe([true, false])
        ->and($pasos[1]['detalle'])->toContain('no está autorizado para Facturación Electrónica');
    $arca->wsfe->assertNoLlamo('FECompUltimoAutorizado');
});

it('sin puntos de venta avisa que falta cargar uno', function () {
    $arca = arcaFalso()->conTicket();
    $arca->wsfe->responder('FEDummy', RespuestasArca::dummy());
    $emisor = Emisor::factory()->create();
    Certificado::factory()->vigente()->for($emisor)->create();

    $pasos = pasosDeLaPrueba($this->actingAs($emisor->usuario)->post(route('conexion.store')));

    expect(end($pasos))->toBe(['nombre' => 'Punto de venta', 'ok' => false, 'detalle' => 'Cargá al menos un punto de venta para poder facturar.']);
});

it('sin perfil fiscal no se puede probar', function () {
    arcaFalso();

    $this->actingAs(User::factory()->create())
        ->post(route('conexion.store'))
        ->assertForbidden();
});
