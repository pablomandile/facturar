<?php

declare(strict_types=1);

namespace App\Services\Facturas;

use App\Enums\Arca\Ambiente;
use App\Enums\EstadoComprobante;
use App\Exceptions\Facturas\EnvioNoPermitido;
use App\Mail\FacturaPorEmail;
use App\Models\Comprobante;
use App\Models\EnvioDeComprobante;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * El único que manda facturas por email, a mano (botón del detalle) o al
 * autorizarse (`EnviarAlAutorizar`). Cada intento queda en
 * `envios_de_comprobantes`, salga bien o mal.
 *
 * Un fallo del correo **no lanza**: queda registrado con su error y se
 * devuelve. Así un SMTP caído nunca tumba la emisión que lo disparó.
 */
class EnviadorDeFacturas
{
    public const DESTINATARIOS_MAXIMOS = 3;

    public function __construct(
        private readonly GeneradorDePdf $pdf,
        private readonly TextoDelEmail $textoDelEmail,
    ) {}

    /**
     * @param  list<string>  $para
     * @param  string|null  $asunto  null: el de la plantilla del emisor
     *
     * @throws EnvioNoPermitido
     */
    public function enviar(Comprobante $comprobante, array $para, bool $copiaAMi, string $texto, ?string $asunto = null): EnvioDeComprobante
    {
        $comprobante->loadMissing(['emisor.usuario', 'puntoDeVenta', 'items']);
        $propio = $comprobante->emisor->usuario->email;
        $para = array_values(array_unique(array_map(fn (string $email): string => mb_strtolower(trim($email)), $para)));

        $this->asegurarQueSePuede($comprobante, $para, $propio);

        // La copia, solo si no está ya entre los destinatarios.
        $conCopia = $copiaAMi && ! in_array(mb_strtolower($propio), $para, true);
        $texto = trim(str_replace("\r\n", "\n", $texto));
        $asunto = TextoDelEmail::unaLinea($asunto ?? '') ?: $this->textoDelEmail->asuntoPara($comprobante);

        try {
            $correo = new FacturaPorEmail(
                comprobante: $comprobante,
                asunto: $asunto,
                texto: $texto,
                pdf: $this->pdf->generar($comprobante)->output(),
                nombreDelPdf: $this->pdf->nombreDeArchivo($comprobante),
                responderA: $propio,
            );

            Mail::to($para)->cc($conCopia ? [$propio] : [])->send($correo);
            $error = null;
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar una factura por email', ['comprobante' => $comprobante->id, 'error' => $e->getMessage()]);
            $error = $e->getMessage();
        }

        return $comprobante->envios()->create([
            'destinatarios' => $para,
            'con_copia' => $conCopia,
            'asunto' => $asunto,
            'texto' => $texto,
            'enviado' => $error === null,
            'error' => $error === null ? null : mb_substr($error, 0, 1000),
        ]);
    }

    /** @param  list<string>  $para */
    private function asegurarQueSePuede(Comprobante $comprobante, array $para, string $propio): void
    {
        if ($comprobante->estado !== EstadoComprobante::Autorizado) {
            throw new EnvioNoPermitido('Solo se envían facturas autorizadas por ARCA.');
        }

        if ($para === [] || count($para) > self::DESTINATARIOS_MAXIMOS) {
            throw new EnvioNoPermitido('Indicá entre una y '.self::DESTINATARIOS_MAXIMOS.' direcciones.');
        }

        // Una factura de prueba no le llega a nadie más que a quien la hizo.
        if ($comprobante->ambiente === Ambiente::Homologacion && $para !== [mb_strtolower($propio)]) {
            throw new EnvioNoPermitido('Una factura de homologación solo se puede enviar a tu propio email.');
        }
    }
}
