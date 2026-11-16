<?php

declare(strict_types=1);

namespace App\Exceptions\Arca;

use App\Enums\Arca\Concepto;
use Carbon\CarbonInterface;

/**
 * No se puede mandar a ARCA: falta algo o algo no cierra, y se sabe antes de
 * llamar. El comprobante no cambia de estado.
 */
class ComprobanteNoEmitible extends ExcepcionDeArca
{
    public static function porEstado(string $estado): self
    {
        return new self("Un comprobante {$estado} no se puede emitir.");
    }

    public static function sinItems(): self
    {
        return new self('La factura no tiene ítems.');
    }

    public static function totalCero(): self
    {
        return new self('El total de la factura tiene que ser mayor que cero.');
    }

    public static function puntoDeVentaInactivo(): self
    {
        return new self('El punto de venta está desactivado o es de otro ambiente.');
    }

    public static function fueraDeVentana(CarbonInterface $fecha, Concepto $concepto): self
    {
        return new self(sprintf(
            'ARCA acepta la fecha de la factura hasta %d días antes o después de hoy, y esta es del %s. Editala y volvé a emitir.',
            $concepto->ventanaDias(),
            $fecha->format('d/m/Y'),
        ));
    }

    public static function anteriorAlUltimo(CarbonInterface $fecha, CarbonInterface $ultima): self
    {
        return new self(sprintf(
            'La fecha (%s) es anterior a la de la última factura de ese punto de venta (%s): ARCA no lo permite.',
            $fecha->format('d/m/Y'),
            $ultima->format('d/m/Y'),
        ));
    }

    public static function consumidorFinalSobreElTope(int $tope): self
    {
        return new self('Desde $ '.number_format($tope / 100, 2, ',', '.').' hay que identificar al consumidor final con su DNI, CUIL o CUIT: elegí o cargá el cliente.');
    }
}
