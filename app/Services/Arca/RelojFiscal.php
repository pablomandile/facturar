<?php

declare(strict_types=1);

namespace App\Services\Arca;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * La hora y el día fiscales: los de Buenos Aires.
 *
 * La app persiste en UTC, pero ARCA razona en hora argentina. A las 21 h de
 * Buenos Aires ya es el día siguiente en UTC, y un `CbteFch` de mañana o un
 * TRA con la hora corrida los rechaza ARCA sin que nada lo anticipe. Todo lo
 * que va al wire con fecha u hora sale de acá.
 *
 * Dos clases de valores, que no se mezclan:
 *
 * - **Instantes** (`ahora()`): llevan zona horaria. Son los del TRA.
 * - **Fechas de calendario** (`hoy()`, `desdeYmd()`): un día, sin hora. Se
 *   representan a medianoche UTC, igual que las columnas `date` que lee
 *   Eloquent, para que comparar una con otra no se corra un día.
 */
final class RelojFiscal
{
    public const ZONA = 'America/Argentina/Buenos_Aires';

    public function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now(self::ZONA);
    }

    /** El día de hoy en Argentina, como fecha de calendario. */
    public function hoy(): CarbonImmutable
    {
        return $this->fecha($this->ahora()->toDateString());
    }

    /**
     * Una fecha de calendario en el formato de ARCA (`20261116`). Recibe
     * fechas, no instantes: no convierte la zona, solo formatea.
     */
    public function ymd(CarbonInterface $fecha): string
    {
        return $fecha->format('Ymd');
    }

    /** `20261116` → fecha de calendario. */
    public function desdeYmd(string $valor): CarbonImmutable
    {
        if (preg_match('/^\d{8}$/', $valor) !== 1) {
            throw new InvalidArgumentException("ARCA devolvió una fecha con formato inesperado: {$valor}");
        }

        return $this->fecha(substr($valor, 0, 4).'-'.substr($valor, 4, 2).'-'.substr($valor, 6, 2));
    }

    private function fecha(string $isoFecha): CarbonImmutable
    {
        $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $isoFecha, 'UTC');

        if ($fecha === null) {
            throw new InvalidArgumentException("Fecha inválida: {$isoFecha}");
        }

        return $fecha;
    }
}
