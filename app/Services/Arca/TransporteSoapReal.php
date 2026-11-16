<?php

declare(strict_types=1);

namespace App\Services\Arca;

use App\Contracts\Arca\TransporteSoap;
use App\Exceptions\Arca\FallaDeConexion;
use App\Exceptions\Arca\FaultDeArca;
use SoapClient;
use SoapFault;

/**
 * SoapClient detrás de la interfaz, traduciendo sus fallas a las nuestras.
 */
class TransporteSoapReal implements TransporteSoap
{
    /**
     * Mensajes de SoapClient que significan que no se llegó a mandar nada.
     * Cualquier otra falla de transporte (típicamente "Error Fetching http
     * headers", el timeout de lectura) puede haber llegado a ARCA.
     */
    private const SIN_CONEXION = '/Could not connect|Could not resolve|getaddrinfo|Connection refused|SSL|failed to load external entity/i';

    public function __construct(
        private readonly SoapClient $cliente,
        private readonly int $timeoutLectura,
    ) {}

    public function llamar(string $metodo, array $parametros = []): object
    {
        // SoapClient no tiene opción para el timeout de lectura: usa el
        // default_socket_timeout de PHP, que se ajusta solo para esta llamada.
        $anterior = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', (string) $this->timeoutLectura);

        try {
            $respuesta = $this->cliente->__soapCall($metodo, $parametros === [] ? [] : [$parametros]);
        } catch (SoapFault $fault) {
            throw $this->traducir($fault);
        } finally {
            ini_set('default_socket_timeout', (string) $anterior);
        }

        return is_object($respuesta) ? $respuesta : (object) ['valor' => $respuesta];
    }

    public function ultimoPedido(): ?string
    {
        return $this->cliente->__getLastRequest();
    }

    public function ultimaRespuesta(): ?string
    {
        return $this->cliente->__getLastResponse();
    }

    private function traducir(SoapFault $fault): FallaDeConexion|FaultDeArca
    {
        $codigo = (string) $fault->faultcode;
        $mensaje = (string) $fault->getMessage();

        // "HTTP" y "WSDL" son fallas del transporte, no respuestas de ARCA.
        if ($codigo === 'HTTP' || $codigo === 'WSDL') {
            return new FallaDeConexion($mensaje, pudoHaberLlegado: preg_match(self::SIN_CONEXION, $mensaje) !== 1);
        }

        return new FaultDeArca($codigo, $mensaje);
    }
}
