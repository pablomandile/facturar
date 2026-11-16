<?php

declare(strict_types=1);

namespace App\Services\Arca;

use App\Contracts\Arca\FabricaDeTransportes;
use App\Enums\Arca\ServicioArca;
use App\Enums\Arca\TipoComprobante;
use App\Exceptions\Arca\ErrorDeWsfe;
use App\Models\Emisor;
use App\Support\Arca\ComprobanteConsultado;
use App\Support\Arca\EstadoDelServidor;
use App\Support\Arca\RespuestaDeCae;
use UnexpectedValueException;

/**
 * WSFEv1: facturación electrónica. Cada método arma el pedido, lo manda con
 * el ticket del emisor, lo deja registrado y devuelve la respuesta ya leída.
 *
 * Los errores de WSFE vienen dentro de la respuesta (Errors), no como
 * SOAP Fault: `llamar()` los convierte en ErrorDeWsfe para que ningún
 * método los pase por alto.
 */
class ClienteWsfe
{
    /** "No existen datos en nuestros registros": una consulta sin resultados. */
    public const SIN_RESULTADOS = 602;

    public function __construct(
        private readonly FabricaDeTransportes $fabrica,
        private readonly ProveedorDeTickets $tickets,
        private readonly RegistradorDeLlamadas $registrador,
    ) {}

    /** ¿Están arriba los servidores? No necesita ticket. */
    public function dummy(Emisor $emisor): EstadoDelServidor
    {
        $resultado = $this->llamar($emisor, 'FEDummy', [], conTicket: false);

        return new EstadoDelServidor(
            aplicacion: (string) ($resultado->AppServer ?? ''),
            baseDeDatos: (string) ($resultado->DbServer ?? ''),
            autenticacion: (string) ($resultado->AuthServer ?? ''),
        );
    }

    /** El número del último comprobante autorizado (0 si no hay ninguno). */
    public function ultimoAutorizado(Emisor $emisor, int $puntoDeVenta, TipoComprobante $tipo): int
    {
        $resultado = $this->llamar($emisor, 'FECompUltimoAutorizado', [
            'PtoVta' => $puntoDeVenta,
            'CbteTipo' => $tipo->value,
        ]);

        return (int) ($resultado->CbteNro ?? 0);
    }

    /**
     * Pide el CAE para un comprobante ya serializado (ver
     * `SerializadorDeFacturaC`). Un rechazo no es una excepción: viene como
     * Resultado R con sus observaciones.
     *
     * @param  array<string, mixed>  $pedido  el FeCAEReq
     */
    public function solicitarCae(Emisor $emisor, array $pedido, ?int $comprobanteId = null): RespuestaDeCae
    {
        $resultado = $this->llamar($emisor, 'FECAESolicitar', ['FeCAEReq' => $pedido], comprobanteId: $comprobanteId);

        $detalle = LectorDeRespuestasWsfe::lista($resultado->FeDetResp->FECAEDetResponse ?? null)[0]
            ?? throw new UnexpectedValueException('FECAESolicitar no devolvió el detalle del comprobante.');
        $vencimiento = trim((string) ($detalle->CAEFchVto ?? ''));
        $cae = trim((string) ($detalle->CAE ?? ''));

        return new RespuestaDeCae(
            resultado: (string) ($detalle->Resultado ?? $resultado->FeCabResp->Resultado ?? ''),
            cae: $cae === '' ? null : $cae,
            caeVencimiento: $vencimiento === '' ? null : (new RelojFiscal)->desdeYmd($vencimiento),
            observaciones: LectorDeRespuestasWsfe::observaciones($detalle),
            eventos: LectorDeRespuestasWsfe::eventos($resultado),
        );
    }

