<?php

use App\Enums\Arca\ResultadoLlamada;
use App\Enums\Arca\TipoComprobante;
use App\Exceptions\Arca\ErrorDeWsfe;
use App\Models\Certificado;
use App\Models\Emisor;
use App\Models\LlamadaArca;
use App\Services\Arca\ClienteWsfe;
use Tests\Support\Arca\RespuestasArca;

beforeEach(function () {
    $this->arca = arcaFalso()->conTicket('TOKEN-WSFE', 'SIGN-WSFE');
    $this->emisor = Emisor::factory()->create();
    Certificado::factory()->vigente()->for($this->emisor)->create();
});

it('FEDummy no pide ticket', function () {
    $this->arca->wsfe->responder('FEDummy', RespuestasArca::dummy());

    expect(app(ClienteWsfe::class)->dummy($this->emisor)->todoOk())->toBeTrue();
    $this->arca->wsaa->assertNoLlamo('loginCms');
});

it('pide el último autorizado con el ticket y el CUIT del emisor', function () {
    $this->arca->wsfe->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(41, 3));

    $numero = app(ClienteWsfe::class)->ultimoAutorizado($this->emisor, 3, TipoComprobante::FacturaC);

    expect($numero)->toBe(41)
        ->and($this->arca->wsfe->parametrosDe('FECompUltimoAutorizado'))->toBe([
            'Auth' => ['Token' => 'TOKEN-WSFE', 'Sign' => 'SIGN-WSFE', 'Cuit' => $this->emisor->cuit],
            'PtoVta' => 3,
            'CbteTipo' => 11,
        ]);
});

it('convierte los Errors en ErrorDeWsfe, venga el Err suelto o en lista', function (bool $comoArray) {
    $this->arca->wsfe->responder('FECompUltimoAutorizado', RespuestasArca::conErrores('FECompUltimoAutorizado', [[600, 'ValidacionDeToken: No valido token']], $comoArray));

    expect(fn () => app(ClienteWsfe::class)->ultimoAutorizado($this->emisor, 1, TipoComprobante::FacturaC))
        ->toThrow(fn (ErrorDeWsfe $e) => expect($e->tieneCodigo(600))->toBeTrue());

    expect(LlamadaArca::query()->where('metodo', 'FECompUltimoAutorizado')->sole())
        ->resultado->toBe(ResultadoLlamada::ErrorDeArca)
        ->codigo->toBe('600');
})->with(['suelto' => false, 'en lista' => true]);

it('consulta un comprobante y lee sus fechas YYYYMMDD', function () {
    $this->arca->wsfe->responder('FECompConsultar', RespuestasArca::comprobante());

    $comprobante = app(ClienteWsfe::class)->consultar($this->emisor, 1, TipoComprobante::FacturaC, 8);

    expect($comprobante)
        ->numero->toBe(8)
        ->cae->toBe('76461234567890')
        ->resultado->toBe('A')
        ->and($comprobante?->fecha->toDateString())->toBe('2026-11-16')
        ->and($comprobante?->caeVencimiento?->toDateString())->toBe('2026-11-26');

    expect($this->arca->wsfe->parametrosDe('FECompConsultar')['FeCompConsReq'])
        ->toBe(['CbteTipo' => 11, 'CbteNro' => 8, 'PtoVta' => 1]);
});

it('un comprobante que ARCA no tiene es null, no un error', function () {
    $this->arca->wsfe->responder('FECompConsultar', RespuestasArca::sinResultados('FECompConsultar'));

    expect(app(ClienteWsfe::class)->consultar($this->emisor, 1, TipoComprobante::FacturaC, 99))->toBeNull();
});

it('lista los puntos de venta, y en homologación vacío no es error', function () {
    $this->arca->wsfe
        ->responder('FEParamGetPtosVenta', RespuestasArca::puntosDeVenta([[2, 'CAE - Factura Electronica - Monotributo - Web Services', 'N']]))
        ->responder('FEParamGetPtosVenta', RespuestasArca::sinResultados('FEParamGetPtosVenta'));

    expect(app(ClienteWsfe::class)->puntosDeVenta($this->emisor))->toBe([
        ['numero' => 2, 'tipo' => 'CAE - Factura Electronica - Monotributo - Web Services', 'bloqueado' => false, 'baja' => null],
    ])->and(app(ClienteWsfe::class)->puntosDeVenta($this->emisor))->toBe([]);
});

it('usa un solo ticket para varias llamadas', function () {
    $this->arca->wsfe
        ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(1))
        ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(1));

    $cliente = app(ClienteWsfe::class);
    $cliente->ultimoAutorizado($this->emisor, 1, TipoComprobante::FacturaC);
    $cliente->ultimoAutorizado($this->emisor, 1, TipoComprobante::FacturaC);

    expect($this->arca->wsaa->llamadas('loginCms'))->toHaveCount(1);
});
