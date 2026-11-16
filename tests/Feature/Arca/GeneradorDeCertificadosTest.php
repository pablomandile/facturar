<?php

use App\Enums\Arca\Ambiente;
use App\Enums\Arca\EstadoCertificado;
use App\Exceptions\Arca\CertificadoInvalido;
use App\Models\Certificado;
use App\Models\Emisor;
use App\Services\Arca\GeneradorDeCertificados;
use Database\Factories\CertificadoFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function generador(): GeneradorDeCertificados
{
    return app(GeneradorDeCertificados::class);
}

it('genera clave y CSR con el sujeto que pide ARCA', function () {
    $emisor = Emisor::factory()->create(['razon_social' => 'Martín Peña']);

    $certificado = generador()->generar($emisor, Ambiente::Homologacion, 'facturar');

    expect(openssl_csr_get_subject($certificado->csr, false))->toBe([
        'countryName' => 'AR',
        // Sin acentos: las pantallas de ARCA los muestran rotos.
        'organizationName' => 'Martin Pena',
        'commonName' => 'facturar',
        'serialNumber' => "CUIT {$emisor->cuit}",
    ])
        ->and($certificado->estado)->toBe(EstadoCertificado::EsperandoCertificado)
        ->and($certificado->ambiente)->toBe(Ambiente::Homologacion);
});

it('guarda la clave privada cifrada y no la expone al serializar', function () {
    $certificado = generador()->generar(Emisor::factory()->create(), Ambiente::Homologacion, 'facturar');

    $enLaBase = DB::table('certificados')->where('id', $certificado->id)->value('clave_privada');

    expect($enLaBase)->not->toContain('PRIVATE KEY')
        ->and($certificado->fresh()?->clave_privada)->toContain('-----BEGIN PRIVATE KEY-----')
        ->and($certificado->toArray())->not->toHaveKey('clave_privada');
});

it('al regenerar descarta el pedido sin usar pero mantiene el vigente', function () {
    $emisor = Emisor::factory()->create();
    $vigente = Certificado::factory()->vigente()->for($emisor)->create();
    $viejo = generador()->generar($emisor, Ambiente::Homologacion, 'viejo');

    $nuevo = generador()->generar($emisor, Ambiente::Homologacion, 'nuevo');

    expect(Certificado::find($viejo->id))->toBeNull()
        ->and($vigente->fresh()?->estado)->toBe(EstadoCertificado::Vigente)
        ->and($emisor->certificadoPendiente()?->id)->toBe($nuevo->id);
});

it('incorpora el certificado de ARCA y reemplaza el anterior del ambiente', function () {
    $emisor = Emisor::factory()->create();
    $anterior = Certificado::factory()->vigente()->for($emisor)->create();
    $deProduccion = Certificado::factory()->vigente()->for($emisor)->create(['ambiente' => Ambiente::Produccion]);
    $pendiente = Certificado::factory()->for($emisor)->create();

    generador()->incorporar($pendiente, CertificadoFactory::certificadoPara($emisor));

    expect($pendiente->fresh()?->estado)->toBe(EstadoCertificado::Vigente)
        ->and($pendiente->fresh()?->valido_hasta?->isFuture())->toBeTrue()
        ->and($anterior->fresh()?->estado)->toBe(EstadoCertificado::Reemplazado)
        ->and($deProduccion->fresh()?->estado)->toBe(EstadoCertificado::Vigente);
});

it('acepta el certificado en formato binario (DER)', function () {
    $emisor = Emisor::factory()->create();
    $pendiente = Certificado::factory()->for($emisor)->create();
    $pem = CertificadoFactory::certificadoPara($emisor);
    $der = base64_decode(preg_replace('/-----[A-Z ]+-----|\s/', '', $pem) ?? '');

    generador()->incorporar($pendiente, $der);

    expect($pendiente->fresh()?->estado)->toBe(EstadoCertificado::Vigente);
});

it('rechaza lo que no sirve, con un mensaje que dice qué hacer', function (Closure $armar, string $mensaje) {
    $emisor = Emisor::factory()->create();
    $pendiente = Certificado::factory()->for($emisor)->create();

    expect(fn () => generador()->incorporar($pendiente, $armar($emisor)))
        ->toThrow(CertificadoInvalido::class, $mensaje);

    expect($pendiente->fresh()?->estado)->toBe(EstadoCertificado::EsperandoCertificado);
})->with([
    'no es un certificado' => [fn () => 'cualquier cosa', 'no es un certificado'],
    'otra clave' => [
        fn (Emisor $e) => CertificadoFactory::certificadoPara($e, clave: openssl_pkey_new([...GeneradorDeCertificados::opcionesOpenSsl(), 'private_key_bits' => 2048]) ?: null),
        'no corresponde al pedido',
    ],
    'otro CUIT' => [fn (Emisor $e) => CertificadoFactory::certificadoPara($e, ['serialNumber' => 'CUIT 20111111112']), 'es de otro CUIT'],
    'del otro ambiente' => [fn (Emisor $e) => CertificadoFactory::certificadoPara($e, ['commonName' => 'Computadores']), 'es de Producción'],
    // OpenSSL no firma con días negativos: se firma por un día y se adelanta el reloj.
    'vencido' => [function (Emisor $e) {
        $pem = CertificadoFactory::certificadoPara($e, dias: 1);
        Carbon::setTestNow(now()->addDays(2));

        return $pem;
    }, 'ya venció'],
]);

it('marca vencidos los vigentes que pasaron su fecha', function () {
    $vencido = Certificado::factory()->vigente()->create(['valido_hasta' => now()->subMinute()]);
    $sano = Certificado::factory()->vigente()->create();

    expect(generador()->marcarVencidos())->toBe(1)
        ->and($vencido->fresh()?->estado)->toBe(EstadoCertificado::Vencido)
        ->and($sano->fresh()?->estado)->toBe(EstadoCertificado::Vigente);
});
