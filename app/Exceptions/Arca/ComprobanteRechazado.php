<?php

declare(strict_types=1);

namespace App\Exceptions\Arca;

use App\Support\Arca\ObservacionDeArca;

/** ARCA contestó con Resultado R: no dio el CAE y dijo por qué. */
class ComprobanteRechazado extends ExcepcionDeArca
{
    /** @param  list<ObservacionDeArca>  $observaciones */
    public function __construct(public readonly array $observaciones)
    {
        parent::__construct($observaciones === []
            ? 'ARCA rechazó la factura sin explicar el motivo.'
            : 'ARCA rechazó la factura: '.implode(' · ', array_map(fn (ObservacionDeArca $o): string => $o->mensaje, $observaciones)));
    }
}
