<?php

declare(strict_types=1);

namespace App\Contracts\Arca;

use App\Enums\Arca\Ambiente;
use App\Enums\Arca\ServicioArca;

/**
 * Da el transporte para un servicio en un ambiente. Los tests registran una
 * fábrica falsa en el contenedor antes de resolver a quien la usa.
 */
interface FabricaDeTransportes
{
    public function para(ServicioArca $servicio, Ambiente $ambiente): TransporteSoap;
}
