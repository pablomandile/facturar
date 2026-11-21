<?php

declare(strict_types=1);

namespace App\Services\Arca;

use App\Enums\EstadoComprobante;
use App\Events\ComprobanteAutorizado;
use App\Exceptions\Arca\ComprobanteNoEmitible;
use App\Exceptions\Arca\ComprobanteRechazado;
use App\Exceptions\Arca\EmisionBloqueada;
use App\Exceptions\Arca\ErrorDeWsfe;
use App\Exceptions\Arca\ExcepcionDeArca;
use App\Exceptions\Arca\FallaDeConexion;
use App\Models\Comprobante;
use App\Support\Arca\ComprobanteConsultado;
use App\Support\Arca\ObservacionDeArca;
use App\Support\Arca\RespuestaDeCae;
use App\Support\Dinero;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Emite un comprobante: lo numera, pide el CAE y deja el resultado. Es el
 * **único** lugar que mueve un comprobante a Pendiente, Autorizado,
 * Rechazado o Error.
 *
 * El orden importa y cada paso tiene su porqué:
 *
 * 1. Validar todo lo que se puede saber sin ARCA (no gasta un número).
 * 2. Tener el ticket antes del lock: si hay que pedirlo a WSAA, que no
 *    bloquee el punto de venta mientras tanto.
 * 3. Lock por punto de venta: dos emisiones a la vez pedirían el mismo
 *    "último + 1". El UNIQUE de la tabla es la última defensa.
 * 4. Número = último autorizado en ARCA + 1, y se guarda como Pendiente
 *    **antes** de llamar, en una transacción corta: si el proceso muere en
 *    la llamada, queda constancia de qué número se mandó.
 * 5. La llamada a ARCA, fuera de toda transacción.
 * 6. El resultado. Si no hubo respuesta y el pedido pudo haber llegado, se
 *    consulta a ARCA antes de dar nada por perdido: la factura pudo haber
 *    quedado autorizada.
 */
class EmisorDeComprobantes
{
    /** "El número o la fecha no se corresponden con el próximo a autorizar". */
    public const NO_ES_EL_PROXIMO = 10016;

    public function __construct(
        private readonly ClienteWsfe $wsfe,
        private readonly ProveedorDeTickets $tickets,
        private readonly SerializadorDeFacturaC $serializador,
        private readonly RelojFiscal $reloj,
    ) {}

    /**
     * @throws ExcepcionDeArca con un mensaje para mostrar tal cual. El estado
     *                         del comprobante ya quedó guardado cuando se lanza.
     */
    public function emitir(Comprobante $comprobante): Comprobante
    {
        $comprobante->loadMissing(['emisor', 'puntoDeVenta', 'items']);
        $this->validar($comprobante);
        $this->tickets->obtener($comprobante->emisor);

        // ComprobanteAutorizado sale recién con el lock liberado: el email
        // (EnviarAlAutorizar) no tiene por qué retener el punto de venta.
        return Event::defer(function () use ($comprobante): Comprobante {
            $lock = $this->lock($comprobante);

            try {
                $lock->block((int) config('arca.espera_lock_emision_segundos'));
            } catch (LockTimeoutException) {
                throw EmisionBloqueada::enCurso();
            }

            try {
                $this->sinPendientes($comprobante);

                return $this->enviar($comprobante, reintentarSiNoEsElProximo: true);
            } finally {
                $lock->release();
            }
        }, [ComprobanteAutorizado::class]);
    }

    /**
     * Resuelve un Pendiente consultándolo a ARCA. Lo usa el scheduler para
     * los que quedaron en el aire. Devuelve el estado en que quedó, o null
     * si otra emisión tiene el punto de venta o ARCA sigue sin contestar.
     */
    public function reconciliar(Comprobante $comprobante): ?EstadoComprobante
    {
        $comprobante->loadMissing(['emisor', 'puntoDeVenta']);

        return Event::defer(function () use ($comprobante): ?EstadoComprobante {
            $lock = $this->lock($comprobante);

            if (! $lock->get()) {
                return null;
            }

            try {
                $comprobante->refresh();

                if ($comprobante->estado !== EstadoComprobante::Pendiente) {
                    return $comprobante->estado;
                }

                $this->resolverConConsulta($comprobante);

                return $comprobante->estado;
            } catch (ExcepcionDeArca $e) {
                Log::warning("No se pudo reconciliar el comprobante {$comprobante->id}: {$e->getMessage()}");

                return null;
            } finally {
                $lock->release();
            }
        }, [ComprobanteAutorizado::class]);
    }

