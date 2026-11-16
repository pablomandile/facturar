<?php

declare(strict_types=1);

namespace App\Console\Commands\Arca;

use App\Enums\EstadoComprobante;
use App\Models\Comprobante;
use App\Services\Arca\EmisorDeComprobantes;
use Illuminate\Console\Command;

/**
 * Resuelve los comprobantes que quedaron Pendientes: se mandaron a ARCA y
 * no llegó la respuesta. Para cada uno pregunta a ARCA si lo tiene. Corre
 * cada 5 minutos; mientras tanto, su punto de venta no emite.
 */
class ReconciliarPendientesCommand extends Command
{
    protected $signature = 'arca:reconciliar-pendientes';

    protected $description = 'Consulta a ARCA los comprobantes que quedaron sin respuesta';

    public function handle(EmisorDeComprobantes $emisor): int
    {
        // Un minuto de gracia: el que acaba de mandarse puede estar en vuelo.
        $pendientes = Comprobante::query()
            ->with(['emisor', 'puntoDeVenta'])
            ->where('estado', EstadoComprobante::Pendiente->value)
            ->where('enviado_en', '<', now()->subMinute())
            ->orderBy('id')
            ->get();

        foreach ($pendientes as $comprobante) {
            $estado = $emisor->reconciliar($comprobante);
            $this->line(sprintf('Comprobante %d (%s): %s', $comprobante->id, $comprobante->numeroCompleto(), $estado?->etiqueta() ?? 'sigue pendiente'));
        }

        $this->info("Pendientes revisados: {$pendientes->count()}");

        return self::SUCCESS;
    }
}
