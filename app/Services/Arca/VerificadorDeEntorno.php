<?php

declare(strict_types=1);

namespace App\Services\Arca;

use App\Enums\Arca\Ambiente;
use App\Support\Arca\ChequeoDeEntorno;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * ¿Puede este servidor hablar con ARCA? Lo que falte acá no da un error
 * claro en el momento de facturar: da un `false` de OpenSSL sin mensaje o un
 * SoapFault genérico. Por eso se corre en cada deploy (`arca:diagnostico`).
 */
class VerificadorDeEntorno
{
    /** Diferencia máxima tolerable con el reloj de ARCA, en segundos. */
    private const DESVIO_MAXIMO = 120;

    /** @return list<ChequeoDeEntorno> */
    public function locales(): array
    {
        return [
            $this->extension('soap'),
            $this->extension('openssl'),
            $this->configuracionOpenSsl(),
            $this->wsdl('wsaa'),
            $this->wsdl('wsfe'),
            $this->directorioTemporal(),
        ];
    }

    /** @return list<ChequeoDeEntorno> */
    public function deRed(): array
    {
        $chequeos = [];

        foreach (Ambiente::cases() as $ambiente) {
            $chequeos[] = $this->salida("WSAA {$ambiente->etiqueta()}", $ambiente->urlWsaa().'?wsdl');
            $chequeos[] = $this->salida("WSFE {$ambiente->etiqueta()}", $ambiente->urlWsfe().'?WSDL');
        }

        $chequeos[] = $this->reloj();

        return $chequeos;
    }

    private function extension(string $nombre): ChequeoDeEntorno
    {
        return extension_loaded($nombre)
            ? ChequeoDeEntorno::bien("Extensión {$nombre}", 'cargada')
            : ChequeoDeEntorno::mal("Extensión {$nombre}", "falta: habilitá extension={$nombre} en el php.ini (en Hostinger, hPanel → Configuración de PHP)");
    }

    /**
     * Genera una clave de verdad: es la única forma de saber que OpenSSL
     * encuentra su configuración (en Windows es lo primero que falla).
     */
    private function configuracionOpenSsl(): ChequeoDeEntorno
    {
        $ruta = (string) config('arca.openssl_cnf');

        if (! is_readable($ruta)) {
            return ChequeoDeEntorno::mal('Configuración de OpenSSL', "no se puede leer {$ruta}");
        }

        if (! extension_loaded('openssl')) {
            return ChequeoDeEntorno::mal('Configuración de OpenSSL', 'sin la extensión openssl no se puede probar');
        }

        $clave = openssl_pkey_new([...GeneradorDeCertificados::opcionesOpenSsl(), 'private_key_bits' => 2048]);

        return $clave === false
            ? ChequeoDeEntorno::mal('Configuración de OpenSSL', 'no se pudo generar una clave de prueba: '.(openssl_error_string() ?: 'sin detalle'))
            : ChequeoDeEntorno::bien('Configuración de OpenSSL', 'genera claves RSA de 2048 bits');
    }

    private function wsdl(string $servicio): ChequeoDeEntorno
    {
        $ruta = (string) config("arca.wsdl.{$servicio}");

        return is_readable($ruta)
            ? ChequeoDeEntorno::bien("WSDL de {$servicio}", 'presente')
            : ChequeoDeEntorno::mal("WSDL de {$servicio}", "no se encuentra {$ruta}");
    }

    private function directorioTemporal(): ChequeoDeEntorno
    {
        $ruta = (string) config('arca.directorio_temporal');

        if (! is_dir($ruta) && ! @mkdir($ruta, 0700, true) && ! is_dir($ruta)) {
            return ChequeoDeEntorno::mal('Directorio temporal', "no se pudo crear {$ruta}");
        }

        return is_writable($ruta)
            ? ChequeoDeEntorno::bien('Directorio temporal', 'se puede escribir')
            : ChequeoDeEntorno::mal('Directorio temporal', "sin permiso de escritura en {$ruta}");
    }

    private function salida(string $nombre, string $url): ChequeoDeEntorno
    {
        try {
            // Con los mismos cifrados que usa la app: si no, en Hostinger el WSFE
            // de producción da "sin conexión" aunque facturar ande.
            $respuesta = Http::timeout((int) config('arca.timeout_conexion'))
                ->withOptions(['curl' => [CURLOPT_SSL_CIPHER_LIST => (string) config('arca.cifrados_tls')]])
                ->get($url);

            return $respuesta->successful()
                ? ChequeoDeEntorno::bien($nombre, 'responde')
                : ChequeoDeEntorno::mal($nombre, "respondió HTTP {$respuesta->status()}");
        } catch (Throwable $e) {
            return ChequeoDeEntorno::mal($nombre, 'sin conexión: '.$e->getMessage());
        }
    }

    /**
     * WSAA rechaza un pedido cuyo horario no cierra con el suyo. Se compara
     * contra el encabezado `Date` de su propia respuesta.
     */
    private function reloj(): ChequeoDeEntorno
    {
        try {
            $fecha = Http::timeout((int) config('arca.timeout_conexion'))
                ->get(Ambiente::Produccion->urlWsaa().'?wsdl')
                ->header('Date');
        } catch (Throwable $e) {
            return ChequeoDeEntorno::mal('Reloj del servidor', 'no se pudo consultar la hora de ARCA: '.$e->getMessage());
        }

        if ($fecha === '') {
            return ChequeoDeEntorno::mal('Reloj del servidor', 'ARCA no devolvió la hora');
        }

        $desvio = (int) abs(now()->diffInSeconds(Carbon::parse($fecha), false));

        return $desvio <= self::DESVIO_MAXIMO
            ? ChequeoDeEntorno::bien('Reloj del servidor', "{$desvio} s de diferencia con ARCA")
            : ChequeoDeEntorno::mal('Reloj del servidor', "{$desvio} s de diferencia con ARCA: el pedido de ticket puede ser rechazado");
    }
}
