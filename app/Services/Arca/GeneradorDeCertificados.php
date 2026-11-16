<?php

declare(strict_types=1);

namespace App\Services\Arca;

use App\Enums\Arca\Ambiente;
use App\Enums\Arca\EstadoCertificado;
use App\Exceptions\Arca\CertificadoInvalido;
use App\Exceptions\Arca\EntornoIncompleto;
use App\Models\Certificado;
use App\Models\Emisor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSSLAsymmetricKey;

/**
 * El ciclo del certificado de un emisor: generar la clave y el pedido (CSR),
 * y después incorporar el certificado que ARCA devuelve firmado.
 *
 * La clave privada se genera acá y no sale nunca: la persona sube a ARCA
 * solo el CSR, que es público. Así no hay que pedirle que maneje OpenSSL ni
 * que suba su clave a ningún lado.
 */
class GeneradorDeCertificados
{
    /**
     * Genera clave y CSR nuevos para el ambiente. Un pedido anterior que
     * nunca recibió su certificado se descarta (no sirvió para nada); un
     * certificado vigente se mantiene hasta que llegue el nuevo.
     */
    public function generar(Emisor $emisor, Ambiente $ambiente, string $alias): Certificado
    {
        $clave = $this->nuevaClave();
        $clavePem = $this->exportarClave($clave);
        $csrPem = $this->nuevoCsr($emisor, $alias, $clave);

        return DB::transaction(function () use ($emisor, $ambiente, $alias, $clavePem, $csrPem): Certificado {
            $emisor->certificados()
                ->where('ambiente', $ambiente->value)
                ->where('estado', EstadoCertificado::EsperandoCertificado->value)
                ->delete();

            $certificado = new Certificado;
            $certificado->forceFill([
                'emisor_id' => $emisor->id,
                'ambiente' => $ambiente,
                'alias' => $alias,
                'clave_privada' => $clavePem,
                'csr' => $csrPem,
                'estado' => EstadoCertificado::EsperandoCertificado,
            ])->save();

            return $certificado;
        });
    }

    /**
     * Valida el certificado que devolvió ARCA contra el pedido y, si
     * corresponde, lo deja vigente (y el anterior del ambiente, reemplazado).
     *
     * @throws CertificadoInvalido con un mensaje para mostrar tal cual
     */
    public function incorporar(Certificado $pendiente, string $contenido): Certificado
    {
        $pem = $this->normalizarPem($contenido);
        $x509 = @openssl_x509_read($pem);

        if ($x509 === false) {
            throw new CertificadoInvalido('El archivo no es un certificado. Subí el que te dio ARCA (suele terminar en .crt o .pem).');
        }

        if (! openssl_x509_check_private_key($x509, $pendiente->clave_privada)) {
            throw new CertificadoInvalido('Ese certificado no corresponde al pedido que generaste acá. Fijate de haber subido a ARCA el último CSR que bajaste.');
        }

        /** @var array{subject?: array<string, string>, issuer?: array<string, string>, validFrom_time_t?: int, validTo_time_t?: int} $datos */
        $datos = openssl_x509_parse($x509) ?: [];
        $this->validarCuit($datos, $pendiente->emisor);
        $this->validarAmbiente($datos, $pendiente->ambiente);

        $desde = Carbon::createFromTimestamp($datos['validFrom_time_t'] ?? 0);
        $hasta = Carbon::createFromTimestamp($datos['validTo_time_t'] ?? 0);

        if ($hasta->isPast()) {
            throw new CertificadoInvalido('Ese certificado ya venció. Generá un pedido nuevo y pedí otro en ARCA.');
        }

        return DB::transaction(function () use ($pendiente, $pem, $desde, $hasta): Certificado {
            $pendiente->emisor->certificados()
                ->where('ambiente', $pendiente->ambiente->value)
                ->where('estado', EstadoCertificado::Vigente->value)
                ->update(['estado' => EstadoCertificado::Reemplazado->value]);

            $pendiente->forceFill([
                'certificado' => $pem,
                'valido_desde' => $desde,
                'valido_hasta' => $hasta,
                'estado' => EstadoCertificado::Vigente,
            ])->save();

            return $pendiente;
        });
    }

