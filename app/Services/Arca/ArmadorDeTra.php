<?php

declare(strict_types=1);

namespace App\Services\Arca;

use App\Enums\Arca\ServicioArca;
use DOMDocument;

/**
 * El TRA (Ticket de Requerimiento de Acceso): el XML que se firma y se
 * manda a WSAA para pedir el ticket.
 *
 * La ventana de validez va de 10 minutos antes a 10 minutos después de
 * ahora: absorbe el desvío del reloj del servidor contra el de ARCA, que en
 * un hosting compartido no se controla. Las horas van con el offset de
 * Argentina (-03:00); sin offset WSAA las lee como UTC y las rechaza.
 */
class ArmadorDeTra
{
    public function __construct(private readonly RelojFiscal $reloj) {}

    /** @return array{xml: string, uniqueId: string} */
    public function armar(ServicioArca $servicio): array
    {
        $ahora = $this->reloj->ahora();
        // Segundos de época: único por segundo, y no se piden dos tickets
        // del mismo servicio en el mismo segundo (lo impide el lock).
        $uniqueId = (string) $ahora->getTimestamp();

        $documento = new DOMDocument('1.0', 'UTF-8');
        $raiz = $documento->appendChild($documento->createElement('loginTicketRequest'));
        $raiz->setAttribute('version', '1.0');

        $encabezado = $raiz->appendChild($documento->createElement('header'));
        $encabezado->appendChild($documento->createElement('uniqueId', $uniqueId));
        $encabezado->appendChild($documento->createElement('generationTime', $ahora->subMinutes(10)->format('c')));
        $encabezado->appendChild($documento->createElement('expirationTime', $ahora->addMinutes(10)->format('c')));

        $raiz->appendChild($documento->createElement('service', $servicio->value));

        return ['xml' => (string) $documento->saveXML(), 'uniqueId' => $uniqueId];
    }
}
