<?php

declare(strict_types=1);

namespace App\Console\Commands\Arca;

use App\Services\Arca\GeneradorDeCertificados;
use Illuminate\Console\Command;

/** Marca como vencidos los certificados que pasaron su fecha. Corre a diario. */
class VencerCertificadosCommand extends Command
{
    protected $signature = 'arca:vencer-certificados';

    protected $description = 'Marca como vencidos los certificados de ARCA que pasaron su fecha';

    public function handle(GeneradorDeCertificados $generador): int
    {
        $cantidad = $generador->marcarVencidos();
        $this->info("Certificados marcados como vencidos: {$cantidad}");

        return self::SUCCESS;
    }
}
