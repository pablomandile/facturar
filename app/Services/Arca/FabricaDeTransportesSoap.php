<?php

declare(strict_types=1);

namespace App\Services\Arca;

use App\Contracts\Arca\FabricaDeTransportes;
use App\Contracts\Arca\TransporteSoap;
use App\Enums\Arca\Ambiente;
use App\Enums\Arca\ServicioArca;
use App\Exceptions\Arca\EntornoIncompleto;
use SoapClient;

/**
 * Arma el SoapClient de cada servicio con el WSDL local y el endpoint del
 * ambiente. WSAA habla SOAP 1.1; WSFE acepta los dos y se usa 1.2.
 */
class FabricaDeTransportesSoap implements FabricaDeTransportes
{
    public function para(ServicioArca $servicio, Ambiente $ambiente): TransporteSoap
    {
        if (! extension_loaded('soap')) {
            throw new EntornoIncompleto('Falta la extensión soap de PHP: el servidor no puede hablar con ARCA.');
        }

        $cliente = new SoapClient((string) config("arca.wsdl.{$servicio->value}"), [
            'location' => match ($servicio) {
                ServicioArca::Wsaa => $ambiente->urlWsaa(),
                ServicioArca::Wsfe => $ambiente->urlWsfe(),
            },
            'soap_version' => $servicio === ServicioArca::Wsaa ? SOAP_1_1 : SOAP_1_2,
            'trace' => true,
            'exceptions' => true,
            'connection_timeout' => (int) config('arca.timeout_conexion'),
            'cache_wsdl' => app()->environment('production') ? WSDL_CACHE_BOTH : WSDL_CACHE_NONE,
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
        ]);

        return new TransporteSoapReal($cliente, (int) config('arca.timeout_lectura'));
    }
}
