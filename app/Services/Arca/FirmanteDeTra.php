<?php

declare(strict_types=1);

namespace App\Services\Arca;

use App\Exceptions\Arca\EntornoIncompleto;

/**
 * Firma el TRA con el certificado y la clave del emisor (CMS / PKCS#7) y
 * devuelve lo que espera loginCms: el CMS en base64, sin los encabezados
 * S/MIME que agrega OpenSSL.
 *
 * openssl_pkcs7_sign() solo trabaja con archivos: el TRA y la firma pasan
 * por dos temporales en el disco privado que se borran pase lo que pase. La
 * clave no se escribe nunca a disco: va en memoria.
 */
class FirmanteDeTra
{
    public function firmar(string $tra, string $certificadoPem, string $clavePem): string
    {
        $directorio = $this->directorio();
        $entrada = tempnam($directorio, 'tra');
        $salida = tempnam($directorio, 'cms');

        if ($entrada === false || $salida === false) {
            throw new EntornoIncompleto("No se pudieron crear archivos temporales en {$directorio}.");
        }

        try {
            chmod($entrada, 0600);
            chmod($salida, 0600);
            file_put_contents($entrada, $tra);

            // Flags 0: firma con el contenido adentro (no PKCS7_DETACHED),
            // que es lo que pide WSAA. Sin cadena de certificados: alcanza
            // con el del emisor.
            if (! openssl_pkcs7_sign($entrada, $salida, $certificadoPem, [$clavePem, ''], [], 0)) {
                throw new EntornoIncompleto('No se pudo firmar el pedido a ARCA: '.(openssl_error_string() ?: 'sin detalle de OpenSSL'));
            }

            return $this->sinEncabezados((string) file_get_contents($salida));
        } finally {
            @unlink($entrada);
            @unlink($salida);
        }
    }

    /**
     * OpenSSL devuelve un mensaje S/MIME: encabezados, una línea vacía y el
     * CMS en base64. WSAA quiere solo el base64; con los encabezados contesta
     * cms.bad.
     */
    private function sinEncabezados(string $smime): string
    {
        $partes = preg_split("/\r?\n\r?\n/", $smime, 2);

        if ($partes === false || count($partes) !== 2) {
            throw new EntornoIncompleto('OpenSSL devolvió una firma con formato inesperado.');
        }

        return trim($partes[1]);
    }

    private function directorio(): string
    {
        $directorio = (string) config('arca.directorio_temporal');

        if (! is_dir($directorio) && ! @mkdir($directorio, 0700, true) && ! is_dir($directorio)) {
            throw new EntornoIncompleto("No se pudo crear el directorio temporal {$directorio}.");
        }

        return $directorio;
    }
}
