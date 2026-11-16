<?php

declare(strict_types=1);

namespace App\Enums\Arca;

use App\Support\Arca\Cuit;

/**
 * Cómo se identifica al receptor de la factura, con el código de ARCA
 * (`DocTipo`). Consumidor final no es un documento: es "no identificado",
 * con `DocNro = 0`, y por eso no se guarda como cliente.
 */
enum TipoDocumento: int
{
    case Cuit = 80;
    case Cuil = 86;
    case Dni = 96;
    case ConsumidorFinal = 99;

    public function etiqueta(): string
    {
        return match ($this) {
            self::Cuit => 'CUIT',
            self::Cuil => 'CUIL',
            self::Dni => 'DNI',
            self::ConsumidorFinal => 'Sin identificar',
        };
    }

    /** ¿El número es válido para este tipo? CUIT y CUIL con dígito verificador. */
    public function numeroValido(string $numero): bool
    {
        return match ($this) {
            self::Cuit, self::Cuil => Cuit::esValido($numero),
            self::Dni => preg_match('/^\d{7,8}$/', $numero) === 1,
            self::ConsumidorFinal => $numero === '0',
        };
    }

    /**
     * Los que se pueden cargar en un cliente.
     *
     * @return list<self>
     */
    public static function deClientes(): array
    {
        return [self::Cuit, self::Cuil, self::Dni];
    }

    public function formatear(string $numero): string
    {
        return match ($this) {
            self::Cuit, self::Cuil => Cuit::formatear($numero),
            self::Dni => number_format((int) $numero, 0, ',', '.'),
            self::ConsumidorFinal => '',
        };
    }
}
