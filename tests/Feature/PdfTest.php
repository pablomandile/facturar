<?php

use App\Enums\Arca\Concepto;
use App\Enums\Arca\CondicionIvaReceptor;
use App\Enums\Arca\TipoDocumento;
use App\Models\Comprobante;
use App\Models\Emisor;
use App\Models\PuntoDeVenta;
use App\Services\Facturas\ArmadorDeQr;
use App\Services\Facturas\GeneradorDePdf;

beforeEach(function () {
    $this->emisor = Emisor::factory()->create(['cuit' => '20111111112', 'razon_social' => 'Pablo Mandile']);
    $this->punto = PuntoDeVenta::factory()->for($this->emisor)->create(['numero' => 3]);
});

it('arma el QR de ARCA con los datos y tipos de la RG 4892', function () {
    $comprobante = Comprobante::factory()->paraPunto($this->punto)->autorizado(42, '76461234567890')->create([
        'fecha_emision' => '2026-11-16',
        'importe_neto' => 150_050,
        'importe_total' => 150_050,
        'receptor_tipo_documento' => TipoDocumento::Cuit,
        'receptor_numero_documento' => '30500010912',
        'receptor_condicion_iva' => CondicionIvaReceptor::ResponsableInscripto,
    ]);

    $qr = app(ArmadorDeQr::class);

    expect($qr->datos($comprobante))->toBe([
        'ver' => 1,
        'fecha' => '2026-11-16',
        'cuit' => 20111111112,
        'ptoVta' => 3,
        'tipoCmp' => 11,
        'nroCmp' => 42,
        'importe' => 1500.5,
        'moneda' => 'PES',
        'ctz' => 1.0,
        'tipoDocRec' => 80,
        'nroDocRec' => 30500010912,
        'tipoCodAut' => 'E',
        'codAut' => 76461234567890,
    ]);

    $url = $qr->url($comprobante);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $consulta);
    expect($url)->toStartWith('https://www.afip.gob.ar/fe/qr/?p=')
        ->and(json_decode(base64_decode($consulta['p']), true)['nroCmp'])->toBe(42);
});

it('a consumidor final sin identificar no informa receptor en el QR', function () {
    $comprobante = Comprobante::factory()->paraPunto($this->punto)->autorizado(1)->create();

    expect(app(ArmadorDeQr::class)->datos($comprobante))->not->toHaveKeys(['tipoDocRec', 'nroDocRec']);
});

it('la imagen del QR es un PNG', function () {
    $comprobante = Comprobante::factory()->paraPunto($this->punto)->autorizado(1)->create();
    $imagen = app(ArmadorDeQr::class)->imagen($comprobante);

    expect($imagen)->toStartWith('data:image/png;base64,')
        ->and(substr(base64_decode(substr($imagen, 22)), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});

it('descarga el PDF de una factura autorizada', function () {
    $comprobante = Comprobante::factory()->paraPunto($this->punto)->autorizado(42)->create(['fecha_emision' => '2026-11-16']);

    $respuesta = $this->actingAs($this->emisor->usuario)->get(route('comprobantes.pdf', $comprobante));

    $respuesta->assertOk()->assertDownload('MANDILE_PABLO_NOV_20111111112_011_00003-00000042.pdf');
    expect(substr((string) $respuesta->getContent(), 0, 5))->toBe('%PDF-');
});

describe('el nombre del archivo', function () {
    // APELLIDO_NOMBRE_MES_CUIT_011_número: el mismo al bajarlo, al mandarlo y al exportar.

    it('lleva el mes del período facturado, aunque se emita al mes siguiente', function () {
        $comprobante = Comprobante::factory()->paraPunto($this->punto)->autorizado(1)->create([
            'concepto' => Concepto::Servicios,
            'fecha_emision' => '2026-12-03',
            'fecha_servicio_desde' => '2026-11-01',
            'fecha_servicio_hasta' => '2026-11-30',
        ]);

        expect(app(GeneradorDePdf::class)->nombreDeArchivo($comprobante))->toBe('MANDILE_PABLO_NOV_20111111112_011_00003-00000001.pdf');
    });

    it('sin período, el mes de la emisión', function () {
        $comprobante = Comprobante::factory()->paraPunto($this->punto)->autorizado(2)->create(['fecha_emision' => '2026-01-15']);

        expect(app(GeneradorDePdf::class)->nombreDeArchivo($comprobante))->toBe('MANDILE_PABLO_ENE_20111111112_011_00003-00000002.pdf');
    });

    it('usa el nombre elegido en Datos fiscales, normalizado', function () {
        $this->actingAs($this->emisor->usuario)->put(route('emisor.update'), [
            'cuit' => $this->emisor->cuit,
            'razon_social' => 'Pablo Mandile',
            'nombre_archivos' => 'de la Peña  José',
            'domicilio_fiscal' => $this->emisor->domicilio_fiscal,
            'inicio_actividades' => $this->emisor->inicio_actividades->toDateString(),
            'ambiente' => $this->emisor->ambiente->value,
        ])->assertSessionHasNoErrors();

        $comprobante = Comprobante::factory()->paraPunto($this->punto)->autorizado(3)->create(['fecha_emision' => '2026-11-16']);

        expect($this->emisor->fresh()?->nombre_archivos)->toBe('DE_LA_PENA_JOSE')
            ->and(app(GeneradorDePdf::class)->nombreDeArchivo($comprobante))->toBe('DE_LA_PENA_JOSE_NOV_20111111112_011_00003-00000003.pdf');
    });

    it('sin nombre elegido, da vuelta la razón social', function () {
        expect(Emisor::nombreDeArchivoSugerido('Pablo Mandile'))->toBe('MANDILE_PABLO')
            ->and(Emisor::nombreDeArchivoSugerido('María José Núñez'))->toBe('NUNEZ_MARIA_JOSE')
            ->and(Emisor::nombreDeArchivoSugerido('Estudio'))->toBe('ESTUDIO');
    });
});

it('un borrador no tiene PDF', function () {
    $comprobante = Comprobante::factory()->paraPunto($this->punto)->create();

    $this->actingAs($this->emisor->usuario)->get(route('comprobantes.pdf', $comprobante))->assertNotFound();
});

it('no deja bajar el PDF de otra cuenta', function () {
    $ajeno = Comprobante::factory()->autorizado(1)->create();

    $this->actingAs($this->emisor->usuario)->get(route('comprobantes.pdf', $ajeno))->assertForbidden();
});
