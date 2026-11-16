<?php

use App\Enums\Arca\Ambiente;
use App\Enums\Arca\TipoDocumento;
use App\Enums\EstadoComprobante;
use App\Models\Certificado;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Emisor;
use App\Models\PuntoDeVenta;
use App\Services\Arca\RelojFiscal;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Arca\RespuestasArca;

beforeEach(function () {
    $this->emisor = Emisor::factory()->create();
    $this->punto = PuntoDeVenta::factory()->for($this->emisor)->create(['numero' => 1]);
    $this->actingAs($this->emisor->usuario);
});

function datosFactura(array $cambios = []): array
{
    return [
        'punto_de_venta_id' => test()->punto->id,
        'cliente_id' => '',
        'concepto' => 1,
        'fecha_emision' => (new RelojFiscal)->hoy()->toDateString(),
        'items' => [
            ['descripcion' => 'Clase de yoga', 'cantidad' => '4', 'precio_unitario' => '2.500,50'],
            ['descripcion' => '', 'cantidad' => '1', 'precio_unitario' => ''],
        ],
        ...$cambios,
    ];
}

it('guarda un borrador a consumidor final con los totales en centavos', function () {
    $this->post(route('comprobantes.store'), datosFactura())->assertSessionHasNoErrors();

    $comprobante = Comprobante::query()->with('items')->sole();
    expect($comprobante)
        ->estado->toBe(EstadoComprobante::Borrador)
        ->importe_total->toBe(1_000_200)
        ->receptor_tipo_documento->toBe(TipoDocumento::ConsumidorFinal)
        ->receptor_numero_documento->toBe('0')
        ->and($comprobante->items)->toHaveCount(1)
        ->and($comprobante->items[0]->precio_unitario)->toBe(250_050);
});

it('copia los datos del cliente, que después no cambian si se edita el cliente', function () {
    $cliente = Cliente::factory()->for($this->emisor)->create(['nombre' => 'Antes']);

    $this->post(route('comprobantes.store'), datosFactura(['cliente_id' => $cliente->id]))->assertSessionHasNoErrors();
    $cliente->update(['nombre' => 'Después']);

    expect(Comprobante::query()->sole()->receptor_nombre)->toBe('Antes');
});

it('valida lo que ARCA rechazaría', function (array $cambios, string $campo) {
    $this->post(route('comprobantes.store'), datosFactura($cambios))->assertSessionHasErrors($campo);
})->with([
    'sin ítems' => [['items' => [['descripcion' => '', 'cantidad' => '1', 'precio_unitario' => '']]], 'items'],
    'precio con tres decimales' => [['items' => [['descripcion' => 'X', 'cantidad' => '1', 'precio_unitario' => '10.555']]], 'items.0.precio_unitario'],
    'cantidad cero' => [['items' => [['descripcion' => 'X', 'cantidad' => '0', 'precio_unitario' => '10']]], 'items.0.cantidad'],
    'fecha fuera de la ventana' => [['fecha_emision' => (new RelojFiscal)->hoy()->subDays(6)->toDateString()], 'fecha_emision'],
    'servicio sin período' => [['concepto' => 2], 'fecha_servicio_desde'],
    'período al revés' => [['concepto' => 2, 'fecha_servicio_desde' => '2026-11-30', 'fecha_servicio_hasta' => '2026-11-01', 'fecha_vto_pago' => (new RelojFiscal)->hoy()->toDateString()], 'fecha_servicio_hasta'],
    'punto de venta ajeno' => [['punto_de_venta_id' => 999], 'punto_de_venta_id'],
]);

it('no acepta el cliente ni el punto de venta de otra cuenta', function () {
    $ajeno = Cliente::factory()->create();
    $puntoAjeno = PuntoDeVenta::factory()->create();

    $this->post(route('comprobantes.store'), datosFactura(['cliente_id' => $ajeno->id, 'punto_de_venta_id' => $puntoAjeno->id]))
        ->assertSessionHasErrors(['cliente_id', 'punto_de_venta_id']);
});