    /** Marca como vencidos los vigentes que pasaron su fecha. Devuelve cuántos. */
    public function marcarVencidos(): int
    {
        return Certificado::query()
            ->where('estado', EstadoCertificado::Vigente->value)
            ->where('valido_hasta', '<', now())
            ->update(['estado' => EstadoCertificado::Vencido->value]);
    }

    /** @return array{config: string} */
    public static function opcionesOpenSsl(): array
    {
        return ['config' => (string) config('arca.openssl_cnf')];
    }

    private function nuevaClave(): OpenSSLAsymmetricKey
    {
        $clave = openssl_pkey_new([
            ...self::opcionesOpenSsl(),
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($clave === false) {
            throw new EntornoIncompleto('No se pudo generar la clave: '.$this->erroresOpenSsl());
        }

        return $clave;
    }

    private function exportarClave(OpenSSLAsymmetricKey $clave): string
    {
        if (! openssl_pkey_export($clave, $pem, null, self::opcionesOpenSsl())) {
            throw new EntornoIncompleto('No se pudo exportar la clave: '.$this->erroresOpenSsl());
        }

        /** @var string $pem */
        return $pem;
    }

    /**
     * El sujeto que pide ARCA: `C=AR, O=<razón social>, CN=<alias>,
     * serialNumber=CUIT <cuit>`. La razón social va sin acentos: el CSR los
     * admite, pero las pantallas de ARCA los muestran rotos.
     */
    private function nuevoCsr(Emisor $emisor, string $alias, OpenSSLAsymmetricKey $clave): string
    {
        $sujeto = [
            'countryName' => 'AR',
            'organizationName' => Str::limit(Str::ascii($emisor->razon_social), 64, ''),
            'commonName' => $alias,
            'serialNumber' => "CUIT {$emisor->cuit}",
        ];

        $csr = openssl_csr_new($sujeto, $clave, [...self::opcionesOpenSsl(), 'digest_alg' => 'sha256']);

        if ($csr === false || $csr === true || ! openssl_csr_export($csr, $pem)) {
            throw new EntornoIncompleto('No se pudo generar el pedido de certificado: '.$this->erroresOpenSsl());
        }

        /** @var string $pem */
        return $pem;
    }

    /**
     * Acepta el PEM tal cual lo copia la gente de la pantalla de ARCA (con
     * espacios o líneas de más) y también el formato binario (DER).
     */
    private function normalizarPem(string $contenido): string
    {
        // El trim solo para el PEM: un DER es binario y puede terminar en un
        // byte que trim() toma por espacio (\0, \n, 0x20), y recortarlo rompe
        // la firma. Pasaba en uno de cada tantos certificados.
        if (str_contains($contenido, '-----BEGIN CERTIFICATE-----')) {
            return trim($contenido)."\n";
        }

        return "-----BEGIN CERTIFICATE-----\n"
            .chunk_split(base64_encode($contenido), 64, "\n")
            ."-----END CERTIFICATE-----\n";
    }

    /** @param  array{subject?: array<string, string>}  $datos */
    private function validarCuit(array $datos, Emisor $emisor): void
    {
        $serial = $datos['subject']['serialNumber'] ?? '';

        if ($serial !== "CUIT {$emisor->cuit}") {
            throw new CertificadoInvalido("Ese certificado es de otro CUIT ({$serial}). Tiene que ser del {$emisor->cuitFormateado()}.");
        }
    }

    /**
     * Un certificado de homologación usado en producción (o al revés) lo
     * rechaza WSAA con un mensaje que no dice eso. Mejor avisarlo acá.
     *
     * @param  array{issuer?: array<string, string>}  $datos
     */
    private function validarAmbiente(array $datos, Ambiente $ambiente): void
    {
        $emisorDelCertificado = $datos['issuer']['CN'] ?? '';

        foreach (Ambiente::cases() as $otro) {
            if ($otro !== $ambiente && $emisorDelCertificado === $otro->autoridadCertificante()) {
                throw new CertificadoInvalido(
                    "Ese certificado es de {$otro->etiqueta()} y lo estás cargando para {$ambiente->etiqueta()}."
                );
            }
        }
    }

    private function erroresOpenSsl(): string
    {
        $errores = [];
        while (($error = openssl_error_string()) !== false) {
            $errores[] = $error;
        }

        return $errores === [] ? 'error desconocido de OpenSSL' : implode(' | ', $errores);
    }
}
