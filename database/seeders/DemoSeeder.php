<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Arca\Ambiente;
use App\Enums\Arca\Concepto;
use App\Enums\Arca\CondicionIvaReceptor;
use App\Enums\Arca\EstadoCertificado;
use App\Enums\Arca\TipoComprobante;
use App\Enums\Arca\TipoDocumento;
use App\Enums\EstadoComprobante;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Emisor;
use App\Models\PuntoDeVenta;
use App\Models\User;
use App\Services\Arca\Demo\TransporteDemo;
use App\Services\Arca\GeneradorDeCertificados;
use App\Services\Arca\RelojFiscal;
use App\Services\Facturas\TextoDelEmail;
use App\Support\Arca\Cuit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * La monotributista inventada de la demo (APP_DEMO=true). La corre
 * `demo:reset` todas las noches sobre una base vacía.
 *
 * - **Lucía Fernández**, diseñadora web, en homologación: todo PDF sale con
 *   "sin validez fiscal" y ARCA es la simulada (FabricaDeTransportesDemo).
 * - Un certificado "vigente" autofirmado (la ARCA simulada no lo mira), el
 *   punto de venta 1 y tres clientes: uno con descripción habitual y alias,
 *   uno con alias, y una persona con DNI.
 * - Seis meses de facturas autorizadas con sus envíos por email, una rechazada
 *   y el borrador del mes, listo para emitir.
 *
 * Sin factories: usan Faker, que no está en producción (`--no-dev`). Las
 * fechas son relativas a hoy, para que la demo siempre esté al día.
 */
class DemoSeeder extends Seeder
{
    private const HONORARIOS = 85_000_000;

    private const HONORARIOS_EN_LETRAS = 'Ochocientos cincuenta mil pesos con cero centavos';

    private const SOPORTE = 18_000_000;

    private RelojFiscal $reloj;

    private int $numero = 0;

    public function run(GeneradorDeCertificados $generador, TextoDelEmail $texto): void
    {
        $this->reloj = new RelojFiscal;
        $hoy = $this->reloj->hoy();

        $usuario = User::query()->create([
            'name' => 'Lucía Fernández',
            'email' => (string) config('demo.email'),
            'password' => (string) config('demo.password'),
        ]);
        $usuario->forceFill(['email_verified_at' => now()])->save();
        $usuario->aceptarTerminos();

        $emisor = new Emisor;
        $emisor->forceFill([
            'usuario_id' => $usuario->id,
            'cuit' => self::cuit('2730123456'),
            'razon_social' => 'Lucía Fernández',
            'domicilio_fiscal' => 'Av. Corrientes 1234, piso 5 «B», CABA',
            'ingresos_brutos' => 'Exento',
            'inicio_actividades' => '2021-03-01',
            'ambiente' => Ambiente::Homologacion,
            'plantilla_email' => "Hola, {alias}:\n\nTe envío la factura C {numero} del {fecha}, por {importe}, correspondiente a {mes} {anio}. Vence el {vencimiento}.\n\nCualquier consulta, respondé este mail.\n\nSaludos,\n{razon_social}",
        ])->save();

        $this->certificado($generador, $emisor);

        $punto = new PuntoDeVenta(['numero' => 1, 'descripcion' => 'Casa central', 'activo' => true]);
        $punto->forceFill(['emisor_id' => $emisor->id, 'ambiente' => Ambiente::Homologacion])->save();

        $ministerio = $this->cliente($emisor, [
            'tipo_documento' => TipoDocumento::Cuit,
            'numero_documento' => self::cuit('3099912345'),
            'nombre' => 'Ministerio de Cultura de la Provincia',
            'alias' => 'Ministerio',
            'condicion_iva' => CondicionIvaReceptor::Exento,
            'domicilio' => 'Calle 7 N° 1500, La Plata',
            'email' => 'pagos@ministerio.demo',
            'plantilla_descripcion' => "Honorarios por diseño y mantenimiento del sitio web, período comprendido entre el {desde} y el {hasta}.\n{importe_en_letras}.",
        ]);
        $estudio = $this->cliente($emisor, [
            'tipo_documento' => TipoDocumento::Cuit,
            'numero_documento' => self::cuit('3071234567'),
            'nombre' => 'Estudio Contable Ríos SRL',
            'alias' => 'Estudio Ríos',
            'condicion_iva' => CondicionIvaReceptor::ResponsableInscripto,
            'domicilio' => 'Av. Santa Fe 2450, CABA',
            'email' => 'administracion@estudiorios.demo',
            'plantilla_descripcion' => null,
        ]);
        $this->cliente($emisor, [
            'tipo_documento' => TipoDocumento::Dni,
            'numero_documento' => '33444555',
            'nombre' => 'Martín Gómez',
            'alias' => null,
            'condicion_iva' => CondicionIvaReceptor::ConsumidorFinal,
            'domicilio' => null,
            'email' => null,
            'plantilla_descripcion' => null,
        ]);

        // Seis meses hacia atrás, en orden: la numeración sigue a las fechas.
        foreach (range(6, 1) as $mesesAtras) {
            $periodo = $hoy->subMonthsNoOverflow($mesesAtras)->startOfMonth();
            $emision = $this->noDespuesDeAyer($periodo->addMonthNoOverflow()->addDays(2));

            $factura = $this->autorizada($punto, $ministerio, $emision, $periodo, self::HONORARIOS,
                "Honorarios por diseño y mantenimiento del sitio web, período comprendido entre el {$periodo->format('d/m/Y')} y el {$periodo->endOfMonth()->format('d/m/Y')}.\n".self::HONORARIOS_EN_LETRAS.'.');
            $factura->envios()->create([
                'destinatarios' => [$ministerio->email],
                'con_copia' => true,
                'texto' => $texto->para($factura),
                'enviado' => true,
                'created_at' => $emision->setTime(13, 15)->shiftTimezone('America/Argentina/Buenos_Aires')->utc(),
            ]);

            if ($mesesAtras % 2 === 0) {
                $this->autorizada($punto, $estudio, $this->noDespuesDeAyer($emision->addDays(5)), $periodo, self::SOPORTE, 'Soporte técnico mensual y actualización de la web del estudio.');
            }
        }

        $this->rechazada($punto, $hoy->subDays(3));
        $this->borradorDelMes($punto, $ministerio, $hoy);
    }

