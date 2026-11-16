<?php

declare(strict_types=1);

namespace App\Support\Arca;

/** El resultado de una verificación de `VerificadorDeEntorno`. */
final readonly class ChequeoDeEntorno
{
    public function __construct(
        public string $nombre,
        public bool $ok,
        public string $detalle,
    ) {}

    public static function bien(string $nombre, string $detalle): self
    {
        return new self($nombre, true, $detalle);
    }

    public static function mal(string $nombre, string $detalle): self
    {
        return new self($nombre, false, $detalle);
    }
}
