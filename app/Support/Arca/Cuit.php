<?php

declare(strict_types=1);

namespace App\Support\Arca;

/**
 * CUIT y CUIL: los dos usan el mismo formato de 11 dígitos y el mismo dígito
 * verificador (módulo 11).
 */
final class Cuit
{
    private const PESOS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    /** Saca guiones, puntos y espacios: "20-12345678-9" → "20123456789". */
    public static function normalizar(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }

    public static function esValido(string $valor): bool
    {
        $cuit = self::normalizar($valor);

        if (strlen($cuit) !== 11) {
            return false;
        }

        return self::verificador(substr($cuit, 0, 10)) === (int) $cuit[10];
    }

    /**
     * Completa los diez primeros dígitos con el verificador. Null si con
     * esos dígitos no hay CUIT posible (el verificador daría 10).
     */
    public static function conVerificador(string $diezDigitos): ?string
    {
        $verificador = self::verificador($diezDigitos);

        return $verificador === null ? null : $diezDigitos.$verificador;
    }

    private static function verificador(string $diezDigitos): ?int
    {
        $suma = 0;
        foreach (self::PESOS as $posicion => $peso) {
            $suma += (int) $diezDigitos[$posicion] * $peso;
        }

        return match ($resto = 11 - ($suma % 11)) {
            11 => 0,
            10 => null,
            default => $resto,
        };
    }

    /** "20123456789" → "20-12345678-9". */
    public static function formatear(string $valor): string
    {
        $cuit = self::normalizar($valor);

        if (strlen($cuit) !== 11) {
            return $valor;
        }

        return substr($cuit, 0, 2).'-'.substr($cuit, 2, 8).'-'.substr($cuit, 10, 1);
    }
}
