<?php

declare(strict_types=1);

namespace App\Enums\Arca;

/**
 * En qué anda el certificado de un emisor para un ambiente.
 *
 * El ciclo es: la app genera la clave y el pedido (CSR) y queda esperando
 * que la persona lo suba a ARCA y vuelva con el certificado firmado. Recién
 * ahí sirve para facturar. Al renovar, el anterior sigue vigente hasta que
 * llega el nuevo: no hay un rato sin poder emitir.
 */
enum EstadoCertificado: string
{
    /** Hay clave y CSR; falta el certificado que devuelve ARCA. */
    case EsperandoCertificado = 'esperando_certificado';

    /** Firmado por ARCA y dentro de su vigencia: se usa para facturar. */
    case Vigente = 'vigente';

    /** Pasó su fecha de vencimiento. */
    case Vencido = 'vencido';

    /** Lo reemplazó uno más nuevo del mismo ambiente. */
    case Reemplazado = 'reemplazado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::EsperandoCertificado => 'Esperando el certificado de ARCA',
            self::Vigente => 'Vigente',
            self::Vencido => 'Vencido',
            self::Reemplazado => 'Reemplazado',
        };
    }
}
