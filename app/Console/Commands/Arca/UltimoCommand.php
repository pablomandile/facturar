<?php

declare(strict_types=1);

namespace App\Console\Commands\Arca;

use App\Enums\Arca\TipoComprobante;
use App\Services\Arca\ClienteWsfe;
use Illuminate\Console\Command;

/** El número del último comprobante autorizado en un punto de venta. */
class UltimoCommand extends Command
{
    use ResuelveEmisor;

    protected $signature = 'arca:ultimo {emisor : id o CUIT} {punto-de-venta : número}';

    protected $description = 'Consulta FECompUltimoAutorizado para la factura C de un punto de venta';

    public function handle(ClienteWsfe $wsfe): int
    {
        return $this->conArca(function () use ($wsfe): int {
            $emisor = $this->emisor();
            $this->encabezado($emisor);
            $puntoDeVenta = (int) $this->argument('punto-de-venta');

            $numero = $wsfe->ultimoAutorizado($emisor, $puntoDeVenta, TipoComprobante::FacturaC);

            $this->info("Última factura C autorizada en el punto de venta {$puntoDeVenta}: {$numero}");

            return self::SUCCESS;
        });
    }
}
