<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Arca\Ambiente;
use App\Enums\Arca\Concepto;
use App\Enums\Arca\CondicionIvaReceptor;
use App\Enums\Arca\TipoComprobante;
use App\Enums\Arca\TipoDocumento;
use App\Enums\EstadoComprobante;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\ComprobanteItem;
use App\Models\Emisor;
use App\Support\Dinero;
use Illuminate\Support\Facades\DB;

/**
 * Vuelca el formulario en un comprobante en borrador: ítems, totales y la
 * **copia** del receptor. Editar un rechazado o uno con error lo devuelve a
 * borrador y borra la respuesta de ARCA anterior.
 */
class ArmadorDeComprobantes
{
    /**
     * @param  array{punto_de_venta_id: int, cliente_id?: int|null, concepto: int, fecha_emision: string, fecha_servicio_desde?: string|null, fecha_servicio_hasta?: string|null, fecha_vto_pago?: string|null, observaciones?: string|null, enviar_al_autorizar?: bool, items: list<array{descripcion: string, cantidad: string, unidad?: string|null, precio_unitario: string}>}  $datos
     */
    public function guardar(Emisor $emisor, array $datos, ?Comprobante $comprobante = null): Comprobante
    {
        return DB::transaction(function () use ($emisor, $datos, $comprobante): Comprobante {
            $comprobante ??= new Comprobante;
            $concepto = Concepto::from((int) $datos['concepto']);
            $conServicios = $concepto->exigeFechasDeServicio();

            $comprobante->forceFill([
                'emisor_id' => $emisor->id,
                'punto_de_venta_id' => $datos['punto_de_venta_id'],
                'ambiente' => $emisor->ambiente,
                'tipo' => TipoComprobante::FacturaC,
                'concepto' => $concepto,
                'fecha_emision' => $datos['fecha_emision'],
                'fecha_servicio_desde' => $conServicios ? ($datos['fecha_servicio_desde'] ?? null) : null,
                'fecha_servicio_hasta' => $conServicios ? ($datos['fecha_servicio_hasta'] ?? null) : null,
                'fecha_vto_pago' => $conServicios ? ($datos['fecha_vto_pago'] ?? null) : null,
                'observaciones' => $datos['observaciones'] ?? null,
                // Una factura de prueba no se le manda al cliente (EnviarAlAutorizar).
                'enviar_al_autorizar' => ($datos['enviar_al_autorizar'] ?? false) && $emisor->ambiente === Ambiente::Produccion,
                'estado' => EstadoComprobante::Borrador,
                'numero' => null,
                'respuesta_arca' => null,
                'importe_neto' => 0,
                'importe_total' => 0,
            ]);
            $this->copiarReceptor($comprobante, isset($datos['cliente_id']) ? Cliente::query()->find($datos['cliente_id']) : null);
            $comprobante->save();

            $comprobante->items()->delete();
            foreach ($datos['items'] as $orden => $item) {
                $precio = Dinero::aCentavos($item['precio_unitario']);
                $comprobante->items()->create([
                    'orden' => $orden + 1,
                    'descripcion' => $item['descripcion'],
                    'cantidad' => $item['cantidad'],
                    'unidad' => $item['unidad'] ?? null,
                    'precio_unitario' => $precio,
                    'importe' => ComprobanteItem::importeDe($item['cantidad'], $precio),
                ]);
            }

            $comprobante->load('items');
            $comprobante->recalcular();
            $comprobante->save();

            return $comprobante;
        });
    }

    /**
     * Sin cliente: consumidor final sin identificar (DocTipo 99, DocNro 0).
     * Con cliente: sus datos de hoy, que quedan fijos en la factura.
     */
    private function copiarReceptor(Comprobante $comprobante, ?Cliente $cliente): void
    {
        $comprobante->forceFill($cliente === null ? [
            'cliente_id' => null,
            'receptor_tipo_documento' => TipoDocumento::ConsumidorFinal,
            'receptor_numero_documento' => '0',
            'receptor_nombre' => 'Consumidor Final',
            'receptor_condicion_iva' => CondicionIvaReceptor::ConsumidorFinal,
            'receptor_domicilio' => null,
        ] : [
            'cliente_id' => $cliente->id,
            'receptor_tipo_documento' => $cliente->tipo_documento,
            'receptor_numero_documento' => $cliente->numero_documento,
            'receptor_nombre' => $cliente->nombre,
            'receptor_condicion_iva' => $cliente->condicion_iva,
            'receptor_domicilio' => $cliente->domicilio,
        ]);
    }
}
