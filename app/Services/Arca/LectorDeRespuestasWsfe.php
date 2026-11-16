<?php

declare(strict_types=1);

namespace App\Services\Arca;

use App\Support\Arca\ObservacionDeArca;
use stdClass;

/**
 * Lee las respuestas de WSFE tal como las arma SoapClient.
 *
 * La trampa: un nodo que puede repetirse (Err, Obs, Evt, PtoVenta) llega
 * como array si vino más de uno y como objeto suelto si vino uno solo.
 * SOAP_SINGLE_ELEMENT_ARRAYS lo evita en el cliente real, pero todo lo que
 * lee listas pasa igual por `lista()`: un fixture, un cambio de WSDL o un
 * nodo mal declarado no tienen que romper la lectura.
 */
final class LectorDeRespuestasWsfe
{
    /** @return list<stdClass> */
    public static function lista(mixed $nodo): array
    {
        return match (true) {
            $nodo === null => [],
            is_array($nodo) => array_values(array_filter($nodo, fn (mixed $item): bool => $item instanceof stdClass)),
            $nodo instanceof stdClass => [$nodo],
            default => [],
        };
    }

    /** @return list<ObservacionDeArca> */
    public static function errores(object $resultado): array
    {
        return self::observacionesDe($resultado->Errors->Err ?? null);
    }

    /** @return list<ObservacionDeArca> */
    public static function eventos(object $resultado): array
    {
        return self::observacionesDe($resultado->Events->Evt ?? null);
    }

    /**
     * Las observaciones de un comprobante (FECAEDetResponse o ResultGet).
     *
     * @return list<ObservacionDeArca>
     */
    public static function observaciones(object $detalle): array
    {
        return self::observacionesDe($detalle->Observaciones->Obs ?? null);
    }

    /** @return list<ObservacionDeArca> */
    private static function observacionesDe(mixed $nodo): array
    {
        return array_map(
            fn (stdClass $item): ObservacionDeArca => new ObservacionDeArca((int) ($item->Code ?? 0), trim((string) ($item->Msg ?? ''))),
            self::lista($nodo),
        );
    }
}
