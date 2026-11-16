<?php

declare(strict_types=1);

namespace App\Services\Arca;

use App\Contracts\Arca\TransporteSoap;
use App\Enums\Arca\Ambiente;
use App\Enums\Arca\ResultadoLlamada;
use App\Enums\Arca\ServicioArca;
use App\Exceptions\Arca\ErrorDeWsfe;
use App\Exceptions\Arca\FallaDeConexion;
use App\Exceptions\Arca\FaultDeArca;
use App\Models\Emisor;
use App\Models\LlamadaArca;
use Closure;
use Throwable;

/**
 * Corre una llamada a ARCA y la deja registrada, salga como salga. El token
 * y el sign se tachan antes de guardar: con ellos cualquiera podría facturar
 * a nombre del emisor durante 12 horas.
 */
class RegistradorDeLlamadas
{
    /**
     * @template T
     *
     * @param  Closure(): T  $llamada
     * @return T
     */
    public function registrar(
        Emisor $emisor,
        ServicioArca $servicio,
        Ambiente $ambiente,
        string $metodo,
        TransporteSoap $transporte,
        Closure $llamada,
        ?int $comprobanteId = null,
    ): mixed {
        $inicio = hrtime(true);
        $resultado = ResultadoLlamada::Ok;
        $codigo = null;
        $mensaje = null;

        try {
            return $llamada();
        } catch (Throwable $e) {
            [$resultado, $codigo] = $this->clasificar($e);
            $mensaje = $e instanceof FallaDeConexion ? $e->mensajeTecnico : $e->getMessage();

            throw $e;
        } finally {
            LlamadaArca::query()->create([
                'emisor_id' => $emisor->id,
                'comprobante_id' => $comprobanteId,
                'ambiente' => $ambiente,
                'servicio' => $servicio,
                'metodo' => $metodo,
                'pedido' => self::tachar($transporte->ultimoPedido()),
                'respuesta' => self::tachar($transporte->ultimaRespuesta()),
                'resultado' => $resultado,
                'codigo' => $codigo,
                'mensaje' => $mensaje,
                'duracion_ms' => (int) ((hrtime(true) - $inicio) / 1_000_000),
            ]);
        }
    }

    /**
     * Tacha token y sign en cualquier forma en que aparezcan: como elementos
     * del pedido a WSFE (<Token>, <ar:Sign>) y escapados adentro del
     * loginCmsReturn de WSAA (&lt;token&gt;).
     */
    public static function tachar(?string $xml): ?string
    {
        if ($xml === null) {
            return null;
        }

        $xml = (string) preg_replace('#(<([\w]+:)?(token|sign)>)[^<]*(</([\w]+:)?(token|sign)>)#i', '$1***$4', $xml);

        return (string) preg_replace('#(&lt;(token|sign)&gt;).*?(&lt;/(token|sign)&gt;)#is', '$1***$3', $xml);
    }

    /** @return array{ResultadoLlamada, string|null} */
    private function clasificar(Throwable $e): array
    {
        return match (true) {
            $e instanceof FallaDeConexion => [$e->pudoHaberLlegado ? ResultadoLlamada::Timeout : ResultadoLlamada::SinConexion, null],
            $e instanceof FaultDeArca => [ResultadoLlamada::Fault, $e->codigo],
            $e instanceof ErrorDeWsfe => [ResultadoLlamada::ErrorDeArca, (string) $e->primerCodigo()],
            default => [ResultadoLlamada::Fault, class_basename($e)],
        };
    }
}
