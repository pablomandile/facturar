<?php

declare(strict_types=1);

namespace App\Enums\Arca;

/**
 * Los tipos de comprobante que emite la app, con el código de ARCA
 * (`CbteTipo`). La v1 es solo factura C: la de monotributistas.
 */
enum TipoComprobante: int
{
    case FacturaC = 11;

    public function etiqueta(): string
    {
        return match ($this) {
            self::FacturaC => 'Factura C',
        };
    }

    /** La letra del recuadro y la clase que pide `FEParamGetCondicionIvaReceptor`. */
    public function clase(): string
    {
        return match ($this) {
            self::FacturaC => 'C',
        };
    }
}