it('emite al guardar y avisa con el CAE', function () {
    Certificado::factory()->vigente()->for($this->emisor)->create();
    $arca = arcaFalso()->conTicket();
    $arca->wsfe
        ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(41))
        ->responder('FECAESolicitar', RespuestasArca::cae(cae: '76460000000042'));

    $respuesta = $this->post(route('comprobantes.store'), datosFactura(['emitir' => '1']));

    $comprobante = Comprobante::query()->sole();
    $respuesta->assertRedirect(route('comprobantes.show', $comprobante))
        ->assertInertiaFlash('toast.type', 'success');
    expect($comprobante)->estado->toBe(EstadoComprobante::Autorizado)->numero->toBe(42);
});

it('si ARCA rechaza, vuelve al detalle con el motivo', function () {
    Certificado::factory()->vigente()->for($this->emisor)->create();
    $arca = arcaFalso()->conTicket();
    $arca->wsfe
        ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(0))
        ->responder('FECAESolicitar', RespuestasArca::cae('R', observaciones: [[10048, 'El campo ImpTotal es invalido']]));

    $this->post(route('comprobantes.store'), datosFactura(['emitir' => '1']))
        ->assertInertiaFlash('toast.type', 'error');

    $comprobante = Comprobante::query()->sole();
    expect($comprobante->estado)->toBe(EstadoComprobante::Rechazado);

    $this->get(route('comprobantes.show', $comprobante))
        ->assertInertia(fn (Assert $p) => $p
            ->component('comprobantes/Detalle')
            ->where('comprobante.respuesta_arca.observaciones.0.codigo', 10048)
            ->where('puede.emitir', true));
});

it('editar un rechazado lo vuelve a borrador', function () {
    $comprobante = Comprobante::factory()->paraPunto($this->punto)->create(['estado' => EstadoComprobante::Rechazado, 'respuesta_arca' => ['observaciones' => []]]);

    $this->put(route('comprobantes.update', $comprobante), datosFactura())->assertSessionHasNoErrors();

    expect($comprobante->fresh())->estado->toBe(EstadoComprobante::Borrador)->respuesta_arca->toBeNull();
});

it('una autorizada no se edita, no se borra y no se vuelve a emitir', function () {
    $comprobante = Comprobante::factory()->paraPunto($this->punto)->autorizado(5)->create();

    $this->get(route('comprobantes.edit', $comprobante))->assertForbidden();
    $this->put(route('comprobantes.update', $comprobante), datosFactura())->assertForbidden();
    $this->delete(route('comprobantes.destroy', $comprobante))->assertForbidden();
    $this->post(route('comprobantes.emitir', $comprobante))->assertForbidden();
});

it('borra un borrador', function () {
    $comprobante = Comprobante::factory()->paraPunto($this->punto)->create();

    $this->delete(route('comprobantes.destroy', $comprobante))->assertRedirect(route('comprobantes.index'));

    expect(Comprobante::query()->count())->toBe(0);
});

it('lista las facturas del emisor en su ambiente, filtrables por estado', function () {
    Comprobante::factory()->paraPunto($this->punto)->autorizado(1)->create();
    Comprobante::factory()->paraPunto($this->punto)->create();
    Comprobante::factory()->create();
    $puntoProduccion = PuntoDeVenta::factory()->for($this->emisor)->create(['ambiente' => Ambiente::Produccion, 'numero' => 2]);
    Comprobante::factory()->paraPunto($puntoProduccion)->create();

    $this->get(route('comprobantes.index'))
        ->assertInertia(fn (Assert $p) => $p->component('comprobantes/Index')->has('comprobantes.data', 2));

    $this->get(route('comprobantes.index', ['estado' => 'autorizado']))
        ->assertInertia(fn (Assert $p) => $p->has('comprobantes.data', 1)->where('comprobantes.data.0.numero', '00001-00000001'));
});

it('no deja ver la factura de otra cuenta', function () {
    $this->get(route('comprobantes.show', Comprobante::factory()->create()))->assertForbidden();
});
