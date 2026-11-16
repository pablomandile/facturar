<?php

use App\Enums\Arca\Ambiente;
use App\Enums\EstadoComprobante;
use App\Exceptions\Arca\ComprobanteNoEmitible;
use App\Exceptions\Arca\ComprobanteRechazado;
use App\Exceptions\Arca\EmisionBloqueada;
use App\Exceptions\Arca\ErrorDeWsfe;
use App\Exceptions\Arca\FallaDeConexion;
use App\Models\Certificado;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Emisor;
use App\Models\PuntoDeVenta;
use App\Services\Arca\EmisorDeComprobantes;
use App\Services\Arca\RelojFiscal;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Arca\RespuestasArca;

beforeEach(function () {
    $this->arca = arcaFalso()->conTicket();
    $this->emisor = Emisor::factory()->create();
    Certificado::factory()->vigente()->for($this->emisor)->create();
    $this->punto = PuntoDeVenta::factory()->for($this->emisor)->create(['numero' => 3]);
});

function emitir(Comprobante $comprobante): Comprobante
{
    return app(EmisorDeComprobantes::class)->emitir($comprobante);
}

function borrador(array $estado = []): Comprobante
{
    return Comprobante::factory()->paraPunto(test()->punto)->create($estado);
}

function detalleEnviado(): array
{
    return test()->arca->wsfe->parametrosDe('FECAESolicitar')['FeCAEReq']['FeDetReq']['FECAEDetRequest'][0];
}

it('numera con el último de ARCA + 1 y guarda el CAE', function () {
    $this->arca->wsfe
        ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(7, 3))
        ->responder('FECAESolicitar', RespuestasArca::cae(cae: '76469999999999', vencimiento: '20261126'));
    $comprobante = borrador();

    emitir($comprobante);

    expect($comprobante->fresh())
        ->estado->toBe(EstadoComprobante::Autorizado)
        ->numero->toBe(8)
        ->cae->toBe('76469999999999')
        ->and($comprobante->fresh()?->cae_vencimiento?->toDateString())->toBe('2026-11-26');
});

it('arma el pedido con las reglas de la factura C', function () {
    $this->arca->wsfe
        ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(0, 3))
        ->responder('FECAESolicitar', RespuestasArca::cae());

    emitir(borrador(['importe_neto' => 150_050, 'importe_total' => 150_050]));

    $cabecera = $this->arca->wsfe->parametrosDe('FECAESolicitar')['FeCAEReq']['FeCabReq'];
    expect($cabecera)->toBe(['CantReg' => 1, 'PtoVta' => 3, 'CbteTipo' => 11])
        ->and(detalleEnviado())->toMatchArray([
            'Concepto' => 1,
            'DocTipo' => 99,
            'DocNro' => 0,
            'CbteDesde' => 1,
            'CbteHasta' => 1,
            'CbteFch' => (new RelojFiscal)->ymd((new RelojFiscal)->hoy()),
            'ImpTotal' => 1500.5,
            'ImpNeto' => 1500.5,
            'ImpIVA' => 0,
            'ImpTrib' => 0.0,
            'MonId' => 'PES',
            'MonCotiz' => 1.0,
            'CondicionIVAReceptorId' => 5,
        ])
        ->and(detalleEnviado())->not->toHaveKeys(['Iva', 'FchServDesde', 'FchServHasta', 'FchVtoPago']);
});

it('con servicios manda el período y el vencimiento del pago', function () {
    $this->arca->wsfe
        ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(0, 3))
        ->responder('FECAESolicitar', RespuestasArca::cae());
    $comprobante = Comprobante::factory()->paraPunto($this->punto)->deServicios()->create();

    emitir($comprobante);

    $reloj = new RelojFiscal;
    expect(detalleEnviado())->toMatchArray([
        'Concepto' => 2,
        'FchServDesde' => $reloj->ymd($comprobante->fecha_servicio_desde),
        'FchServHasta' => $reloj->ymd($comprobante->fecha_servicio_hasta),
        'FchVtoPago' => $reloj->ymd($comprobante->fecha_vto_pago),
    ]);
});

