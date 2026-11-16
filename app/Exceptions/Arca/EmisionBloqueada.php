<?php

declare(strict_types=1);

namespace App\Exceptions\Arca;

/**
 * El punto de venta no puede numerar ahora: otra emisión está en curso, hay
 * una factura de resultado incierto, o la numeración de ARCA no cierra con
 * la nuestra.
 */
class EmisionBloqueada extends ExcepcionDeArca
{
    public static function enCurso(): self
    {
        return new self('Hay otra factura emitiéndose en este punto de venta. Probá de nuevo en unos segundos.');
    }

    public static function hayPendientes(): self
    {
        return new self('Hay una factura de este punto de venta esperando confirmación de ARCA. Se resuelve sola en unos minutos; después vas a poder emitir.');
    }

    public static function numeracionDesincronizada(int $enArca, int $enLaApp): self
    {
        return new self("ARCA dice que la última factura de este punto de venta es la {$enArca}, pero acá ya hay autorizada la {$enLaApp}. Revisá que el punto de venta y el ambiente sean los correctos.");
    }
}
