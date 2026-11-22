<?php

declare(strict_types=1);

namespace App\Services\Arca\Demo;

use App\Contracts\Arca\TransporteSoap;
use App\Enums\Arca\ServicioArca;
use App\Enums\EstadoComprobante;
use App\Exceptions\Arca\FaultDeArca;
use App\Models\Comprobante;
use App\Services\Arca\RelojFiscal;

/**
 * Contesta como ARCA, con la forma exacta que entrega SoapClient, sin salir a
 * la red. Autoriza todo lo que se le pide con un CAE inventado: en la demo la
 * cuenta es de homologación, así que el PDF sale con "sin validez fiscal".
 *
 * El último autorizado lo saca de la base: es lo que ARCA sabría.
 */
class TransporteDemo implements TransporteSoap
{
    private ?string $pedido = null;

    private ?string $respuesta = null;

    public function __construct(private readonly ServicioArca $servicio) {}

    public function llamar(string $metodo, array $parametros = []): object
    {
        $this->pedido = $this->comoTexto($metodo, $parametros);

        $respuesta = match ($this->servicio) {
            ServicioArca::Wsaa => $this->wsaa($metodo),
            ServicioArca::Wsfe => $this->wsfe($metodo, $parametros),
        };

        $this->respuesta = (string) json_encode($respuesta, JSON_UNESCAPED_UNICODE);

        return $respuesta;
    }

    public function ultimoPedido(): ?string
    {
        return $this->pedido;
    }

    public function ultimaRespuesta(): ?string
    {
        return $this->respuesta;
    }

    private function wsaa(string $metodo): object
    {
        if ($metodo !== 'loginCms') {
            throw new FaultDeArca('ns1:demo', "La demo no conoce {$metodo}.");
        }

        $ahora = (new RelojFiscal)->ahora();
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<loginTicketResponse version="1.0"><header>'
            .'<uniqueId>1</uniqueId>'
            .'<generationTime>'.$ahora->format('Y-m-d\TH:i:s.vP').'</generationTime>'
            .'<expirationTime>'.$ahora->addHours(12)->format('Y-m-d\TH:i:s.vP').'</expirationTime>'
            .'</header><credentials><token>DEMO</token><sign>DEMO</sign></credentials></loginTicketResponse>';

        return (object) ['loginCmsReturn' => $xml];
    }

    /** @param  array<string, mixed>  $parametros */
    private function wsfe(string $metodo, array $parametros): object
    {
        return match ($metodo) {
            'FEDummy' => (object) ['FEDummyResult' => (object) ['AppServer' => 'OK', 'DbServer' => 'OK', 'AuthServer' => 'OK']],
            'FECompUltimoAutorizado' => (object) ['FECompUltimoAutorizadoResult' => (object) [
                'PtoVta' => (int) $parametros['PtoVta'],
                'CbteTipo' => (int) $parametros['CbteTipo'],
                'CbteNro' => $this->ultimoAutorizado((string) $parametros['Auth']['Cuit'], (int) $parametros['PtoVta'], (int) $parametros['CbteTipo']),
            ]],
            'FECAESolicitar' => $this->cae($parametros),
            'FEParamGetPtosVenta' => (object) ['FEParamGetPtosVentaResult' => (object) ['ResultGet' => (object) [
                'PtoVenta' => [(object) ['Nro' => 1, 'EmisionTipo' => 'CAE - Demo', 'Bloqueado' => 'N', 'FchBaja' => 'NULL']],
            ]]],
            // Lo único que se consulta es un comprobante que quedó en el aire
            // tras un corte, y la demo no tiene cortes: no hay nada que encontrar.
            default => (object) ["{$metodo}Result" => (object) [
                'Errors' => (object) ['Err' => [(object) ['Code' => 602, 'Msg' => 'Sin Resultados: la demo no guarda comprobantes en ARCA.']]],
            ]],
        };
    }

    private function ultimoAutorizado(string $cuit, int $puntoDeVenta, int $tipo): int
    {
        return (int) Comprobante::query()
            ->whereHas('emisor', fn ($q) => $q->where('cuit', $cuit))
            ->whereHas('puntoDeVenta', fn ($q) => $q->where('numero', $puntoDeVenta))
            ->where('tipo', $tipo)
            ->where('estado', EstadoComprobante::Autorizado->value)
            ->max('numero');
    }

    /** @param  array<string, mixed>  $parametros */
    private function cae(array $parametros): object
    {
        /** @var array{FeCabReq: array<string, mixed>, FeDetReq: array{FECAEDetRequest: list<array<string, mixed>>}} $pedido */
        $pedido = $parametros['FeCAEReq'];
        $detalle = $pedido['FeDetReq']['FECAEDetRequest'][0];
        $reloj = new RelojFiscal;

        return (object) ['FECAESolicitarResult' => (object) [
            'FeCabResp' => (object) [
                'Cuit' => $parametros['Auth']['Cuit'] ?? 0,
                'PtoVta' => $pedido['FeCabReq']['PtoVta'],
                'CbteTipo' => $pedido['FeCabReq']['CbteTipo'],
                'FchProceso' => $reloj->ahora()->format('YmdHis'),
                'CantReg' => 1,
                'Resultado' => 'A',
                'Reproceso' => 'N',
            ],
            'FeDetResp' => (object) ['FECAEDetResponse' => [(object) [
                'Concepto' => $detalle['Concepto'],
                'DocTipo' => $detalle['DocTipo'],
                'DocNro' => $detalle['DocNro'],
                'CbteDesde' => $detalle['CbteDesde'],
                'CbteHasta' => $detalle['CbteHasta'],
                'CbteFch' => $detalle['CbteFch'],
                'Resultado' => 'A',
                'CAE' => self::caeInventado(),
                'CAEFchVto' => $reloj->ymd($reloj->hoy()->addDays(10)),
            ]]],
        ]];
    }

    /** 14 dígitos, como un CAE de verdad. */
    public static function caeInventado(): string
    {
        return '7'.str_pad((string) random_int(0, 9_999_999_999_999), 13, '0', STR_PAD_LEFT);
    }

    /** @param  array<string, mixed>  $parametros */
    private function comoTexto(string $metodo, array $parametros): string
    {
        // Sin token ni sign, como el registro de las llamadas reales.
        unset($parametros['Auth'], $parametros['in0']);

        return $metodo.' '.json_encode($parametros, JSON_UNESCAPED_UNICODE);
    }
}
