<?php

declare(strict_types=1);

namespace App\Exceptions\Arca;

use App\Enums\Arca\Ambiente;

/** El emisor no tiene un certificado vigente para el ambiente en que factura. */
class SinCertificadoVigente extends ExcepcionDeArca
{
    public static function para(Ambiente $ambiente): self
    {
        return new self("Todavía no hay un certificado de ARCA vigente para {$ambiente->etiqueta()}. Cargalo en Datos fiscales.");
    }
}
