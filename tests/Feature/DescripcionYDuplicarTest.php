<?php

use App\Enums\Arca\Concepto;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\ComprobanteItem;
use App\Models\Emisor;
use App\Models\PuntoDeVenta;
use App\Services\Arca\RelojFiscal;
use App\Services\Facturas\GeneradorDePdf;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->emisor = Emisor::factory()->create();
    $this->punto = PuntoDeVenta::factory()->for($this->emisor)->create(['numero' => 1]);
    $this->actingAs($this->emisor->usuario);
});

const PLANTILLA = "Facturación del período {desde} al {hasta}, Cuenta Bco. Galicia N° 400201251943.\n{importe_en_letras}.";

/**
 * La factura de octubre, emitida el 5 de noviembre con vencimiento a 15 días.
 */
function facturaDeOctubre(?Cliente $cliente = null): Comprobante
{
    $comprobante = Comprobante::factory()->paraPunto(test()->punto)->autorizado(7)->create([
        'concepto' => Concepto::Servicios,
        'cliente_id' => $cliente?->id,
        'fecha_emision' => '2026-11-05',
        'fecha_servicio_desde' => '2026-10-01',
        'fecha_servicio_hasta' => '2026-10-31',
        'fecha_vto_pago' => '2026-11-20',
        'importe_neto' => 216_916_900,
        'importe_total' => 216_916_900,
    ]);

    $comprobante->items()->create([
        'orden' => 1,
        'descripcion' => "Período del 01/10/2026 al 31/10/2026.\nDos millones … centavos.",
        'cantidad' => '1',
        'precio_unitario' => 216_916_900,
        'importe' => 216_916_900,
    ]);

    return $comprobante;
}

describe('la descripción habitual del cliente', function () {
    it('se guarda con el cliente y llega al formulario de la factura', function () {
        $this->post(route('clientes.store'), [
            'tipo_documento' => 80,
            'numero_documento' => '30-50001091-2',
            'nombre' => 'Ministerio de Prueba',
            'condicion_iva' => 4,
            'email' => 'pagos@ministerio.test',
            'plantilla_descripcion' => PLANTILLA,
        ])->assertSessionHasNoErrors();

        $cliente = Cliente::query()->sole();
        expect($cliente->plantilla_descripcion)->toBe(PLANTILLA);

        $this->get(route('comprobantes.create'))->assertInertia(fn (Assert $p) => $p
            ->where('clientes.0.id', $cliente->id)
            ->where('clientes.0.plantilla', PLANTILLA));
    });

    it('no acepta una de más de mil caracteres', function () {
        $cliente = Cliente::factory()->for($this->emisor)->create();

        $this->put(route('clientes.update', $cliente), [
            ...$cliente->only(['nombre', 'numero_documento', 'domicilio', 'email']),
            'tipo_documento' => $cliente->tipo_documento->value,
            'condicion_iva' => $cliente->condicion_iva->value,
            'plantilla_descripcion' => str_repeat('a', Cliente::PLANTILLA_MAXIMA + 1),
        ])->assertSessionHasErrors('plantilla_descripcion');
    });
});

describe('la descripción de un ítem', function () {
    function facturaConDescripcion(string $descripcion): array
    {
        return [
            'punto_de_venta_id' => test()->punto->id,
            'cliente_id' => '',
            'concepto' => 1,
            'fecha_emision' => (new RelojFiscal)->hoy()->toDateString(),
            'items' => [['descripcion' => $descripcion, 'cantidad' => '1', 'precio_unitario' => '100']],
        ];
    }

    it('acepta varias líneas y hasta mil caracteres, y guarda los saltos como \n', function () {
        $larga = "Primera línea\r\nSegunda línea\r\n".str_repeat('x', 900);

        $this->post(route('comprobantes.store'), facturaConDescripcion($larga))->assertSessionHasNoErrors();

        expect(ComprobanteItem::query()->sole()->descripcion)
            ->toStartWith("Primera línea\nSegunda línea\n")
            ->not->toContain("\r");
    });

    it('no acepta más de mil caracteres', function () {
        $this->post(route('comprobantes.store'), facturaConDescripcion(str_repeat('x', ComprobanteItem::DESCRIPCION_MAXIMA + 1)))
            ->assertSessionHasErrors('items.0.descripcion');
    });

    it('el PDF respeta los saltos de línea y escapa el HTML', function () {
        $comprobante = facturaDeOctubre();
        $comprobante->items()->update(['descripcion' => "Línea uno\n<b>Línea dos</b>"]);

        $html = view('pdf.factura-c', app(GeneradorDePdf::class)->datos($comprobante->fresh()))->render();

        expect($html)->toContain('Línea uno<br />')
            ->toContain('&lt;b&gt;Línea dos&lt;/b&gt;');
    });
});

