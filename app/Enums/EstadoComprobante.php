<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * En qué anda un comprobante.
 *
 * Borrador → (emitir) → Pendiente → Autorizado | Rechazado | Error.
 * Pendiente es el único estado "en el aire": se mandó a ARCA y no sabemos
 * todavía qué contestó. Mientras haya uno, su punto de venta no emite.
 */
enum EstadoComprobante: string
{
    /** Se puede editar y emitir. */
    case Borrador = 'borrador';

    /** Se mandó a ARCA y falta saber el resultado. Tiene número reservado. */
    case Pendiente = 'pendiente';

    /** ARCA dio el CAE. Es una factura válida y no se toca más. */
    case Autorizado = 'autorizado';

    /** ARCA la rechazó con observaciones. Se corrige y se vuelve a emitir. */
    case Rechazado = 'rechazado';

    /** No se pudo completar (conexión, error de ARCA). Se puede reintentar. */
    case Error = 'error';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Pendiente => 'Pendiente',
            self::Autorizado => 'Autorizada',
            self::Rechazado => 'Rechazada',
            self::Error => 'Con error',
        };
    }

    public function esEmitible(): bool
    {
        return in_array($this, [self::Borrador, self::Rechazado, self::Error], true);
    }

    /** Lo mismo que emitible: lo que no salió de la app o volvió sin CAE. */
    public function esEditable(): bool
    {
        return $this->esEmitible();
    }
}