it('con un cliente informa su documento y su condición frente al IVA', function () {
    $this->arca->wsfe
        ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(0, 3))
        ->responder('FECAESolicitar', RespuestasArca::cae());
    $cliente = Cliente::factory()->for($this->emisor)->create(['numero_documento' => '30500010912']);

    emitir(borrador([
        'cliente_id' => $cliente->id,
        'receptor_tipo_documento' => $cliente->tipo_documento,
        'receptor_numero_documento' => $cliente->numero_documento,
        'receptor_nombre' => $cliente->nombre,
        'receptor_condicion_iva' => $cliente->condicion_iva,
    ]));

    expect(detalleEnviado())->toMatchArray(['DocTipo' => 80, 'DocNro' => 30500010912, 'CondicionIVAReceptorId' => 1]);
});

it('un rechazo guarda las observaciones y libera el número', function () {
    $this->arca->wsfe
        ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(7, 3))
        ->responder('FECAESolicitar', RespuestasArca::cae('R', observaciones: [[10015, 'El campo DocNro es invalido']]));
    $comprobante = borrador();

    expect(fn () => emitir($comprobante))->toThrow(ComprobanteRechazado::class, 'El campo DocNro es invalido');

    expect($comprobante->fresh())
        ->estado->toBe(EstadoComprobante::Rechazado)
        ->numero->toBeNull()
        ->respuesta_arca->toBe(['observaciones' => [['codigo' => 10015, 'mensaje' => 'El campo DocNro es invalido']], 'eventos' => []]);
});

it('si el número ya no era el próximo, lo pide de nuevo una sola vez', function () {
    $this->arca->wsfe
        ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(7, 3))
        ->responder('FECAESolicitar', RespuestasArca::cae('R', observaciones: [[10016, 'El numero o fecha del comprobante no se corresponde con el proximo a autorizar']]))
        ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(8, 3))
        ->responder('FECAESolicitar', RespuestasArca::cae());
    $comprobante = borrador();

    emitir($comprobante);

    expect($comprobante->fresh())->estado->toBe(EstadoComprobante::Autorizado)->numero->toBe(9)
        ->and($this->arca->wsfe->llamadas('FECAESolicitar'))->toHaveCount(2);
});

it('no reintenta más de una vez', function () {
    $rechazo = RespuestasArca::cae('R', observaciones: [[10016, 'No es el próximo']]);
    $this->arca->wsfe
        ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(7, 3))
        ->responder('FECAESolicitar', $rechazo)
        ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(8, 3))
        ->responder('FECAESolicitar', $rechazo);
    $comprobante = borrador();

    expect(fn () => emitir($comprobante))->toThrow(ComprobanteRechazado::class);
    expect($comprobante->fresh()?->estado)->toBe(EstadoComprobante::Rechazado);
});

