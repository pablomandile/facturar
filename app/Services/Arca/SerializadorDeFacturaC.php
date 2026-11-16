<?php

declare(strict_types=1);

namespace App\Services\Arca;

use App\Models\Comprobante;

/**
 * Comprobante → FeCAEReq de WSFEv1, con las reglas de la factura C:
 *
 * - Sin IVA discriminado: **no va el nodo Iva**, ImpIVA = 0, ImpNeto es el
 *   total sin tributos. ARCA rechaza una C con Iva.
 * - ImpTotal = ImpNeto + ImpTrib exacto: los importes salen de centavos
 *   enteros.
 * - CondicionIVAReceptorId siempre (obligatorio desde el 1/12/2026).
 * - Las fechas de servicio solo con concepto 2 o 3; con 1 ARCA las rechaza.
 * - Un comprobante por pedido (CantReg 1, CbteDesde = CbteHasta).
 */
class SerializadorDeFacturaC
{
    public function __construct(private readonly RelojFiscal $reloj) {}

    /** @return array<string, mixed> */
    public function serializar(Comprobante $comprobante): array
    {
        $detalle = [
            'Concepto' => $comprobante->concepto->value,
            'DocTipo' => $comprobante->receptor_tipo_documento->value,
            'DocNro' => (int) $comprobante->receptor_numero_documento,
            'CbteDesde' => $comprobante->numero,
            'CbteHasta' => $comprobante->numero,
            'CbteFch' => $this->reloj->ymd($comprobante->fecha_emision),
            'ImpTotal' => self::importe($comprobante->importe_total),
            'ImpTotConc' => 0,
            'ImpNeto' => self::importe($comprobante->importe_neto),
            'ImpOpEx' => 0,
            'ImpTrib' => self::importe($comprobante->importe_tributos),
            'ImpIVA' => 0,
        ];

        if ($comprobante->concepto->exigeFechasDeServicio()) {
            $detalle['FchServDesde'] = $this->reloj->ymd($comprobante->fecha_servicio_desde ?? $comprobante->fecha_emision);
            $detalle['FchServHasta'] = $this->reloj->ymd($comprobante->fecha_servicio_hasta ?? $comprobante->fecha_emision);
            $detalle['FchVtoPago'] = $this->reloj->ymd($comprobante->fecha_vto_pago ?? $comprobante->fecha_emision);
        }

        $detalle['MonId'] = $comprobante->moneda;
        $detalle['MonCotiz'] = (float) $comprobante->cotizacion;
        $detalle['CondicionIVAReceptorId'] = $comprobante->receptor_condicion_iva->value;

        return [
            'FeCabReq' => [
                'CantReg' => 1,
                'PtoVta' => $comprobante->puntoDeVenta->numero,
                'CbteTipo' => $comprobante->tipo->value,
            ],
            'FeDetReq' => [
                'FECAEDetRequest' => [$detalle],
            ],
        ];
    }

    /** Centavos → pesos con dos decimales, sin pasar por la división de flotantes. */
    public static function importe(int $centavos): float
    {
        return (float) (intdiv($centavos, 100).'.'.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT));
    }
}
