<?php

use App\Exceptions\Arca\FallaDeConexion;
use App\Exceptions\Arca\FaultDeArca;
use App\Services\Arca\LectorDeRespuestasWsfe;
use App\Services\Arca\TransporteSoapReal;
use Tests\Support\Arca\SoapClientGrabado;

/*
 * El SoapClient real, con el WSDL real, leyendo respuestas grabadas de
 * homologación (tests/Fixtures/arca). Prueba lo que los dobles no pueden:
 * que el WSDL y la lectura de SoapClient coinciden con lo que manda ARCA.
 */

it('lee la respuesta real de FEDummy', function () {
    $respuesta = (new TransporteSoapReal(SoapClientGrabado::wsfe('wsfe-FEDummy.xml'), 25))->llamar('FEDummy');

    expect($respuesta->FEDummyResult->AppServer)->toBe('OK')
        ->and($respuesta->FEDummyResult->AuthServer)->toBe('OK');
});

it('lee los Errors reales de WSFE como lista aunque venga uno solo', function () {
    $transporte = new TransporteSoapReal(SoapClientGrabado::wsfe('wsfe-FECompUltimoAutorizado-error-600.xml'), 25);

    $respuesta = $transporte->llamar('FECompUltimoAutorizado', ['Auth' => ['Token' => 't', 'Sign' => 's', 'Cuit' => '20111111112'], 'PtoVta' => 1, 'CbteTipo' => 11]);
    $errores = LectorDeRespuestasWsfe::errores($respuesta->FECompUltimoAutorizadoResult);

    expect($errores)->toHaveCount(1)
        ->and($errores[0]->codigo)->toBe(600)
        ->and($errores[0]->mensaje)->toStartWith('ValidacionDeToken');
});

it('manda los parámetros de WSFE en el namespace de ARCA', function () {
    $cliente = SoapClientGrabado::wsfe('wsfe-FECompUltimoAutorizado-error-600.xml');

    (new TransporteSoapReal($cliente, 25))->llamar('FECompUltimoAutorizado', ['Auth' => ['Token' => 't', 'Sign' => 's', 'Cuit' => '20111111112'], 'PtoVta' => 3, 'CbteTipo' => 11]);

    expect($cliente->pedidoEnviado)
        ->toContain('http://ar.gov.afip.dif.FEV1/')
        ->toContain('<ns1:PtoVta>3</ns1:PtoVta>')
        ->toContain('<ns1:Cuit>20111111112</ns1:Cuit>');
});

it('convierte el fault real de WSAA en FaultDeArca sin el prefijo de namespace', function () {
    $transporte = new TransporteSoapReal(SoapClientGrabado::wsaa('wsaa-loginCms-fault.xml'), 25);

    expect(fn () => $transporte->llamar('loginCms', ['in0' => 'x']))
        ->toThrow(fn (FaultDeArca $e) => expect($e->codigo)->toBe('cms.bad')->and($e->getMessage())->toBe('El CMS no es valido'));
});

it('distingue si el pedido pudo haber llegado a ARCA', function (string $falla, bool $pudoHaberLlegado) {
    $transporte = new TransporteSoapReal(SoapClientGrabado::wsfe(null, $falla), 25);

    expect(fn () => $transporte->llamar('FEDummy'))
        ->toThrow(fn (FallaDeConexion $e) => expect($e->pudoHaberLlegado)->toBe($pudoHaberLlegado));
})->with([
    'no pudo conectar' => ['Could not connect to host', false],
    'no resolvió el nombre' => ['php_network_getaddresses: getaddrinfo failed', false],
    'se cortó esperando la respuesta' => ['Error Fetching http headers', true],
]);

it('restaura el timeout de sockets después de la llamada', function () {
    ini_set('default_socket_timeout', '60');

    (new TransporteSoapReal(SoapClientGrabado::wsfe('wsfe-FEDummy.xml'), 7))->llamar('FEDummy');

    expect(ini_get('default_socket_timeout'))->toBe('60');
});
