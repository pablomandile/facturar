<?php

declare(strict_types=1);

namespace App\Services\Arca;

use App\Contracts\Arca\FabricaDeTransportes;
use App\Enums\Arca\ServicioArca;
use App\Exceptions\Arca\ErrorDeWsaa;
use App\Exceptions\Arca\FaultDeArca;
use App\Models\Certificado;
use App\Support\Arca\TicketObtenido;
use Illuminate\Support\Carbon;
use SimpleXMLElement;
use UnexpectedValueException;

/**
 * Pide el ticket de acceso a WSAA. No guarda nada: de eso se ocupa
 * `ProveedorDeTickets`, que es quien decide cuándo hace falta uno nuevo.
 */
class ClienteWsaa
{
    public function __construct(
        private readonly FabricaDeTransportes $fabrica,
        private readonly ArmadorDeTra $armador,
        private readonly FirmanteDeTra $firmante,
        private readonly RegistradorDeLlamadas $registrador,
    ) {}

    /** @throws ErrorDeWsaa */
    public function login(Certificado $certificado, ServicioArca $servicio): TicketObtenido
    {
        $tra = $this->armador->armar($servicio);
        $cms = $this->firmante->firmar($tra['xml'], (string) $certificado->certificado, $certificado->clave_privada);
        $transporte = $this->fabrica->para(ServicioArca::Wsaa, $certificado->ambiente);

        try {
            $respuesta = $this->registrador->registrar(
                $certificado->emisor,
                ServicioArca::Wsaa,
                $certificado->ambiente,
                'loginCms',
                $transporte,
                fn () => $transporte->llamar('loginCms', ['in0' => $cms]),
            );
        } catch (FaultDeArca $fault) {
            throw ErrorDeWsaa::desdeFault($fault);
        }

        return $this->leer((string) ($respuesta->loginCmsReturn ?? ''), $tra['uniqueId']);
    }

    private function leer(string $xml, string $uniqueId): TicketObtenido
    {
        $ticket = @simplexml_load_string($xml);

        if (! $ticket instanceof SimpleXMLElement || ! isset($ticket->credentials->token, $ticket->header->expirationTime)) {
            throw new UnexpectedValueException('WSAA devolvió un ticket con formato inesperado.');
        }

        // A UTC al leerlas: ARCA las manda con -03:00 y Eloquent guarda la
        // hora tal cual, sin convertir. Sin esto el vencimiento quedaba tres
        // horas antes y el ticket se renovaba antes de tiempo.
        return new TicketObtenido(
            token: (string) $ticket->credentials->token,
            sign: (string) $ticket->credentials->sign,
            uniqueId: $uniqueId,
            generadoEn: Carbon::parse((string) $ticket->header->generationTime)->utc(),
            expiraEn: Carbon::parse((string) $ticket->header->expirationTime)->utc(),
        );
    }
}