    private function certificado(GeneradorDeCertificados $generador, Emisor $emisor): void
    {
        $certificado = $generador->generar($emisor, Ambiente::Homologacion, 'facturar-demo');
        $clave = openssl_pkey_get_private($certificado->clave_privada);
        $opciones = [...GeneradorDeCertificados::opcionesOpenSsl(), 'digest_alg' => 'sha256'];
        $x509 = $clave === false ? false : openssl_csr_sign($certificado->csr, null, $clave, 730, $opciones);

        if ($x509 === false || ! openssl_x509_export($x509, $pem)) {
            throw new RuntimeException('No se pudo firmar el certificado de la demo.');
        }

        $certificado->forceFill([
            'certificado' => $pem,
            'valido_desde' => now()->subMonth(),
            'valido_hasta' => now()->addYears(2),
            'estado' => EstadoCertificado::Vigente,
        ])->save();
    }

    /** @param  array<string, mixed>  $datos */
    private function cliente(Emisor $emisor, array $datos): Cliente
    {
        $cliente = new Cliente;
        $cliente->forceFill(['emisor_id' => $emisor->id, ...$datos])->save();

        return $cliente;
    }

    private function autorizada(PuntoDeVenta $punto, Cliente $cliente, CarbonImmutable $emision, CarbonImmutable $periodo, int $importe, string $descripcion): Comprobante
    {
        $comprobante = $this->comprobante($punto, $cliente, $emision, $importe, [
            'concepto' => Concepto::Servicios,
            'fecha_servicio_desde' => $periodo,
            'fecha_servicio_hasta' => $periodo->endOfMonth(),
            'fecha_vto_pago' => $emision->addDays(15),
            'estado' => EstadoComprobante::Autorizado,
            'numero' => ++$this->numero,
            'cae' => TransporteDemo::caeInventado(),
            'cae_vencimiento' => $emision->addDays(10),
            'enviado_en' => $emision->setTime(13, 10)->shiftTimezone('America/Argentina/Buenos_Aires')->utc(),
            'autorizado_en' => $emision->setTime(13, 10)->shiftTimezone('America/Argentina/Buenos_Aires')->utc(),
            'intentos' => 1,
            'respuesta_arca' => ['observaciones' => [], 'eventos' => []],
        ]);
        $this->item($comprobante, $descripcion, $importe);

        return $comprobante;
    }

