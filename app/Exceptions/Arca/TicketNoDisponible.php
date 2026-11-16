<?php

declare(strict_types=1);

namespace App\Exceptions\Arca;

use App\Services\Arca\RelojFiscal;
use Carbon\CarbonInterface;

/**
 * No hay ticket de acceso y ARCA no da uno nuevo todavía: tiene uno vigente
 * que perdimos, u otro pedido está tardando demasiado.
 */
class TicketNoDisponible extends ExcepcionDeArca
{
    public static function bloqueadoHasta(CarbonInterface $hasta): self
    {
        $hora = $hasta->toImmutable()->setTimezone(RelojFiscal::ZONA)->format('H:i');

        return new self("ARCA todavía no da un permiso nuevo para facturar. Probá de nuevo después de las {$hora}.");
    }

    public static function ocupado(): self
    {
        return new self('Hay otro pedido a ARCA en curso. Probá de nuevo en unos segundos.');
    }
}
