<?php

use App\Enums\Arca\Ambiente;
use App\Enums\Arca\ResultadoLlamada;
use App\Enums\Arca\ServicioArca;
use App\Exceptions\Arca\ErrorDeWsaa;
use App\Exceptions\Arca\FaultDeArca;
use App\Exceptions\Arca\SinCertificadoVigente;
use App\Exceptions\Arca\TicketNoDisponible;
use App\Models\Certificado;
use App\Models\Emisor;
use App\Models\LlamadaArca;
use App\Models\TicketDeAcceso;
use App\Services\Arca\ProveedorDeTickets;
use Illuminate\Support\Facades\DB;

function emisorConCertificado(): Emisor
{
    $emisor = Emisor::factory()->create();
    Certificado::factory()->vigente()->for($emisor)->create();

    return $emisor;
}

function ticketGuardado(Emisor $emisor, array $cambios = []): TicketDeAcceso
{
    return TicketDeAcceso::query()->create([
        'emisor_id' => $emisor->id,
        'certificado_id' => $emisor->certificadoVigente()?->id,
        'ambiente' => $emisor->ambiente,
        'servicio' => ServicioArca::Wsfe,
        'token' => 'TOKEN-GUARDADO',
        'sign' => 'SIGN-GUARDADO',
        'generado_en' => now()->subHour(),
        'expira_en' => now()->addHours(11),
        ...$cambios,
    ]);
}

it('pide un ticket a WSAA y lo guarda cifrado', function () {
    $arca = arcaFalso()->conTicket('TOKEN-NUEVO', 'SIGN-NUEVO');
    $emisor = emisorConCertificado();

    $ticket = app(ProveedorDeTickets::class)->obtener($emisor);

    // Releído de la base: así se ve si la hora de ARCA (-03:00) se guardó corrida.
    $guardado = TicketDeAcceso::query()->sole();

    expect($ticket->token)->toBe('TOKEN-NUEVO')
        ->and($guardado->expira_en?->isAfter(now()->addHours(11)->addMinutes(59)))->toBeTrue()
        ->and(DB::table('tickets_de_acceso')->value('token'))->not->toContain('TOKEN-NUEVO')
        ->and($arca->wsaa->llamadas('loginCms'))->toHaveCount(1);

    // Lo que viaja es el CMS en base64.
    expect(base64_decode($arca->wsaa->parametrosDe('loginCms')['in0'], true))->not->toBeFalse();
});

it('reusa el ticket vigente sin llamar a WSAA', function () {
    $arca = arcaFalso();
    $emisor = emisorConCertificado();
    ticketGuardado($emisor);

    expect(app(ProveedorDeTickets::class)->obtener($emisor)->token)->toBe('TOKEN-GUARDADO');
    $arca->wsaa->assertNoLlamo('loginCms');
});

it('renueva el ticket que vence dentro del margen', function () {
    arcaFalso()->conTicket('TOKEN-NUEVO');
    $emisor = emisorConCertificado();
    ticketGuardado($emisor, ['expira_en' => now()->addMinutes(5)]);

    expect(app(ProveedorDeTickets::class)->obtener($emisor)->token)->toBe('TOKEN-NUEVO')
        ->and(TicketDeAcceso::query()->count())->toBe(1);
});

it('pide uno nuevo si el ticket es de un certificado anterior', function () {
    arcaFalso()->conTicket('TOKEN-DEL-NUEVO-CERTIFICADO');
    $emisor = Emisor::factory()->create();
    $viejo = Certificado::factory()->vigente()->for($emisor)->create();
    ticketGuardado($emisor, ['certificado_id' => $viejo->id]);
    $viejo->forceFill(['estado' => 'reemplazado'])->save();
    Certificado::factory()->vigente()->for($emisor)->create();

    expect(app(ProveedorDeTickets::class)->obtener($emisor)->token)->toBe('TOKEN-DEL-NUEVO-CERTIFICADO');
});

it('sin certificado vigente avisa qué falta', function () {
    arcaFalso();

    app(ProveedorDeTickets::class)->obtener(Emisor::factory()->create());
})->throws(SinCertificadoVigente::class, 'Cargalo en Datos fiscales');

it('si ARCA ya tiene un ticket que perdimos, espera en vez de insistir', function () {
    $arca = arcaFalso();
    $arca->wsaa->fallar('loginCms', new FaultDeArca('ns1:coe.alreadyAuthenticated', 'El CEE ya posee un TA valido para el acceso al WSN solicitado'));
    $emisor = emisorConCertificado();

    expect(fn () => app(ProveedorDeTickets::class)->obtener($emisor))->toThrow(TicketNoDisponible::class);
    expect(TicketDeAcceso::query()->sole()->bloqueado_hasta?->isFuture())->toBeTrue();

    // El segundo intento no vuelve a molestar a ARCA.
    expect(fn () => app(ProveedorDeTickets::class)->obtener($emisor))->toThrow(TicketNoDisponible::class);
    expect($arca->wsaa->llamadas('loginCms'))->toHaveCount(1);
});

it('pasado el bloqueo vuelve a pedir', function () {
    arcaFalso()->conTicket('TOKEN-NUEVO');
    $emisor = emisorConCertificado();
    ticketGuardado($emisor, ['token' => null, 'sign' => null, 'expira_en' => null, 'bloqueado_hasta' => now()->subMinute()]);

    expect(app(ProveedorDeTickets::class)->obtener($emisor)->token)->toBe('TOKEN-NUEVO')
        ->and(TicketDeAcceso::query()->sole()->bloqueado_hasta)->toBeNull();
});

it('traduce los errores de WSAA y deja la llamada registrada sin el ticket', function () {
    $arca = arcaFalso();
    $arca->wsaa->fallar('loginCms', new FaultDeArca('ns1:coe.notAuthorized', 'Computador no autorizado a acceder al servicio'));
    $emisor = emisorConCertificado();

    expect(fn () => app(ProveedorDeTickets::class)->obtener($emisor))
        ->toThrow(ErrorDeWsaa::class, 'no está autorizado para Facturación Electrónica');

    expect(LlamadaArca::query()->sole())
        ->metodo->toBe('loginCms')
        ->resultado->toBe(ResultadoLlamada::Fault)
        ->codigo->toBe('coe.notAuthorized');
});

it('el registro de una llamada exitosa no guarda el token', function () {
    arcaFalso()->conTicket('TOKEN-SECRETO', 'SIGN-SECRETO');
    $emisor = emisorConCertificado();

    app(ProveedorDeTickets::class)->obtener($emisor);

    $llamada = LlamadaArca::query()->sole();
    expect($llamada->resultado)->toBe(ResultadoLlamada::Ok)
        ->and($llamada->respuesta)->not->toContain('TOKEN-SECRETO')
        ->and($llamada->respuesta)->not->toContain('SIGN-SECRETO');
});

it('el ticket es del ambiente: en producción pide otro', function () {
    $arca = arcaFalso()->conTicket('TOKEN-PRODUCCION');
    $emisor = Emisor::factory()->enProduccion()->create();
    Certificado::factory()->vigente()->for($emisor)->create(['ambiente' => Ambiente::Produccion]);

    app(ProveedorDeTickets::class)->obtener($emisor);

    expect($arca->ambientesPedidos)->toBe([Ambiente::Produccion])
        ->and(TicketDeAcceso::query()->sole()->ambiente)->toBe(Ambiente::Produccion);
});
