<?php

declare(strict_types=1);

namespace App\Support\Arca;

/** Lo que responde FEDummy: si los tres servidores de WSFE están arriba. */
final readonly class EstadoDelServidor
{
    public function __construct(
        public string $aplicacion,
        public string $baseDeDatos,
        public string $autenticacion,
    ) {}

    public function todoOk(): bool
    {
        return $this->aplicacion === 'OK' && $this->baseDeDatos === 'OK' && $this->autenticacion === 'OK';
    }
}