    /** Un comprobante como lo tiene ARCA; null si no existe. */
    public function consultar(Emisor $emisor, int $puntoDeVenta, TipoComprobante $tipo, int $numero, ?int $comprobanteId = null): ?ComprobanteConsultado
    {
        try {
            $resultado = $this->llamar($emisor, 'FECompConsultar', [
                'FeCompConsReq' => ['CbteTipo' => $tipo->value, 'CbteNro' => $numero, 'PtoVta' => $puntoDeVenta],
            ], comprobanteId: $comprobanteId);
        } catch (ErrorDeWsfe $error) {
            if ($error->tieneCodigo(self::SIN_RESULTADOS)) {
                return null;
            }

            throw $error;
        }

        $comprobante = $resultado->ResultGet ?? throw new UnexpectedValueException('FECompConsultar no devolvió ResultGet.');
        $reloj = new RelojFiscal;
        $vencimiento = (string) ($comprobante->FchVto ?? '');

        return new ComprobanteConsultado(
            puntoDeVenta: (int) $comprobante->PtoVta,
            tipo: (int) $comprobante->CbteTipo,
            numero: (int) $comprobante->CbteDesde,
            fecha: $reloj->desdeYmd((string) $comprobante->CbteFch),
            importeTotal: (string) $comprobante->ImpTotal,
            resultado: (string) ($comprobante->Resultado ?? ''),
            cae: isset($comprobante->CodAutorizacion) ? (string) $comprobante->CodAutorizacion : null,
            caeVencimiento: $vencimiento === '' ? null : $reloj->desdeYmd($vencimiento),
            tipoDocumento: (int) $comprobante->DocTipo,
            numeroDocumento: (string) $comprobante->DocNro,
        );
    }

    /**
     * Los puntos de venta Web Services que ARCA tiene para el emisor. En
     * homologación suele venir vacío (602): ahí no se dan de alta.
     *
     * @return list<array{numero: int, tipo: string, bloqueado: bool, baja: string|null}>
     */
    public function puntosDeVenta(Emisor $emisor): array
    {
        try {
            $resultado = $this->llamar($emisor, 'FEParamGetPtosVenta');
        } catch (ErrorDeWsfe $error) {
            if ($error->tieneCodigo(self::SIN_RESULTADOS)) {
                return [];
            }

            throw $error;
        }

        return array_map(fn (\stdClass $punto): array => [
            'numero' => (int) $punto->Nro,
            'tipo' => (string) ($punto->EmisionTipo ?? ''),
            'bloqueado' => ($punto->Bloqueado ?? 'N') === 'S',
            'baja' => isset($punto->FchBaja) && $punto->FchBaja !== 'NULL' ? (string) $punto->FchBaja : null,
        ], LectorDeRespuestasWsfe::lista($resultado->ResultGet->PtoVenta ?? null));
    }

    /**
     * Una tabla de parámetros de ARCA (tipos de comprobante, de documento,
     * condiciones de IVA...) como pares id → descripción.
     *
     * @param  array<string, mixed>  $parametros
     * @return array<int, string>
     */
    public function parametros(Emisor $emisor, string $metodo, string $nodo, array $parametros = []): array
    {
        $resultado = $this->llamar($emisor, $metodo, $parametros);
        $tabla = [];

        foreach (LectorDeRespuestasWsfe::lista($resultado->ResultGet->{$nodo} ?? null) as $fila) {
            $tabla[(int) $fila->Id] = (string) $fila->Desc;
        }

        ksort($tabla);

        return $tabla;
    }

    /**
     * @param  array<string, mixed>  $parametros
     *
     * @throws ErrorDeWsfe si la respuesta trae Errors
     */
    private function llamar(Emisor $emisor, string $metodo, array $parametros = [], bool $conTicket = true, ?int $comprobanteId = null): object
    {
        if ($conTicket) {
            $ticket = $this->tickets->obtener($emisor, ServicioArca::Wsfe);
            $parametros = ['Auth' => ['Token' => $ticket->token, 'Sign' => $ticket->sign, 'Cuit' => $emisor->cuit], ...$parametros];
        }

        $transporte = $this->fabrica->para(ServicioArca::Wsfe, $emisor->ambiente);

        return $this->registrador->registrar(
            $emisor,
            ServicioArca::Wsfe,
            $emisor->ambiente,
            $metodo,
            $transporte,
            function () use ($transporte, $metodo, $parametros): object {
                $respuesta = $transporte->llamar($metodo, $parametros);
                $resultado = $respuesta->{"{$metodo}Result"} ?? throw new UnexpectedValueException("{$metodo} no devolvió {$metodo}Result.");

                $errores = LectorDeRespuestasWsfe::errores($resultado);
                if ($errores !== []) {
                    throw new ErrorDeWsfe($errores);
                }

                return $resultado;
            },
            $comprobanteId,
        );
    }
}
