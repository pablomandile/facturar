<?php

declare(strict_types=1);

namespace App\Services\Facturas;

use App\Enums\Arca\Ambiente;
use App\Models\Comprobante;
use App\Models\ComprobanteItem;
use App\Support\Arca\Cuit;
use App\Support\Dinero;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentoPdf;

/**
 * El PDF de una factura autorizada, con el formato que exige ARCA: letra y
 * código del comprobante en el recuadro central, datos del emisor y del
 * receptor, ítems, totales, CAE con su vencimiento y el QR.
 *
 * Se genera al vuelo, no se guarda: todo lo que lleva está en la base y la
 * factura autorizada no cambia nunca.
 */
class GeneradorDePdf
{
    private const MESES = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

    public function __construct(private readonly ArmadorDeQr $qr) {}

    public function generar(Comprobante $comprobante): DocumentoPdf
    {
        $comprobante->loadMissing(['emisor', 'puntoDeVenta', 'items']);

        // Con subsetting embebe solo los glifos usados: ~50 KB en vez de ~900 KB.
        return Pdf::setOption(['isFontSubsettingEnabled' => true])
            ->loadView('pdf.factura-c', $this->datos($comprobante))
            ->setPaper('a4');
    }

    /**
     * "MANDILE_PABLO_NOV_20243660239_011_00002-00000001.pdf": quién factura,
     * el mes del período facturado (sin período, el de la emisión), su CUIT,
     * el código del comprobante en ARCA (011 = factura C) y el número. Es el
     * mismo para la descarga, el email y la exportación de datos.
     */
    public function nombreDeArchivo(Comprobante $comprobante): string
    {
        $comprobante->loadMissing(['emisor', 'puntoDeVenta']);
        $mes = ($comprobante->fecha_servicio_desde ?? $comprobante->fecha_emision)->month;

        return implode('_', [
            $comprobante->emisor->nombreParaArchivos(),
            self::MESES[$mes - 1],
            $comprobante->emisor->cuit,
            str_pad((string) $comprobante->tipo->value, 3, '0', STR_PAD_LEFT),
            $comprobante->numeroCompleto(),
        ]).'.pdf';
    }

    /**
     * Lo que recibe la vista del PDF. Público para poder revisar el HTML sin
     * pasar por dompdf, que comprime el texto.
     *
     * @return array<string, mixed>
     */
    public function datos(Comprobante $comprobante): array
    {
        $emisor = $comprobante->emisor;

        return [
            'comprobante' => $comprobante,
            'numero' => $comprobante->numeroCompleto(),
            'punto' => $comprobante->puntoDeVenta->numeroFormateado(),
            'numeroSolo' => str_pad((string) $comprobante->numero, 8, '0', STR_PAD_LEFT),
            'emisor' => [
                'razon_social' => $emisor->razon_social,
                'domicilio' => $emisor->domicilio_fiscal,
                'cuit' => Cuit::formatear($emisor->cuit),
                'ingresos_brutos' => $emisor->ingresos_brutos ?: 'No inscripto',
                'inicio_actividades' => $emisor->inicio_actividades->format('d/m/Y'),
            ],
            'receptor' => [
                'nombre' => $comprobante->receptor_nombre,
                'documento' => $comprobante->esConsumidorFinalSinIdentificar()
                    ? null
                    : $comprobante->receptor_tipo_documento->etiqueta().': '.$comprobante->receptor_tipo_documento->formatear($comprobante->receptor_numero_documento),
                'condicion_iva' => $comprobante->receptor_condicion_iva->etiqueta(),
                'domicilio' => $comprobante->receptor_domicilio,
            ],
            'items' => $comprobante->items->map(fn (ComprobanteItem $item): array => [
                'descripcion' => $item->descripcion,
                'cantidad' => str_replace('.', ',', rtrim(rtrim($item->cantidad, '0'), '.')),
                'unidad' => $item->unidad,
                'precio' => Dinero::formatear($item->precio_unitario),
                'importe' => Dinero::formatear($item->importe),
            ])->all(),
            'subtotal' => Dinero::formatear($comprobante->importe_neto),
            'tributos' => Dinero::formatear($comprobante->importe_tributos),
            'total' => Dinero::formatear($comprobante->importe_total),
            'qr' => $this->qr->imagen($comprobante),
            'sinValidez' => $comprobante->ambiente === Ambiente::Homologacion,
        ];
    }
}