describe('duplicar una factura', function () {
    beforeEach(function () {
        // 3 de diciembre: la de octubre se emitió en noviembre, un mes atrás.
        $this->travelTo(CarbonImmutable::parse('2026-12-03 12:00:00', 'UTC'));
    });

    it('abre el formulario con el mismo cliente e ítems y el período corrido un mes', function () {
        $cliente = Cliente::factory()->for($this->emisor)->create(['plantilla_descripcion' => PLANTILLA]);
        $original = facturaDeOctubre($cliente);

        $this->get(route('comprobantes.duplicar', $original))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('comprobantes/Formulario')
                ->where('comprobante', null)
                ->where('duplicadoDe', 'la factura 00001-00000007')
                ->where('base.cliente_id', $cliente->id)
                ->where('base.punto_de_venta_id', $this->punto->id)
                ->where('base.concepto', Concepto::Servicios->value)
                ->where('base.fecha_emision', '2026-12-03')
                // Octubre completo → noviembre completo, aunque tenga 30 días.
                ->where('base.fecha_servicio_desde', '2026-11-01')
                ->where('base.fecha_servicio_hasta', '2026-11-30')
                // Conserva los 15 días de plazo.
                ->where('base.fecha_vto_pago', '2026-12-18')
                ->where('base.items.0.precio_unitario', '2169169.00')
                ->where('base.items.0.cantidad', '1'));
    });

    it('no guarda nada hasta que se toca Guardar o Emitir', function () {
        $original = facturaDeOctubre();

        $this->get(route('comprobantes.duplicar', $original))->assertOk();

        expect(Comprobante::query()->count())->toBe(1);
    });

    it('corre fecha por fecha un período que no es un mes completo', function () {
        $original = facturaDeOctubre();
        $original->forceFill(['fecha_servicio_desde' => '2026-10-10', 'fecha_servicio_hasta' => '2026-11-09'])->saveQuietly();

        $this->get(route('comprobantes.duplicar', $original))->assertInertia(fn (Assert $p) => $p
            ->where('base.fecha_servicio_desde', '2026-11-10')
            ->where('base.fecha_servicio_hasta', '2026-12-09'));
    });

    it('si el cliente ya no existe, lo deja para elegir', function () {
        $cliente = Cliente::factory()->for($this->emisor)->create();
        $original = facturaDeOctubre($cliente);
        $cliente->delete();

        $this->get(route('comprobantes.duplicar', $original))
            ->assertInertia(fn (Assert $p) => $p->where('base.cliente_id', null));
    });

    it('si el punto de venta ya no está activo, propone el primero activo', function () {
        $original = facturaDeOctubre();
        $otro = PuntoDeVenta::factory()->for($this->emisor)->create(['numero' => 2]);
        $this->punto->update(['activo' => false]);

        $this->get(route('comprobantes.duplicar', $original))
            ->assertInertia(fn (Assert $p) => $p->where('base.punto_de_venta_id', $otro->id));
    });

    it('no deja duplicar la factura de otra cuenta', function () {
        $ajena = Comprobante::factory()->create();

        $this->get(route('comprobantes.duplicar', $ajena))->assertForbidden();
    });
});
