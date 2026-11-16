<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Plata en centavos enteros. Nunca float: un 0,1 + 0,2 de diferencia en un
 * total es un rechazo de ARCA (ImpTotal ≠ ImpNeto + ImpTrib).
 */
final class Dinero
{
    /**
     * "1500", "1500.5", "1500,50" o "1.500,50" → centavos. Más de dos
     * decimales no: no hay medio centavo.
     */
    public static function aCentavos(string $valor): int
    {
        $valor = str_replace([' ', '$'], '', trim($valor));

        if (str_contains($valor, ',')) {
            // Formato argentino: el punto separa miles y la coma, decimales.
            $valor = str_replace(['.', ','], ['', '.'], $valor);
        }

        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $valor, $partes) !== 1) {
            throw new InvalidArgumentException("Importe inválido: {$valor}");
        }

        return (int) $partes[1] * 100 + (int) str_pad($partes[2] ?? '0', 2, '0');
    }

    public static function esValido(string $valor): bool
    {
        try {
            self::aCentavos($valor);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** Centavos → "1.500,50". */
    public static function formatear(int $centavos): string
    {
        return number_format($centavos / 100, 2, ',', '.');
    }

    /** Centavos → "1500.50", para precargar un input numérico. */
    public static function aDecimal(int $centavos): string
    {
        return intdiv($centavos, 100).'.'.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }
}
