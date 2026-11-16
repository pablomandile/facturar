<?php

declare(strict_types=1);

namespace App\Console\Commands\Arca;

use App\Enums\Arca\TipoComprobante;
use App\Services\Arca\ClienteWsfe;
use Illuminate\Console\Command;

/** Un comprobante tal como lo tiene ARCA. */
class ConsultarCommand extends Command
{
    use ResuelveEmisor;

    protected $signature = 'arca:consultar {emisor : id o CUIT} {punto-de-venta : número} {numero : número de comprobante}';

    protected $description = 'Consulta FECompConsultar para una factura C';

    public function handle(ClienteWsfe $wsfe): int
    {
        return $this->conArca(function () use ($wsfe): int {
            $emisor = $this->emisor();
            $this->encabezado($emisor);

            $comprobante = $wsfe->consultar($emisor, (int) $this->argument('punto-de-venta'), TipoComprobante::FacturaC, (int) $this->argument('numero'));

            if ($comprobante === null) {
                $this->warn('ARCA no tiene ese comprobante.');

                return self::FAILURE;
            }

            $this->table(['', ''], [
                ['Número', sprintf('%05d-%08d', $comprobante->puntoDeVenta, $comprobante->numero)],
                ['Fecha', $comprobante->fecha->format('d/m/Y')],
                ['Total', $comprobante->importeTotal],
                ['Receptor', "{$comprobante->tipoDocumento} {$comprobante->numeroDocumento}"],
                ['Resultado', $comprobante->resultado],
                ['CAE', $comprobante->cae],
                ['Vencimiento del CAE', $comprobante->caeVencimiento?->format('d/m/Y')],
            ]);

            return self::SUCCESS;
        });
    }
}
