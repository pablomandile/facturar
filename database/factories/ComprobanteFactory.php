<?php

namespace Database\Factories;

use App\Enums\Arca\Ambiente;
use App\Enums\Arca\Concepto;
use App\Enums\Arca\CondicionIvaReceptor;
use App\Enums\Arca\TipoComprobante;
use App\Enums\Arca\TipoDocumento;
use App\Enums\EstadoComprobante;
use App\Models\Comprobante;
use App\Models\Emisor;
use App\Models\PuntoDeVenta;
use App\Services\Arca\RelojFiscal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Un borrador de factura C a consumidor final, de hoy, con un ítem de
 * $1.000. El punto de venta es del mismo emisor (usá `for($emisor)` y
 * `paraPunto($punto)` para fijarlos).
 *
 * @extends Factory<Comprobante>
 */
class ComprobanteFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'emisor_id' => Emisor::factory(),
            'punto_de_venta_id' => fn (array $a) => PuntoDeVenta::factory()->create(['emisor_id' => $a['emisor_id'], 'numero' => 1])->id,
            'ambiente' => Ambiente::Homologacion,
            'tipo' => TipoComprobante::FacturaC,
            'concepto' => Concepto::Productos,
            'numero' => null,
            'fecha_emision' => (new RelojFiscal)->hoy()->toDateString(),
            'receptor_tipo_documento' => TipoDocumento::ConsumidorFinal,
            'receptor_numero_documento' => '0',
            'receptor_nombre' => 'Consumidor Final',
            'receptor_condicion_iva' => CondicionIvaReceptor::ConsumidorFinal,
            'importe_neto' => 100_000,
            'importe_total' => 100_000,
            'estado' => EstadoComprobante::Borrador,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Comprobante $comprobante): void {
            if ($comprobante->items()->exists() || $comprobante->estado === EstadoComprobante::Autorizado) {
                return;
            }

            $comprobante->items()->create([
                'orden' => 1,
                'descripcion' => 'Servicio de prueba',
                'cantidad' => '1',
                'precio_unitario' => $comprobante->importe_neto,
                'importe' => $comprobante->importe_neto,
            ]);
        });
    }

    public function paraPunto(PuntoDeVenta $punto): static
    {
        return $this->state(['emisor_id' => $punto->emisor_id, 'punto_de_venta_id' => $punto->id, 'ambiente' => $punto->ambiente]);
    }

    public function deServicios(): static
    {
        $reloj = new RelojFiscal;

        return $this->state([
            'concepto' => Concepto::Servicios,
            'fecha_servicio_desde' => $reloj->hoy()->startOfMonth()->toDateString(),
            'fecha_servicio_hasta' => $reloj->hoy()->endOfMonth()->toDateString(),
            'fecha_vto_pago' => $reloj->hoy()->addDays(10)->toDateString(),
        ]);
    }

    public function conTotal(int $centavos): static
    {
        return $this->state(['importe_neto' => $centavos, 'importe_total' => $centavos]);
    }

    public function autorizado(int $numero, string $cae = '76461234567890'): static
    {
        return $this->state([
            'estado' => EstadoComprobante::Autorizado,
            'numero' => $numero,
            'cae' => $cae,
            'cae_vencimiento' => (new RelojFiscal)->hoy()->addDays(10)->toDateString(),
            'autorizado_en' => now(),
        ]);
    }
}
