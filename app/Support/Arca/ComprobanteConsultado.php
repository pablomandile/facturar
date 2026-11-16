<?php

declare(strict_types=1);

namespace App\Support\Arca;

use Carbon\CarbonImmutable;

/** Un comprobante tal como lo tiene ARCA (FECompConsultar). */
final readonly class ComprobanteConsultado
{
    public function __construct(
        public int $puntoDeVenta,
        public int $tipo,
        public int $numero,
        public CarbonImmutable $fecha,
        public string $importeTotal,
        public string $resultado,
        public ?string $cae,
        public ?CarbonImmutable $caeVencimiento,
        public int $tipoDocumento,
        public string $numeroDocumento,
    ) {}
}