    private function validar(Comprobante $comprobante): void
    {
        if (! $comprobante->estado->esEmitible()) {
            throw ComprobanteNoEmitible::porEstado(mb_strtolower($comprobante->estado->etiqueta()));
        }

        if ($comprobante->items->isEmpty()) {
            throw ComprobanteNoEmitible::sinItems();
        }

        $comprobante->recalcular();

        if ($comprobante->importe_total <= 0) {
            throw ComprobanteNoEmitible::totalCero();
        }

        $punto = $comprobante->puntoDeVenta;
        if (! $punto->activo || $punto->ambiente !== $comprobante->emisor->ambiente) {
            throw ComprobanteNoEmitible::puntoDeVentaInactivo();
        }

        $hoy = $this->reloj->hoy();
        if (abs($hoy->diffInDays($comprobante->fecha_emision, false)) > $comprobante->concepto->ventanaDias()) {
            throw ComprobanteNoEmitible::fueraDeVentana($comprobante->fecha_emision, $comprobante->concepto);
        }

        $tope = $comprobante->emisor->topeConsumidorFinal();
        if ($comprobante->esConsumidorFinalSinIdentificar() && $comprobante->importe_total >= $tope) {
            throw ComprobanteNoEmitible::consumidorFinalSobreElTope($tope);
        }
    }

    private function sinPendientes(Comprobante $comprobante): void
    {
        $hayPendientes = Comprobante::query()
            ->where('punto_de_venta_id', $comprobante->punto_de_venta_id)
            ->where('tipo', $comprobante->tipo->value)
            ->where('estado', EstadoComprobante::Pendiente->value)
            ->whereKeyNot($comprobante->id)
            ->exists();

        if ($hayPendientes) {
            throw EmisionBloqueada::hayPendientes();
        }
    }

    private function enviar(Comprobante $comprobante, bool $reintentarSiNoEsElProximo): Comprobante
    {
        $numero = $this->proximoNumero($comprobante);

        DB::transaction(fn () => $comprobante->forceFill([
            'estado' => EstadoComprobante::Pendiente,
            'numero' => $numero,
            'ambiente' => $comprobante->emisor->ambiente,
            'enviado_en' => now(),
            'intentos' => $comprobante->intentos + 1,
            'respuesta_arca' => null,
        ])->save());

        try {
            $respuesta = $this->wsfe->solicitarCae($comprobante->emisor, $this->serializador->serializar($comprobante), $comprobante->id);
        } catch (FallaDeConexion $e) {
            if ($e->pudoHaberLlegado) {
                return $this->resolverTrasCorte($comprobante, $e);
            }

            $this->marcarError($comprobante, $e->getMessage());

            throw $e;
        } catch (ErrorDeWsfe $e) {
            $this->marcarError($comprobante, $e->getMessage(), $e->errores);

            throw $e;
        } catch (ExcepcionDeArca $e) {
            // El ticket u otra cosa previa al envío: no llegó a ARCA.
            $this->marcarError($comprobante, $e->getMessage());

            throw $e;
        }
        // Cualquier otra excepción deja el comprobante Pendiente: el pedido
        // salió y no sabemos qué pasó. Lo resuelve el scheduler.

        if ($respuesta->autorizado()) {
            return $this->marcarAutorizado($comprobante, (string) $respuesta->cae, $respuesta->caeVencimiento, $respuesta);
        }

        // Alguien numeró en este punto de venta por fuera de la app entre la
        // consulta y el envío: se pide el número de nuevo, una sola vez.
        if ($reintentarSiNoEsElProximo && $respuesta->tieneObservacion(self::NO_ES_EL_PROXIMO)) {
            return $this->enviar($comprobante, reintentarSiNoEsElProximo: false);
        }

        $this->marcarRechazado($comprobante, $respuesta);

        throw new ComprobanteRechazado($respuesta->observaciones);
    }

    /**
     * Último autorizado en ARCA + 1. Si ARCA tiene un número **menor** al
     * último que autorizó la app, algo está mal (otro punto de venta, otro
     * ambiente, un CUIT cruzado) y no se emite.
     */
    private function proximoNumero(Comprobante $comprobante): int
    {
        $enArca = $this->wsfe->ultimoAutorizado($comprobante->emisor, $comprobante->puntoDeVenta->numero, $comprobante->tipo);

        $autorizados = Comprobante::query()
            ->where('punto_de_venta_id', $comprobante->punto_de_venta_id)
            ->where('tipo', $comprobante->tipo->value)
            ->where('estado', EstadoComprobante::Autorizado->value);

        $enLaApp = (int) $autorizados->clone()->max('numero');

        if ($enArca < $enLaApp) {
            throw EmisionBloqueada::numeracionDesincronizada($enArca, $enLaApp);
        }

        if ($enArca > $enLaApp) {
            Log::info("Punto de venta {$comprobante->punto_de_venta_id}: ARCA tiene autorizada la {$enArca} y la app la {$enLaApp}. Se emitió por fuera de la app.");
        }

        $ultimaFecha = $autorizados->clone()->max('fecha_emision');
        if (is_string($ultimaFecha) && $comprobante->fecha_emision->lt(CarbonImmutable::parse($ultimaFecha))) {
            throw ComprobanteNoEmitible::anteriorAlUltimo($comprobante->fecha_emision, CarbonImmutable::parse($ultimaFecha));
        }

        return $enArca + 1;
    }