    /** Una venta a consumidor final que ARCA rechazó: el número no se consumió. */
    private function rechazada(PuntoDeVenta $punto, CarbonImmutable $emision): void
    {
        $comprobante = $this->comprobante($punto, null, $emision, 4_500_000, [
            'concepto' => Concepto::Productos,
            'estado' => EstadoComprobante::Rechazado,
            'intentos' => 1,
            'enviado_en' => now()->subDays(3),
            'respuesta_arca' => [
                'observaciones' => [['codigo' => 10016, 'mensaje' => 'El número de comprobante informado no es el próximo a autorizar. (Demo: editala y volvé a emitirla.)']],
                'eventos' => [],
            ],
        ]);
        $this->item($comprobante, 'Kit de papelería con la identidad de marca (tarjetas, hojas y sobres).', 4_500_000);
    }

    /** La factura de este mes al Ministerio, guardada y lista para emitir. */
    private function borradorDelMes(PuntoDeVenta $punto, Cliente $cliente, CarbonImmutable $hoy): void
    {
        $periodo = $hoy->startOfMonth();
        $comprobante = $this->comprobante($punto, $cliente, $hoy, self::HONORARIOS, [
            'concepto' => Concepto::Servicios,
            'fecha_servicio_desde' => $periodo,
            'fecha_servicio_hasta' => $periodo->endOfMonth(),
            'fecha_vto_pago' => $hoy->addDays(15),
            'enviar_al_autorizar' => false,
        ]);
        $this->item($comprobante, "Honorarios por diseño y mantenimiento del sitio web, período comprendido entre el {$periodo->format('d/m/Y')} y el {$periodo->endOfMonth()->format('d/m/Y')}.\n".self::HONORARIOS_EN_LETRAS.'.', self::HONORARIOS);
    }

    /** @param  array<string, mixed>  $estado */
    private function comprobante(PuntoDeVenta $punto, ?Cliente $cliente, CarbonImmutable $emision, int $importe, array $estado): Comprobante
    {
        $comprobante = new Comprobante;
        $comprobante->forceFill([
            'emisor_id' => $punto->emisor_id,
            'punto_de_venta_id' => $punto->id,
            'ambiente' => Ambiente::Homologacion,
            'tipo' => TipoComprobante::FacturaC,
            'fecha_emision' => $emision,
            'cliente_id' => $cliente?->id,
            'receptor_tipo_documento' => $cliente === null ? TipoDocumento::ConsumidorFinal : $cliente->tipo_documento,
            'receptor_numero_documento' => $cliente === null ? '0' : $cliente->numero_documento,
            'receptor_nombre' => $cliente === null ? 'Consumidor Final' : $cliente->nombre,
            'receptor_condicion_iva' => $cliente === null ? CondicionIvaReceptor::ConsumidorFinal : $cliente->condicion_iva,
            'receptor_domicilio' => $cliente?->domicilio,
            'importe_neto' => $importe,
            'importe_total' => $importe,
            ...$estado,
        ])->save();

        return $comprobante;
    }

    private function item(Comprobante $comprobante, string $descripcion, int $importe): void
    {
        $comprobante->items()->create([
            'orden' => 1,
            'descripcion' => $descripcion,
            'cantidad' => '1',
            'unidad' => null,
            'precio_unitario' => $importe,
            'importe' => $importe,
        ]);
    }

    /** Lo que ya pasó cae a más tardar ayer: el reset corre de madrugada. */
    private function noDespuesDeAyer(CarbonImmutable $fecha): CarbonImmutable
    {
        $ayer = $this->reloj->hoy()->subDay();

        return $fecha->greaterThan($ayer) ? $ayer : $fecha;
    }

    private static function cuit(string $diezDigitos): string
    {
        return Cuit::conVerificador($diezDigitos) ?? throw new RuntimeException("No hay verificador para {$diezDigitos}.");
    }
}