describe('cuando ARCA no contesta', function () {
    it('si no se pudo conectar, queda con error y sin número', function () {
        $this->arca->wsfe
            ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(7, 3))
            ->fallar('FECAESolicitar', new FallaDeConexion('Could not connect to host', pudoHaberLlegado: false));
        $comprobante = borrador();

        expect(fn () => emitir($comprobante))->toThrow(FallaDeConexion::class);
        expect($comprobante->fresh())->estado->toBe(EstadoComprobante::Error)->numero->toBeNull();
    });

    it('si se cortó después de enviar y ARCA la tiene, queda autorizada', function () {
        $this->arca->wsfe
            ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(7, 3))
            ->fallar('FECAESolicitar', new FallaDeConexion('Error Fetching http headers', pudoHaberLlegado: true))
            ->responder('FECompConsultar', RespuestasArca::comprobante(['CbteDesde' => 8, 'PtoVta' => 3, 'ImpTotal' => 1000.0, 'CodAutorizacion' => '76461111111111']));
        $comprobante = borrador();

        emitir($comprobante);

        expect($comprobante->fresh())->estado->toBe(EstadoComprobante::Autorizado)->numero->toBe(8)->cae->toBe('76461111111111')
            ->and($this->arca->wsfe->parametrosDe('FECompConsultar')['FeCompConsReq'])->toBe(['CbteTipo' => 11, 'CbteNro' => 8, 'PtoVta' => 3]);
    });

    it('si se cortó y ARCA no la tiene, queda con error para reintentar', function () {
        $this->arca->wsfe
            ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(7, 3))
            ->fallar('FECAESolicitar', new FallaDeConexion('Error Fetching http headers', pudoHaberLlegado: true))
            ->responder('FECompConsultar', RespuestasArca::sinResultados('FECompConsultar'));
        $comprobante = borrador();

        expect(fn () => emitir($comprobante))->toThrow(fn (FallaDeConexion $e) => expect($e->pudoHaberLlegado)->toBeFalse());
        expect($comprobante->fresh())->estado->toBe(EstadoComprobante::Error)->numero->toBeNull();
    });

    it('si tampoco se puede consultar, queda pendiente con su número', function () {
        $this->arca->wsfe
            ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(7, 3))
            ->fallar('FECAESolicitar', new FallaDeConexion('Error Fetching http headers', pudoHaberLlegado: true))
            ->fallar('FECompConsultar', new FallaDeConexion('Could not connect to host', pudoHaberLlegado: false));
        $comprobante = borrador();

        expect(fn () => emitir($comprobante))->toThrow(FallaDeConexion::class);
        expect($comprobante->fresh())->estado->toBe(EstadoComprobante::Pendiente)->numero->toBe(8);
    });

    it('un error en el cuerpo de la respuesta deja el comprobante con error', function () {
        $this->arca->wsfe
            ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(7, 3))
            ->responder('FECAESolicitar', RespuestasArca::conErrores('FECAESolicitar', [[501, 'Error interno de base de datos']]));
        $comprobante = borrador();

        expect(fn () => emitir($comprobante))->toThrow(ErrorDeWsfe::class);
        expect($comprobante->fresh())
            ->estado->toBe(EstadoComprobante::Error)
            ->respuesta_arca->errores->toBe([['codigo' => 501, 'mensaje' => 'Error interno de base de datos']]);
    });
});

