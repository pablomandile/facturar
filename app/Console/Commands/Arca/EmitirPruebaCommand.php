<?php

declare(strict_types=1);

namespace App\Console\Commands\Arca;

use App\Enums\Arca\Ambiente;
use App\Enums\Arca\Concepto;
use App\Services\Arca\EmisorDeComprobantes;
use App\Services\Arca\RelojFiscal;
use App\Services\ArmadorDeComprobantes;
use Illuminate\Console\Command;

/**
 * Emite una factura C de prueba a consumidor final. **Solo en
 * homologación**: en producción sería una factura de verdad.
 */
class EmitirPruebaCommand extends Command
{
    use ResuelveEmisor;

    protected $signature = 'arca:emitir-prueba
        {emisor : id o CUIT}
        {punto-de-venta : número}
        {--importe=1000 : total en pesos}';

    protected $description = 'Emite una factura C de prueba en homologación';

    public function handle(ArmadorDeComprobantes $armador, EmisorDeComprobantes $emisorDeComprobantes): int
    {
        return $this->conArca(function () use ($armador, $emisorDeComprobantes): int {
            $emisor = $this->emisor();
            $this->encabezado($emisor);

            if ($emisor->ambiente !== Ambiente::Homologacion) {
                $this->error('Este comando solo emite en homologación: en producción sería una factura real.');

                return self::FAILURE;
            }

            $punto = $emisor->puntosDeVenta()
                ->where('ambiente', $emisor->ambiente->value)
                ->where('numero', (int) $this->argument('punto-de-venta'))
                ->first();

            if ($punto === null) {
                $this->error('Ese punto de venta no está cargado para el emisor en homologación.');

                return self::FAILURE;
            }

            $comprobante = $armador->guardar($emisor, [
                'punto_de_venta_id' => $punto->id,
                'concepto' => Concepto::Productos->value,
                'fecha_emision' => (new RelojFiscal)->hoy()->toDateString(),
                'observaciones' => 'Factura de prueba (arca:emitir-prueba)',
                'items' => [[
                    'descripcion' => 'Prueba de homologación',
                    'cantidad' => '1',
                    'precio_unitario' => (string) $this->option('importe'),
                ]],
            ]);

            $emisorDeComprobantes->emitir($comprobante);

            $this->info("Autorizada: {$comprobante->numeroCompleto()} · CAE {$comprobante->cae} · vence {$comprobante->cae_vencimiento?->format('d/m/Y')}");

            return self::SUCCESS;
        });
    }
}
