<?php

declare(strict_types=1);

namespace App\Support\Arca;

use Stringable;

/** Un error, una observación o un evento de WSFE: código y mensaje. */
final readonly class ObservacionDeArca implements Stringable
{
    public function __construct(
        public int $codigo,
        public string $mensaje,
    ) {}

    public function __toString(): string
    {
        return "{$this->codigo}: {$this->mensaje}";
    }

    /** @return array{codigo: int, mensaje: string} */
    public function toArray(): array
    {
        return ['codigo' => $this->codigo, 'mensaje' => $this->mensaje];
    }
}