describe('lo que frena antes de llamar a ARCA', function () {
    it('un pendiente en el mismo punto de venta', function () {
        Comprobante::factory()->paraPunto($this->punto)->create(['estado' => EstadoComprobante::Pendiente, 'numero' => 8, 'enviado_en' => now()]);

        expect(fn () => emitir(borrador()))->toThrow(EmisionBloqueada::class, 'esperando confirmación');
        $this->arca->wsfe->assertNoLlamo('FECompUltimoAutorizado');
    });

    it('otra emisión en curso en el mismo punto de venta', function () {
        config(['arca.espera_lock_emision_segundos' => 0]);
        $lock = Cache::lock("arca:emision:{$this->emisor->id}:homologacion:3:11", 120);
        $lock->get();

        expect(fn () => emitir(borrador()))->toThrow(EmisionBloqueada::class, 'otra factura emitiéndose');

        $lock->release();
    });

    it('ARCA con un número menor al último que autorizó la app', function () {
        Comprobante::factory()->paraPunto($this->punto)->autorizado(10)->create();
        $this->arca->wsfe->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(5, 3));
        $comprobante = borrador();

        expect(fn () => emitir($comprobante))->toThrow(EmisionBloqueada::class, 'la última factura de este punto de venta es la 5');
        expect($comprobante->fresh()?->estado)->toBe(EstadoComprobante::Borrador);
        $this->arca->wsfe->assertNoLlamo('FECAESolicitar');
    });

    it('consumidor final sin identificar desde el tope', function () {
        $comprobante = borrador(['importe_neto' => 1_000_000_000, 'importe_total' => 1_000_000_000]);
        $comprobante->items()->update(['precio_unitario' => 1_000_000_000, 'importe' => 1_000_000_000]);

        expect(fn () => emitir($comprobante))->toThrow(ComprobanteNoEmitible::class, 'identificar al consumidor final');
    });

    it('el tope lo puede pisar el emisor', function () {
        $this->emisor->forceFill(['tope_consumidor_final' => 50_000])->save();
        $comprobante = borrador();

        expect(fn () => emitir($comprobante->fresh()))->toThrow(ComprobanteNoEmitible::class, '$ 500,00');
    });

    it('una fecha fuera de la ventana que acepta ARCA', function () {
        $comprobante = borrador(['fecha_emision' => (new RelojFiscal)->hoy()->subDays(6)->toDateString()]);

        expect(fn () => emitir($comprobante))->toThrow(ComprobanteNoEmitible::class, 'hasta 5 días');
    });

    it('una fecha anterior a la última factura del punto de venta', function () {
        Comprobante::factory()->paraPunto($this->punto)->autorizado(7)->create(['fecha_emision' => (new RelojFiscal)->hoy()->toDateString()]);
        $this->arca->wsfe->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(7, 3));

        expect(fn () => emitir(borrador(['fecha_emision' => (new RelojFiscal)->hoy()->subDay()->toDateString()])))
            ->toThrow(ComprobanteNoEmitible::class, 'anterior a la de la última factura');
    });

    it('un punto de venta de otro ambiente', function () {
        $this->emisor->forceFill(['ambiente' => Ambiente::Produccion])->save();
        Certificado::factory()->vigente()->for($this->emisor)->create(['ambiente' => Ambiente::Produccion]);

        expect(fn () => emitir(borrador()))->toThrow(ComprobanteNoEmitible::class, 'otro ambiente');
    });
});

it('una factura autorizada no se puede modificar ni borrar', function () {
    $comprobante = Comprobante::factory()->paraPunto($this->punto)->autorizado(1)->create();

    expect(fn () => $comprobante->forceFill(['importe_total' => 1])->save())->toThrow(LogicException::class)
        ->and(fn () => $comprobante->delete())->toThrow(LogicException::class);
});

describe('reconciliar pendientes', function () {
    it('confirma el que ARCA autorizó', function () {
        $pendiente = Comprobante::factory()->paraPunto($this->punto)->create(['estado' => EstadoComprobante::Pendiente, 'numero' => 8, 'enviado_en' => now()->subMinutes(5)]);
        $this->arca->wsfe->responder('FECompConsultar', RespuestasArca::comprobante(['CbteDesde' => 8, 'PtoVta' => 3, 'ImpTotal' => 1000.0]));

        $this->artisan('arca:reconciliar-pendientes')->assertSuccessful();

        expect($pendiente->fresh())->estado->toBe(EstadoComprobante::Autorizado)->cae->toBe('76461234567890');
    });

    it('si ARCA tiene otra factura con ese número, la deja con error', function () {
        $pendiente = Comprobante::factory()->paraPunto($this->punto)->create(['estado' => EstadoComprobante::Pendiente, 'numero' => 8, 'enviado_en' => now()->subMinutes(5)]);
        $this->arca->wsfe->responder('FECompConsultar', RespuestasArca::comprobante(['CbteDesde' => 8, 'PtoVta' => 3, 'ImpTotal' => 99.0]));

        $this->artisan('arca:reconciliar-pendientes')->assertSuccessful();

        expect($pendiente->fresh())->estado->toBe(EstadoComprobante::Error)->numero->toBeNull();
    });

    it('no toca los que se acaban de mandar', function () {
        Comprobante::factory()->paraPunto($this->punto)->create(['estado' => EstadoComprobante::Pendiente, 'numero' => 8, 'enviado_en' => now()]);

        $this->artisan('arca:reconciliar-pendientes')->assertSuccessful();

        $this->arca->wsfe->assertNoLlamo('FECompConsultar');
    });
});
