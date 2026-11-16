<?php

declare(strict_types=1);

namespace App\Support\Arca;

use Carbon\CarbonImmutable;

/** Lo que contesta FECAESolicitar para un comprobante. */
final readonly class RespuestaDeCae
{
    /**
     * @param  list<ObservacionDeArca>  $observaciones
     * @param  list<ObservacionDeArca>  $eventos
     */
    public function __construct(
        public string $resultado,
        public ?string $cae,
        public ?CarbonImmutable $caeVencimiento,
        public array $observaciones,
        public array $eventos,
    ) {}

    public function autorizado(): bool
    {
        return $this->resultado === 'A' && $this->cae !== null && $this->cae !== '';
    }

    public function rechazado(): bool
    {
        return $this->resultado === 'R';
    }

    public function tieneObservacion(int $codigo): bool
    {
        foreach ($this->observaciones as $observacion) {
            if ($observacion->codigo === $codigo) {
                return true;
            }
        }

        return false;
    }
}
