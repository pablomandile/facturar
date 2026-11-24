<?php

use App\Enums\Arca\Ambiente;
use App\Enums\Arca\Concepto;
use App\Enums\EstadoComprobante;
use App\Mail\FacturaPorEmail;
use App\Models\Certificado;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Emisor;
use App\Models\EnvioDeComprobante;
use App\Models\PuntoDeVenta;
use App\Models\User;
use App\Services\Arca\RelojFiscal;
use App\Services\Facturas\EnviadorDeFacturas;
use App\Services\Facturas\GeneradorDePdf;
use App\Services\Facturas\TextoDelEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Arca\RespuestasArca;

beforeEach(function () {
    Mail::fake();
    $this->emisor = Emisor::factory()->enProduccion()->create(['razon_social' => 'Pablo Ejemplo']);
    $this->punto = PuntoDeVenta::factory()->for($this->emisor)->create(['numero' => 3, 'ambiente' => Ambiente::Produccion]);
    $this->cliente = Cliente::factory()->for($this->emisor)->create(['nombre' => 'Ministerio de Prueba', 'email' => 'pagos@ministerio.test']);
    $this->usuario = $this->emisor->usuario;
    $this->actingAs($this->usuario);
});

function autorizada(array $estado = []): Comprobante
{
    return Comprobante::factory()->paraPunto(test()->punto)->autorizado(8)->create([
        'cliente_id' => test()->cliente->id,
        'receptor_nombre' => 'Ministerio de Prueba',
        ...$estado,
    ]);
}

function envio(Comprobante $comprobante, array $datos = []): TestResponse
{
    return test()->post(route('comprobantes.enviar', $comprobante), [
        'para' => 'pagos@ministerio.test',
        'copia_a_mi' => '1',
        'asunto' => 'Factura de noviembre — Pablo Ejemplo',
        'texto' => "Hola:\r\nTe mando la factura.",
        ...$datos,
    ]);
}

describe('el envío desde el detalle', function () {
    it('manda la factura con el PDF, la copia y las respuestas a la cuenta', function () {
        $comprobante = autorizada();

        envio($comprobante)->assertRedirect(route('comprobantes.show', $comprobante))->assertSessionHasNoErrors();

        Mail::assertSent(FacturaPorEmail::class, function (FacturaPorEmail $correo) use ($comprobante): bool {
            // El asunto retocado en el diálogo, no el de la plantilla.
            $correo->assertHasSubject('Factura de noviembre — Pablo Ejemplo');

            return $correo->hasTo('pagos@ministerio.test')
                && $correo->hasCc($this->usuario->email)
                && $correo->hasReplyTo($this->usuario->email)
                && $correo->hasFrom((string) config('mail.from.address'), 'Pablo Ejemplo vía '.config('app.name'))
                && $correo->hasAttachedData($correo->pdf, app(GeneradorDePdf::class)->nombreDeArchivo($comprobante), ['mime' => 'application/pdf'])
                && str_starts_with(app(GeneradorDePdf::class)->nombreDeArchivo($comprobante), 'EJEMPLO_PABLO_')
                && str_starts_with($correo->pdf, '%PDF')
                && $correo->texto === "Hola:\nTe mando la factura."
                && $correo->comprobante->is($comprobante);
        });

        expect(EnvioDeComprobante::query()->sole())
            ->destinatarios->toBe(['pagos@ministerio.test'])
            ->con_copia->toBeTrue()
            ->asunto->toBe('Factura de noviembre — Pablo Ejemplo')
            ->enviado->toBeTrue()
            ->error->toBeNull();
    });

    it('acepta hasta tres direcciones, y sin copia', function () {
        envio(autorizada(), ['para' => 'a@x.test, b@x.test; c@x.test', 'copia_a_mi' => null])->assertSessionHasNoErrors();

        Mail::assertSent(FacturaPorEmail::class, fn (FacturaPorEmail $correo): bool => $correo->hasTo('a@x.test') && $correo->hasTo('c@x.test') && $correo->cc === []);
    });

    it('rechaza cuatro direcciones o una inválida', function () {
        envio(autorizada(), ['para' => 'a@x.test, b@x.test, c@x.test, d@x.test'])->assertSessionHasErrors('para');
        envio(autorizada(['numero' => 10]), ['asunto' => ''])->assertSessionHasErrors('asunto');
        envio(autorizada(['numero' => 9]), ['para' => 'esto-no-es-un-email'])->assertSessionHasErrors('para.0');

        Mail::assertNothingSent();
    });

    it('no manda una factura sin autorizar ni una ajena', function () {
        $borrador = Comprobante::factory()->paraPunto($this->punto)->create();
        $ajena = Comprobante::factory()->autorizado(1)->create();

        envio($borrador)->assertForbidden();
        envio($ajena)->assertForbidden();

        Mail::assertNothingSent();
    });

    it('frena a las 20 por hora', function () {
        $comprobante = autorizada();

        foreach (range(1, 20) as $intento) {
            RateLimiter::hit(md5('envios'.$this->usuario->id), 3600);
        }

        envio($comprobante)->assertTooManyRequests();
        Mail::assertNothingSent();
    });

    it('si el correo falla, lo registra y avisa, sin romper nada', function () {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Connection to smtp.hostinger.com:465 timed out'));
        $comprobante = autorizada();

        envio($comprobante)->assertRedirect(route('comprobantes.show', $comprobante));

        expect(EnvioDeComprobante::query()->sole())
            ->enviado->toBeFalse()
            ->error->toContain('timed out')
            ->and($comprobante->fresh()?->estado)->toBe(EstadoComprobante::Autorizado);
    });

    it('el detalle trae el texto completo y el historial', function () {
        $comprobante = autorizada();
        $comprobante->envios()->create(['destinatarios' => ['pagos@ministerio.test'], 'texto' => 'Hola', 'enviado' => true]);

        $this->get(route('comprobantes.show', $comprobante))->assertInertia(fn (Assert $p) => $p
            ->where('puede.enviar', true)
            ->where('email.para', 'pagos@ministerio.test')
            ->where('email.solo_propio', false)
            ->where('email.asunto', 'Factura C 00003-00000008 — Pablo Ejemplo')
            ->where('email.texto', fn (string $texto): bool => str_contains($texto, 'Hola, Ministerio de Prueba:') && str_contains($texto, '00003-00000008'))
            ->has('email.envios', 1));
    });
});

