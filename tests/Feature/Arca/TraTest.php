<?php

use App\Enums\Arca\ServicioArca;
use App\Models\Emisor;
use App\Services\Arca\ArmadorDeTra;
use App\Services\Arca\FirmanteDeTra;
use App\Services\Arca\GeneradorDeCertificados;
use App\Services\Arca\RegistradorDeLlamadas;
use Database\Factories\CertificadoFactory;
use Illuminate\Support\Carbon;

it('arma el TRA para wsfe con hora argentina y ventana de diez minutos', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-16 13:00:00', 'UTC'));

    $tra = app(ArmadorDeTra::class)->armar(ServicioArca::Wsfe);
    $xml = simplexml_load_string($tra['xml']);

    expect((string) $xml->service)->toBe('wsfe')
        ->and((string) $xml->header->generationTime)->toBe('2026-11-16T09:50:00-03:00')
        ->and((string) $xml->header->expirationTime)->toBe('2026-11-16T10:10:00-03:00')
        ->and((string) $xml->header->uniqueId)->toBe($tra['uniqueId'])
        ->and($tra['uniqueId'])->toMatch('/^\d+$/');
});

it('firma el TRA en CMS base64 sin encabezados S/MIME y sin dejar archivos', function () {
    $emisor = Emisor::factory()->create();
    $certificado = CertificadoFactory::certificadoPara($emisor);
    $tra = app(ArmadorDeTra::class)->armar(ServicioArca::Wsfe)['xml'];
    $directorio = config('arca.directorio_temporal');
    $antes = is_dir($directorio) ? count(scandir($directorio)) : 2;

    $cms = app(FirmanteDeTra::class)->firmar($tra, $certificado, CertificadoFactory::clavePem());

    expect($cms)->not->toContain('MIME-Version')
        ->and(base64_decode($cms, true))->not->toBeFalse()
        ->and(count(scandir($directorio)))->toBe($antes);

    // El CMS tiene adentro el TRA y la firma verifica con el certificado.
    $smime = "MIME-Version: 1.0\nContent-Type: application/pkcs7-mime; smime-type=signed-data; name=\"smime.p7m\"\nContent-Transfer-Encoding: base64\n\n{$cms}\n";
    $archivos = [tempnam(sys_get_temp_dir(), 'v'), tempnam(sys_get_temp_dir(), 'c'), tempnam(sys_get_temp_dir(), 'f')];
    file_put_contents($archivos[0], $smime);
    $ok = openssl_pkcs7_verify($archivos[0], PKCS7_NOVERIFY, $archivos[2], [], null, $archivos[1]);
    $contenido = file_get_contents($archivos[1]);
    array_map('unlink', $archivos);

    // S/MIME canoniza los saltos de línea a CRLF al firmar en modo texto.
    expect($ok)->toBeTrue()->and(str_replace("\r\n", "\n", (string) $contenido))->toBe($tra);
});

it('tacha token y sign en pedidos de WSFE y en la respuesta escapada de WSAA', function () {
    $pedido = '<ns1:Auth><ns1:Token>PD94bWwg</ns1:Token><ns1:Sign>abc==</ns1:Sign><ns1:Cuit>20111111112</ns1:Cuit></ns1:Auth>';
    $respuesta = '<loginCmsReturn>&lt;credentials&gt;&lt;token&gt;PD94bWwg&lt;/token&gt;&lt;sign&gt;abc==&lt;/sign&gt;</loginCmsReturn>';

    expect(RegistradorDeLlamadas::tachar($pedido))
        ->toBe('<ns1:Auth><ns1:Token>***</ns1:Token><ns1:Sign>***</ns1:Sign><ns1:Cuit>20111111112</ns1:Cuit></ns1:Auth>')
        ->and(RegistradorDeLlamadas::tachar($respuesta))
        ->toBe('<loginCmsReturn>&lt;credentials&gt;&lt;token&gt;***&lt;/token&gt;&lt;sign&gt;***&lt;/sign&gt;</loginCmsReturn>');
});

it('usa siempre el openssl.cnf de la app', function () {
    expect(GeneradorDeCertificados::opcionesOpenSsl()['config'])->toBeReadableFile();
});
