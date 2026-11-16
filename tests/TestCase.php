<?php

namespace Tests;

use App\Contracts\Arca\FabricaDeTransportes;
use App\Contracts\Arca\TransporteSoap;
use App\Enums\Arca\Ambiente;
use App\Enums\Arca\ServicioArca;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ningún test sale a ARCA. El que lo necesite, llama a arcaFalso().
        $this->app->instance(FabricaDeTransportes::class, new class implements FabricaDeTransportes
        {
            public function para(ServicioArca $servicio, Ambiente $ambiente): TransporteSoap
            {
                throw new RuntimeException("El test llegó a ARCA ({$servicio->value}) sin llamar a arcaFalso().");
            }
        });
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
