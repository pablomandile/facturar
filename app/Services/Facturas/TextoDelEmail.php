<?php

declare(strict_types=1);

namespace App\Services\Facturas;

use App\Models\Comprobante;
use App\Support\Dinero;
use Carbon\CarbonImmutable;

/**
 * El texto del email de una factura: la plantilla del emisor (o la de por
 * defecto) con los marcadores completos.
 *
 * Se completa acá y no en el front, a diferencia de la descripción habitual:
 * el envío automático al autorizar no pasa por ninguna pantalla. Como en
 * `lib/plantillaDescripcion.ts`, un marcador desconocido queda tal cual, a la
 * vista, para que se note el error de tipeo.
 */
class TextoDelEmail
{
    public const PLANTILLA_MAXIMA = 2000;

    public const ASUNTO_MAXIMO = 200;

    public const ASUNTO_POR_DEFECTO = 'Factura C {numero} — {razon_social}';

    public const POR_DEFECTO = <<<'TEXTO'
        Hola, {alias}:

        Te envío la factura C {numero} del {fecha}, por {importe}. Va adjunta en PDF.

        Saludos,
        {razon_social}
        TEXTO;

    /**
     * Los marcadores con un ejemplo, para la pantalla donde se edita.
     *
     * @var list<array{marcador: string, ejemplo: string}>
     */
    public const MARCADORES = [
        ['marcador' => '{cliente}', 'ejemplo' => 'Ministerio de Economía'],
        ['marcador' => '{alias}', 'ejemplo' => 'Ministerio'],
        ['marcador' => '{numero}', 'ejemplo' => '00001-00000008'],
        ['marcador' => '{fecha}', 'ejemplo' => '05/11/2026'],
        ['marcador' => '{desde}', 'ejemplo' => '01/10/2026'],
        ['marcador' => '{hasta}', 'ejemplo' => '31/10/2026'],
        ['marcador' => '{mes}', 'ejemplo' => 'octubre'],
        ['marcador' => '{anio}', 'ejemplo' => '2026'],
        ['marcador' => '{importe}', 'ejemplo' => '$ 2.169.169,00'],
        ['marcador' => '{vencimiento}', 'ejemplo' => '20/11/2026'],
        ['marcador' => '{cae}', 'ejemplo' => '76461234567890'],
        ['marcador' => '{razon_social}', 'ejemplo' => 'Tu nombre o razón social'],
    ];

    private const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public function para(Comprobante $comprobante): string
    {
        $plantilla = trim((string) $comprobante->emisor->plantilla_email);

        return $this->completar($plantilla === '' ? self::POR_DEFECTO : $plantilla, $comprobante);
    }

    /** El asunto: el del emisor o el de por defecto, en una sola línea. */
    public function asuntoPara(Comprobante $comprobante): string
    {
        $plantilla = trim((string) $comprobante->emisor->asunto_email);

        return self::unaLinea($this->completar($plantilla === '' ? self::ASUNTO_POR_DEFECTO : $plantilla, $comprobante));
    }

    /** Un asunto con saltos de línea rompe el encabezado del mail. */
    public static function unaLinea(string $texto): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }

    public function completar(string $plantilla, Comprobante $comprobante): string
    {
        // El mes del período; sin período (productos), el de la fecha de emisión.
        $referencia = $comprobante->fecha_servicio_desde ?? $comprobante->fecha_emision;

        $valores = [
            'cliente' => $comprobante->receptor_nombre,
            // El alias del cliente; sin alias (o consumidor final), el nombre:
            // un saludo nunca queda con un hueco.
            'alias' => trim((string) $comprobante->cliente?->alias) ?: $comprobante->receptor_nombre,
            'numero' => $comprobante->numeroCompleto(),
            'fecha' => self::fecha($comprobante->fecha_emision),
            'desde' => self::fecha($comprobante->fecha_servicio_desde),
            'hasta' => self::fecha($comprobante->fecha_servicio_hasta),
            'mes' => self::MESES[$referencia->month - 1],
            'anio' => (string) $referencia->year,
            'importe' => '$ '.Dinero::formatear($comprobante->importe_total),
            'vencimiento' => self::fecha($comprobante->fecha_vto_pago),
            'cae' => (string) $comprobante->cae,
            'razon_social' => $comprobante->emisor->razon_social,
        ];

        return (string) preg_replace_callback(
            '/\{([a-z_]+)\}/',
            fn (array $m): string => $valores[$m[1]] ?? $m[0],
            str_replace("\r\n", "\n", $plantilla),
        );
    }

    private static function fecha(?CarbonImmutable $fecha): string
    {
        return $fecha?->format('d/m/Y') ?? '';
    }
}
