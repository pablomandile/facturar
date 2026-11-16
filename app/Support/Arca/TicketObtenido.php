<?php

declare(strict_types=1);

namespace App\Support\Arca;

use Illuminate\Support\Carbon;

/** Lo que devuelve WSAA: el ticket de acceso y su vigencia. */
final readonly class TicketObtenido
{
    public function __construct(
        public string $token,
        public string $sign,
        public string $uniqueId,
        public Carbon $generadoEn,
        public Carbon $expiraEn,
    ) {}
}