    /**
     * Se mandó el pedido y no llegó la respuesta: se pregunta a ARCA si lo
     * tiene. Si la consulta tampoco anda, queda Pendiente para el scheduler.
     */
    private function resolverTrasCorte(Comprobante $comprobante, FallaDeConexion $corte): Comprobante
    {
        try {
            $this->resolverConConsulta($comprobante);
        } catch (ExcepcionDeArca) {
            $comprobante->forceFill(['respuesta_arca' => ['mensaje' => $corte->getMessage()]])->save();

            throw $corte;
        }

        if ($comprobante->estado !== EstadoComprobante::Autorizado) {
            throw new FallaDeConexion($corte->mensajeTecnico, pudoHaberLlegado: false);
        }

        return $comprobante;
    }

    private function resolverConConsulta(Comprobante $comprobante): void
    {
        $consultado = $this->wsfe->consultar(
            $comprobante->emisor,
            $comprobante->puntoDeVenta->numero,
            $comprobante->tipo,
            (int) $comprobante->numero,
            $comprobante->id,
        );

        if ($consultado === null) {
            $this->marcarError($comprobante, 'ARCA no recibió la factura. Podés volver a emitirla.');

            return;
        }

        if (! $this->coincide($comprobante, $consultado) || $consultado->cae === null) {
            $this->marcarError($comprobante, "ARCA tiene otra factura con el número {$comprobante->numero}. Volvé a emitir para tomar el siguiente.");

            return;
        }

        $this->marcarAutorizado($comprobante, $consultado->cae, $consultado->caeVencimiento, null);
    }

    /** ¿Lo que tiene ARCA con ese número es este comprobante? */
    private function coincide(Comprobante $comprobante, ComprobanteConsultado $consultado): bool
    {
        return $consultado->resultado === 'A'
            && Dinero::aCentavos(number_format((float) $consultado->importeTotal, 2, '.', '')) === $comprobante->importe_total
            && $consultado->tipoDocumento === $comprobante->receptor_tipo_documento->value
            && (int) $consultado->numeroDocumento === (int) $comprobante->receptor_numero_documento;
    }

    private function marcarAutorizado(Comprobante $comprobante, string $cae, ?CarbonImmutable $vencimiento, ?RespuestaDeCae $respuesta): Comprobante
    {
        $comprobante->forceFill([
            'estado' => EstadoComprobante::Autorizado,
            'cae' => $cae,
            'cae_vencimiento' => $vencimiento,
            'autorizado_en' => now(),
            'respuesta_arca' => $respuesta === null ? null : [
                'observaciones' => self::comoArray($respuesta->observaciones),
                'eventos' => self::comoArray($respuesta->eventos),
            ],
        ])->save();

        // El envío por email cuelga de acá (EnviarAlAutorizar). Un fallo del
        // correo no lanza: la factura ya quedó autorizada.
        ComprobanteAutorizado::dispatch($comprobante);

        return $comprobante;
    }

    private function marcarRechazado(Comprobante $comprobante, RespuestaDeCae $respuesta): void
    {
        // Un rechazo no consume el número: queda libre para el próximo intento.
        $comprobante->forceFill([
            'estado' => EstadoComprobante::Rechazado,
            'numero' => null,
            'respuesta_arca' => [
                'observaciones' => self::comoArray($respuesta->observaciones),
                'eventos' => self::comoArray($respuesta->eventos),
            ],
        ])->save();
    }

    /** @param  list<ObservacionDeArca>  $errores */
    private function marcarError(Comprobante $comprobante, string $mensaje, array $errores = []): void
    {
        $comprobante->forceFill([
            'estado' => EstadoComprobante::Error,
            'numero' => null,
            'respuesta_arca' => ['mensaje' => $mensaje, 'errores' => self::comoArray($errores)],
        ])->save();
    }

    private function lock(Comprobante $comprobante): Lock
    {
        return Cache::lock(sprintf(
            'arca:emision:%d:%s:%d:%d',
            $comprobante->emisor_id,
            $comprobante->emisor->ambiente->value,
            $comprobante->puntoDeVenta->numero,
            $comprobante->tipo->value,
        ), 120);
    }

    /**
     * @param  list<ObservacionDeArca>  $observaciones
     * @return list<array{codigo: int, mensaje: string}>
     */
    private static function comoArray(array $observaciones): array
    {
        return array_map(fn (ObservacionDeArca $o): array => $o->toArray(), $observaciones);
    }
}
