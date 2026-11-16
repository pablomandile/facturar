<?php

declare(strict_types=1);

namespace App\Services\Facturas;

use App\Models\Comprobante;
use App\Services\Arca\SerializadorDeFacturaC;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use LogicException;

/**
 * El código QR que exige ARCA en cada comprobante electrónico (RG 4892).
 *
 * El QR es una URL de ARCA con los datos del comprobante en JSON y base64:
 * quien lo escanea ve en la web de ARCA si el comprobante es legítimo. Los
 * campos y sus tipos son los de la especificación: números como números,
 * fecha como AAAA-MM-DD, codAut el CAE como número.
 */
class ArmadorDeQr
{
    public const URL = 'https://www.afip.gob.ar/fe/qr/';

    /** @return array<string, int|float|string> */
    public function datos(Comprobante $comprobante): array
    {
        if ($comprobante->cae === null || $comprobante->numero === null) {
            throw new LogicException('Solo un comprobante autorizado lleva el QR de ARCA.');
        }

        $datos = [
            'ver' => 1,
            'fecha' => $comprobante->fecha_emision->toDateString(),
            'cuit' => (int) $comprobante->emisor->cuit,
            'ptoVta' => $comprobante->puntoDeVenta->numero,
            'tipoCmp' => $comprobante->tipo->value,
            'nroCmp' => $comprobante->numero,
            'importe' => SerializadorDeFacturaC::importe($comprobante->importe_total),
            'moneda' => $comprobante->moneda,
            'ctz' => (float) $comprobante->cotizacion,
        ];

        // El receptor es opcional: a un consumidor final sin identificar no se lo informa.
        if (! $comprobante->esConsumidorFinalSinIdentificar()) {
            $datos['tipoDocRec'] = $comprobante->receptor_tipo_documento->value;
            $datos['nroDocRec'] = (int) $comprobante->receptor_numero_documento;
        }

        $datos['tipoCodAut'] = 'E';
        $datos['codAut'] = (int) $comprobante->cae;

        return $datos;
    }

    public function url(Comprobante $comprobante): string
    {
        return self::URL.'?p='.base64_encode((string) json_encode($this->datos($comprobante)));
    }

    /** El QR como PNG en data URI, listo para un <img> del PDF. */
    public function imagen(Comprobante $comprobante, int $tamanio = 240): string
    {
        $png = (new Writer(new GDLibRenderer($tamanio, margin: 1)))->writeString($this->url($comprobante));

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
