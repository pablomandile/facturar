<?php

declare(strict_types=1);

namespace Tests\Support\Arca;

use App\Contracts\Arca\FabricaDeTransportes;
use App\Contracts\Arca\TransporteSoap;
use App\Enums\Arca\Ambiente;
use App\Enums\Arca\ServicioArca;

/**
 * La fábrica de transportes de los tests: un TransporteFalso por servicio,
 * compartido entre ambientes. Se registra con `arcaFalso()` (tests/Pest.php)
 * antes de resolver cualquier servicio que hable con ARCA.
 */
final class ArcaFalso implements FabricaDeTransportes
{
    public readonly TransporteFalso $wsaa;

    public readonly TransporteFalso $wsfe;

    /** @var list<Ambiente> */
    public array $ambientesPedidos = [];

    public function __construct()
    {
        $this->wsaa = new TransporteFalso;
        $this->wsfe = new TransporteFalso;
    }

    public function para(ServicioArca $servicio, Ambiente $ambiente): TransporteSoap
    {
        $this->ambientesPedidos[] = $ambiente;

        return match ($servicio) {
            ServicioArca::Wsaa => $this->wsaa,
            ServicioArca::Wsfe => $this->wsfe,
        };
    }

    /** Prepara un ticket válido por 12 horas en la próxima llamada a WSAA. */
    public function conTicket(string $token = 'TOKEN-DE-PRUEBA', string $sign = 'SIGN-DE-PRUEBA'): self
    {
        $this->wsaa->responder('loginCms', RespuestasArca::ticket($token, $sign));

        return $this;
    }
}
