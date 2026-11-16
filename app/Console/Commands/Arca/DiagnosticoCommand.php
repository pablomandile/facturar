<?php

declare(strict_types=1);

namespace App\Console\Commands\Arca;

use App\Services\Arca\VerificadorDeEntorno;
use App\Support\Arca\ChequeoDeEntorno;
use Illuminate\Console\Command;

/**
 * Lo primero que se corre en un servidor nuevo y después de cada deploy:
 * dice si a este PHP le falta algo para hablar con ARCA.
 */
class DiagnosticoCommand extends Command
{
    protected $signature = 'arca:diagnostico
        {--sin-red : Solo lo local, sin consultar a ARCA}';

    protected $description = 'Verifica que el servidor pueda hablar con los web services de ARCA';

    public function handle(VerificadorDeEntorno $verificador): int
    {
        $chequeos = $verificador->locales();

        if (! $this->option('sin-red')) {
            $chequeos = [...$chequeos, ...$verificador->deRed()];
        }

        $this->table(
            ['', 'Qué', 'Detalle'],
            array_map(fn (ChequeoDeEntorno $c): array => [$c->ok ? 'OK' : 'FALLA', $c->nombre, $c->detalle], $chequeos),
        );

        $fallas = count(array_filter($chequeos, fn (ChequeoDeEntorno $c): bool => ! $c->ok));

        if ($fallas > 0) {
            $this->error("Hay {$fallas} problema(s): este servidor todavía no puede facturar.");

            return self::FAILURE;
        }

        $this->info('Todo en orden.');

        return self::SUCCESS;
    }
}