describe('en homologación', function () {
    beforeEach(function () {
        $this->emisor->forceFill(['ambiente' => Ambiente::Homologacion])->save();
        $this->punto->forceFill(['ambiente' => Ambiente::Homologacion])->save();
    });

    it('solo se manda al email de la cuenta', function () {
        $comprobante = autorizada(['ambiente' => Ambiente::Homologacion]);

        envio($comprobante)->assertSessionHasErrors('para');
        Mail::assertNothingSent();

        envio($comprobante, ['para' => mb_strtoupper($this->usuario->email)])->assertSessionHasNoErrors();
        Mail::assertSent(FacturaPorEmail::class, fn (FacturaPorEmail $correo): bool => $correo->hasTo($this->usuario->email));
    });

    it('el diálogo arranca con el email de la cuenta, fijo', function () {
        $this->get(route('comprobantes.show', autorizada(['ambiente' => Ambiente::Homologacion])))->assertInertia(fn (Assert $p) => $p
            ->where('email.para', $this->usuario->email)
            ->where('email.solo_propio', true));
    });
});

describe('el texto del email', function () {
    it('completa cada marcador con los datos de la factura', function () {
        $comprobante = autorizada([
            'concepto' => Concepto::Servicios,
            'fecha_emision' => '2026-11-05',
            'fecha_servicio_desde' => '2026-10-01',
            'fecha_servicio_hasta' => '2026-10-31',
            'fecha_vto_pago' => '2026-11-20',
            'importe_neto' => 216_916_900,
            'importe_total' => 216_916_900,
        ]);

        $texto = (new TextoDelEmail)->completar('{cliente}|{numero}|{fecha}|{desde}|{hasta}|{mes}|{anio}|{importe}|{vencimiento}|{cae}|{razon_social}|{otro}', $comprobante);

        expect($texto)->toBe('Ministerio de Prueba|00003-00000008|05/11/2026|01/10/2026|31/10/2026|octubre|2026|$ 2.169.169,00|20/11/2026|76461234567890|Pablo Ejemplo|{otro}');
    });

    it('{alias} es el alias del cliente, y sin alias, su nombre', function () {
        $comprobante = autorizada();

        expect((new TextoDelEmail)->completar('Hola, {alias}', $comprobante))->toBe('Hola, Ministerio de Prueba');

        $this->cliente->forceFill(['alias' => 'Ministerio'])->save();

        expect((new TextoDelEmail)->completar('Hola, {alias}', $comprobante->fresh()))->toBe('Hola, Ministerio')
            ->and((new TextoDelEmail)->para($comprobante->fresh()))->toStartWith('Hola, Ministerio:');
    });

    it('el alias se guarda con el cliente y se puede buscar por él', function () {
        $this->put(route('clientes.update', $this->cliente), [
            ...$this->cliente->only(['nombre', 'numero_documento', 'domicilio', 'email']),
            'tipo_documento' => $this->cliente->tipo_documento->value,
            'condicion_iva' => $this->cliente->condicion_iva->value,
            'alias' => 'Ministerio',
        ])->assertSessionHasNoErrors();

        expect($this->cliente->fresh()?->alias)->toBe('Ministerio');

        $this->get(route('clientes.index', ['q' => 'Minist']))
            ->assertInertia(fn (Assert $p) => $p->where('clientes.data.0.alias', 'Ministerio'));
    });

    it('sin período, el mes es el de la fecha de emisión', function () {
        $comprobante = autorizada(['fecha_emision' => '2026-11-05']);

        expect((new TextoDelEmail)->completar('{mes} {anio}|{desde}', $comprobante))->toBe('noviembre 2026|');
    });

    it('usa el de la cuenta, o el de por defecto si no hay', function () {
        $comprobante = autorizada();

        expect((new TextoDelEmail)->para($comprobante))->toStartWith('Hola, Ministerio de Prueba:');

        $this->put(route('comunicacion.update'), ['plantilla_email' => "Factura {numero}\r\nGracias"])->assertSessionHasNoErrors();

        expect($this->emisor->fresh()?->plantilla_email)->toBe("Factura {numero}\nGracias")
            ->and((new TextoDelEmail)->para($comprobante->fresh()))->toBe("Factura 00003-00000008\nGracias");

        $this->put(route('comunicacion.update'), ['plantilla_email' => ''])->assertSessionHasNoErrors();
        expect($this->emisor->fresh()?->plantilla_email)->toBeNull();
    });

    it('el asunto: el de la cuenta, con marcadores, en una sola línea', function () {
        $comprobante = autorizada(['fecha_emision' => '2026-11-05']);

        expect((new TextoDelEmail)->asuntoPara($comprobante))->toBe('Factura C 00003-00000008 — Pablo Ejemplo');

        $this->put(route('comunicacion.update'), [
            'asunto_email' => "Factura {mes}\r\n{anio} - {alias}",
            'plantilla_email' => '',
        ])->assertSessionHasNoErrors();

        expect($this->emisor->fresh()?->asunto_email)->toBe('Factura {mes} {anio} - {alias}')
            ->and((new TextoDelEmail)->asuntoPara($comprobante->fresh()))->toBe('Factura noviembre 2026 - Ministerio de Prueba');

        $this->put(route('comunicacion.update'), ['asunto_email' => str_repeat('a', TextoDelEmail::ASUNTO_MAXIMO + 1)])
            ->assertSessionHasErrors('asunto_email');
    });

    it('el envío automático sale con el asunto de la cuenta', function () {
        $this->emisor->forceFill(['asunto_email' => 'Honorarios {mes} {anio}'])->save();
        $comprobante = autorizada(['fecha_emision' => '2026-11-05']);

        app(EnviadorDeFacturas::class)->enviar($comprobante, ['pagos@ministerio.test'], true, 'Hola');

        Mail::assertSent(FacturaPorEmail::class, fn (FacturaPorEmail $correo): bool => $correo->asunto === 'Honorarios noviembre 2026');
    });

    it('se edita en Comunicación, y Datos fiscales ya no lo tiene', function () {
        $this->emisor->forceFill(['asunto_email' => 'Factura {mes}'])->save();

        $this->get(route('comunicacion.edit'))->assertInertia(fn (Assert $p) => $p
            ->component('comunicacion/Index')
            ->where('email.asunto', 'Factura {mes}')
            ->where('email.por_defecto', TextoDelEmail::POR_DEFECTO)
            ->has('email.marcadores', count(TextoDelEmail::MARCADORES)));

        $this->put(route('comunicacion.update'), ['asunto_email' => 'Otro', 'plantilla_email' => ''])
            ->assertRedirect(route('comunicacion.edit'));

        $this->get(route('emisor.edit'))->assertInertia(fn (Assert $p) => $p->missing('email'));
    });

    it('sin datos fiscales, Comunicación manda a cargarlos', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('comunicacion.edit'))
            ->assertRedirect(route('emisor.edit'));
    });

    it('no acepta uno de más de dos mil caracteres', function () {
        $this->put(route('comunicacion.update'), ['plantilla_email' => str_repeat('a', TextoDelEmail::PLANTILLA_MAXIMA + 1)])
            ->assertSessionHasErrors('plantilla_email');
    });
});

