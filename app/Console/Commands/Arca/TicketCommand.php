<?php

declare(strict_types=1);

namespace App\Console\Commands\Arca;

use App\Services\Arca\ProveedorDeTickets;
use App\Services\Arca\RelojFiscal;
use Illuminate\Console\Command;

/**
 * Muestra el ticket de acceso del emisor, pidiéndolo a WSAA si hace falta.
 * No hay forma de forzar uno nuevo: con uno vigente, ARCA lo niega.
 */
class TicketCommand extends Command
{
    use ResuelveEmisor;

    protected $signature = 'arca:ticket {emisor : id o CUIT}';

    protected $description = 'Obtiene (o muestra) el ticket de acceso de WSAA del emisor';

    public function handle(ProveedorDeTickets $tickets): int
    {
        return $this->conArca(function () use ($tickets): int {
            $emisor = $this->emisor();
            $this->encabezado($emisor);
            $ticket = $tickets->obtener($emisor);

            $this->table(['', ''], [
                ['Generado', $ticket->generado_en?->setTimezone(RelojFiscal::ZONA)->format('d/m/Y H:i')],
                ['Vence', $ticket->expira_en?->setTimezone(RelojFiscal::ZONA)->format('d/m/Y H:i')],
                ['Certificado', $emisor->certificadoVigente()?->alias],
            ]);

            return self::SUCCESS;
        });
    }
}
