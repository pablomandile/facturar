<?php

declare(strict_types=1);

namespace App\Services\Arca\Demo;

use App\Contracts\Arca\FabricaDeTransportes;
use App\Contracts\Arca\TransporteSoap;
use App\Enums\Arca\Ambiente;
use App\Enums\Arca\ServicioArca;

/**
 * La ARCA de la demo (APP_DEMO=true, ver config/demo.php): nada sale a la
 * red. La registra `AppServiceProvider` en lugar de la real, así que todo lo
 * demás (ticket, numeración, lock, registro de llamadas, PDF) corre igual que
 * en la productiva.
 */
class FabricaDeTransportesDemo implements FabricaDeTransportes
{
    public function para(ServicioArca $servicio, Ambiente $ambiente): TransporteSoap
    {
        return new TransporteDemo($servicio);
    }
}