describe('enviarla al cliente cuando ARCA la autorice', function () {
    beforeEach(function () {
        $this->arca = arcaFalso()->conTicket();
        Certificado::factory()->vigente()->for($this->emisor)->create(['ambiente' => Ambiente::Produccion]);
    });

    function facturaNueva(array $cambios = []): array
    {
        return [
            'punto_de_venta_id' => test()->punto->id,
            'cliente_id' => test()->cliente->id,
            'concepto' => 1,
            'fecha_emision' => (new RelojFiscal)->hoy()->toDateString(),
            'enviar_al_autorizar' => '1',
            'items' => [['descripcion' => 'Honorarios', 'cantidad' => '1', 'precio_unitario' => '1000']],
            ...$cambios,
        ];
    }

    it('la manda al autorizarse desde el formulario, y el aviso lo dice', function () {
        $this->arca->wsfe
            ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(7, 3))
            ->responder('FECAESolicitar', RespuestasArca::cae(numero: 8));

        $this->post(route('comprobantes.store'), facturaNueva(['emitir' => '1']))
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Factura 00003-00000008 autorizada por ARCA. CAE 76461234567890. Se envió a pagos@ministerio.test.');

        Mail::assertSent(FacturaPorEmail::class, fn (FacturaPorEmail $correo): bool => $correo->hasTo('pagos@ministerio.test')
            && $correo->hasCc($this->usuario->email)
            && str_starts_with($correo->texto, 'Hola, Ministerio de Prueba:'));
        expect(Comprobante::query()->sole()->envios)->toHaveCount(1);
    });

    it('la manda al emitir un borrador desde el detalle', function () {
        $this->arca->wsfe
            ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(0, 3))
            ->responder('FECAESolicitar', RespuestasArca::cae());
        $this->post(route('comprobantes.store'), facturaNueva())->assertSessionHasNoErrors();
        $borrador = Comprobante::query()->sole();
        Mail::assertNothingSent();

        $this->post(route('comprobantes.emitir', $borrador));

        Mail::assertSentCount(1);
    });

    it('la manda cuando el reconciliador resuelve un pendiente', function () {
        $pendiente = Comprobante::factory()->paraPunto($this->punto)->create([
            'cliente_id' => $this->cliente->id,
            'estado' => EstadoComprobante::Pendiente,
            'numero' => 8,
            'enviado_en' => now()->subMinutes(5),
            'enviar_al_autorizar' => true,
        ]);
        $this->arca->wsfe->responder('FECompConsultar', RespuestasArca::comprobante(['CbteDesde' => 8, 'PtoVta' => 3, 'ImpTotal' => $pendiente->importe_total / 100]));

        $this->artisan('arca:reconciliar-pendientes')->assertSuccessful();

        expect($pendiente->fresh()?->estado)->toBe(EstadoComprobante::Autorizado);
        Mail::assertSent(FacturaPorEmail::class, fn (FacturaPorEmail $correo): bool => $correo->hasTo('pagos@ministerio.test'));
    });

    it('no manda nada si ARCA la rechaza, sin la casilla o si el cliente no tiene email', function () {
        $this->arca->wsfe
            ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(0, 3))
            ->responder('FECAESolicitar', RespuestasArca::cae(resultado: 'R', cae: null, observaciones: [[10015, 'Rechazada']]));
        $this->post(route('comprobantes.store'), facturaNueva(['emitir' => '1']));

        $this->arca->wsfe
            ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(0, 3))
            ->responder('FECAESolicitar', RespuestasArca::cae());
        $this->post(route('comprobantes.store'), facturaNueva(['emitir' => '1', 'enviar_al_autorizar' => null]));

        $this->cliente->forceFill(['email' => null])->save();
        $this->arca->wsfe
            ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(1, 3))
            ->responder('FECAESolicitar', RespuestasArca::cae(numero: 2));
        $this->post(route('comprobantes.store'), facturaNueva(['emitir' => '1']));

        expect(Comprobante::query()->where('estado', EstadoComprobante::Autorizado)->count())->toBe(2);
        Mail::assertNothingSent();
    });

    it('en homologación la casilla no se guarda', function () {
        $this->emisor->forceFill(['ambiente' => Ambiente::Homologacion])->save();
        $this->punto->forceFill(['ambiente' => Ambiente::Homologacion])->save();

        $this->post(route('comprobantes.store'), facturaNueva())->assertSessionHasNoErrors();

        expect(Comprobante::query()->sole()->enviar_al_autorizar)->toBeFalse();
    });

    it('si el correo falla, la factura queda autorizada y el aviso lo dice', function () {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP caído'));
        $this->arca->wsfe
            ->responder('FECompUltimoAutorizado', RespuestasArca::ultimoAutorizado(0, 3))
            ->responder('FECAESolicitar', RespuestasArca::cae());

        $this->post(route('comprobantes.store'), facturaNueva(['emitir' => '1']))
            ->assertInertiaFlash('toast.type', 'warning');

        expect(Comprobante::query()->sole())
            ->estado->toBe(EstadoComprobante::Autorizado)
            ->and(EnvioDeComprobante::query()->sole()->enviado)->toBeFalse();
    });
});
