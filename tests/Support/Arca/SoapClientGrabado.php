<?php

declare(strict_types=1);

namespace Tests\Support\Arca;

use SoapClient;
use SoapFault;

/**
 * Un SoapClient de verdad (con el WSDL real) que en vez de salir a la red
 * contesta con un XML grabado de ARCA, o simula una falla de transporte.
 * Sirve para probar TransporteSoapReal y la lectura de respuestas reales
 * sin conexión.
 */
final class SoapClientGrabado extends SoapClient
{
    public ?string $pedidoEnviado = null;

    /**
     * @param  array<string, mixed>  $opciones
     */
    public function __construct(string $wsdl, array $opciones, private readonly ?string $respuesta, private readonly ?string $fallaHttp = null)
    {
        parent::__construct($wsdl, $opciones);
    }

    public function __doRequest(string $request, string $location, string $action, int $version, bool $oneWay = false, ?string $uriParserClass = null): ?string
    {
        $this->pedidoEnviado = $request;

        if ($this->fallaHttp !== null) {
            throw new SoapFault('HTTP', $this->fallaHttp);
        }

        return $this->respuesta;
    }

    public static function wsfe(?string $fixture, ?string $fallaHttp = null): self
    {
        return new self(
            (string) config('arca.wsdl.wsfe'),
            ['soap_version' => SOAP_1_2, 'trace' => true, 'exceptions' => true, 'cache_wsdl' => WSDL_CACHE_NONE, 'features' => SOAP_SINGLE_ELEMENT_ARRAYS],
            $fixture === null ? null : (string) file_get_contents(base_path("tests/Fixtures/arca/{$fixture}")),
            $fallaHttp,
        );
    }

    public static function wsaa(string $fixture): self
    {
        return new self(
            (string) config('arca.wsdl.wsaa'),
            ['soap_version' => SOAP_1_1, 'trace' => true, 'exceptions' => true, 'cache_wsdl' => WSDL_CACHE_NONE],
            (string) file_get_contents(base_path("tests/Fixtures/arca/{$fixture}")),
        );
    }
}
