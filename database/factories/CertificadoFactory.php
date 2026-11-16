<?php

namespace Database\Factories;

use App\Enums\Arca\Ambiente;
use App\Enums\Arca\EstadoCertificado;
use App\Models\Certificado;
use App\Models\Emisor;
use App\Services\Arca\GeneradorDeCertificados;
use Illuminate\Database\Eloquent\Factories\Factory;
use OpenSSLAsymmetricKey;
use OpenSSLCertificateSigningRequest;
use RuntimeException;

/**
 * Certificados con claves y CSR de verdad, y en el estado `vigente()` un
 * certificado autofirmado con el CUIT del emisor en el sujeto: sirve para
 * todo menos para hablar con ARCA, que es justamente lo que los tests no
 * hacen.
 *
 * Generar una clave RSA tarda; se genera una sola por proceso y se reusa.
 * El ambiente por defecto es homologación: el de un emisor recién creado.
 *
 * @extends Factory<Certificado>
 */
class CertificadoFactory extends Factory
{
    private static ?OpenSSLAsymmetricKey $clave = null;

    private static ?string $csrGenerico = null;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'emisor_id' => Emisor::factory(),
            'ambiente' => Ambiente::Homologacion,
            'alias' => 'facturar',
            'clave_privada' => self::clavePem(),
            'csr' => self::$csrGenerico ??= self::csr(['serialNumber' => 'CUIT 20111111112']),
            'certificado' => null,
            'valido_desde' => null,
            'valido_hasta' => null,
            'estado' => EstadoCertificado::EsperandoCertificado,
        ];
    }

    /** Firmado y en vigencia, con el CUIT de su emisor. */
    public function vigente(): static
    {
        return $this->state([
            'valido_desde' => now()->subDay(),
            'valido_hasta' => now()->addYears(2),
            'estado' => EstadoCertificado::Vigente,
        ])->afterCreating(function (Certificado $certificado): void {
            $certificado->forceFill(['certificado' => self::certificadoPara($certificado->emisor)])->save();
        });
    }

    public static function clave(): OpenSSLAsymmetricKey
    {
        return self::$clave ??= openssl_pkey_new([...GeneradorDeCertificados::opcionesOpenSsl(), 'private_key_bits' => 2048])
            ?: throw new RuntimeException('No se pudo generar la clave de prueba');
    }

    public static function clavePem(): string
    {
        openssl_pkey_export(self::clave(), $pem, null, GeneradorDeCertificados::opcionesOpenSsl());

        return $pem;
    }

    /**
     * Un certificado autofirmado con la clave compartida (u otra). `$sujeto`
     * permite fabricar los casos inválidos: otro CUIT, otra autoridad
     * (autofirmado, el emisor es el propio sujeto) o vencido (`$dias < 0`).
     *
     * @param  array<string, string>  $sujeto
     */
    public static function certificadoPara(Emisor $emisor, array $sujeto = [], int $dias = 730, ?OpenSSLAsymmetricKey $clave = null): string
    {
        $clave ??= self::clave();
        $opciones = [...GeneradorDeCertificados::opcionesOpenSsl(), 'digest_alg' => 'sha256'];
        $csr = self::pedido(['serialNumber' => "CUIT {$emisor->cuit}", ...$sujeto], $clave);

        $x509 = openssl_csr_sign($csr, null, $clave, $dias, $opciones);

        if ($x509 === false) {
            throw new RuntimeException('No se pudo firmar el certificado de prueba');
        }

        openssl_x509_export($x509, $pem);

        return $pem;
    }

    /** @param  array<string, string>  $sujeto */
    private static function csr(array $sujeto): string
    {
        openssl_csr_export(self::pedido($sujeto, self::clave()), $pem);

        return $pem;
    }

    /** @param  array<string, string>  $sujeto */
    private static function pedido(array $sujeto, OpenSSLAsymmetricKey $clave): OpenSSLCertificateSigningRequest
    {
        $csr = openssl_csr_new(
            ['countryName' => 'AR', 'organizationName' => 'Prueba', 'commonName' => 'facturar', ...$sujeto],
            $clave,
            [...GeneradorDeCertificados::opcionesOpenSsl(), 'digest_alg' => 'sha256'],
        );

        if (! $csr instanceof OpenSSLCertificateSigningRequest) {
            throw new RuntimeException('No se pudo generar el CSR de prueba');
        }

        return $csr;
    }
}
