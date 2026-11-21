<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Arca\Ambiente;
use App\Enums\Arca\Concepto;
use App\Enums\EstadoComprobante;
use App\Exceptions\Arca\ExcepcionDeArca;
use App\Http\Requests\ComprobanteGuardarRequest;
use App\Http\Requests\EnviarComprobanteRequest;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\ComprobanteItem;
use App\Models\Emisor;
use App\Models\EnvioDeComprobante;
use App\Models\PuntoDeVenta;
use App\Models\User;
use App\Services\Arca\EmisorDeComprobantes;
use App\Services\Arca\RelojFiscal;
use App\Services\ArmadorDeComprobantes;
use App\Services\Facturas\EnviadorDeFacturas;
use App\Services\Facturas\TextoDelEmail;
use App\Support\Dinero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las facturas: borradores, emisión y consulta. La emisión es síncrona: el
 * CAE vuelve en el mismo request (ver EmisorDeComprobantes).
 */
class ComprobanteController extends Controller
{
    private const POR_PAGINA = 20;

    public function __construct(
        private readonly ArmadorDeComprobantes $armador,
        private readonly EmisorDeComprobantes $emisorDeComprobantes,
        private readonly EnviadorDeFacturas $enviador,
        private readonly TextoDelEmail $textoDelEmail,
    ) {}

    public function index(Request $peticion): Response
    {
        $emisor = $this->emisorDe($peticion);
        $estado = EstadoComprobante::tryFrom($peticion->string('estado')->toString());

        $comprobantes = Comprobante::query()
            ->with('puntoDeVenta')
            ->where('emisor_id', $emisor->id)
            ->where('ambiente', $emisor->ambiente->value)
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado?->value))
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->paginate(self::POR_PAGINA)
            ->withQueryString()
            ->through(fn (Comprobante $comprobante): array => [
                'id' => $comprobante->id,
                'numero' => $comprobante->numeroCompleto(),
                'tiene_numero' => $comprobante->numero !== null,
                'fecha' => $comprobante->fecha_emision->toDateString(),
                'receptor' => $comprobante->receptor_nombre,
                'total' => Dinero::formatear($comprobante->importe_total),
                'estado' => $comprobante->estado->value,
                'estado_etiqueta' => $comprobante->estado->etiqueta(),
            ]);

        return Inertia::render('comprobantes/Index', [
            'comprobantes' => $comprobantes,
            'estado' => $estado?->value,
            'estados' => array_map(
                fn (EstadoComprobante $e): array => ['value' => $e->value, 'etiqueta' => $e->etiqueta()],
                EstadoComprobante::cases(),
            ),
            'ambiente' => $emisor->ambiente->etiqueta(),
        ]);
    }

    public function create(Request $peticion): Response
    {
        $emisor = $this->emisorDe($peticion);

        return Inertia::render('comprobantes/Formulario', [
            ...$this->datosDelFormulario($emisor),
            'comprobante' => null,
            'base' => null,
            'duplicadoDe' => null,
            'clienteSugerido' => $this->clienteSugerido($emisor, $peticion->integer('cliente')),
        ]);
    }

    /**
     * Con qué cliente arranca una factura nueva: el que se pidió
     * (`?cliente=`, desde el listado de clientes), si no el de la última
     * factura, y si no el único cliente que haya. Así la descripción habitual
     * ya aparece completa al abrir el formulario. Sin ninguno, consumidor final.
     */
    private function clienteSugerido(Emisor $emisor, int $pedido): ?int
    {
        $clientes = Cliente::query()->where('emisor_id', $emisor->id);

        if ($pedido > 0 && (clone $clientes)->whereKey($pedido)->exists()) {
            return $pedido;
        }

        $ultimo = Comprobante::query()
            ->where('emisor_id', $emisor->id)
            ->whereIn('cliente_id', (clone $clientes)->select('id'))
            ->latest('id')
            ->value('cliente_id');

        if ($ultimo !== null) {
            return (int) $ultimo;
        }

        return (clone $clientes)->count() === 1 ? (int) (clone $clientes)->value('id') : null;
    }

    public function store(ComprobanteGuardarRequest $peticion): RedirectResponse
    {
        $comprobante = $this->armador->guardar($this->emisorDe($peticion), $peticion->datos());

        if ($peticion->boolean('emitir')) {
            return $this->emitirYAvisar($comprobante);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Se guardó el borrador.']);

        return to_route('comprobantes.show', $comprobante);
    }

    public function show(Comprobante $comprobante): Response
    {
        $this->authorize('view', $comprobante);
        $comprobante->load(['items', 'puntoDeVenta', 'emisor.usuario', 'cliente', 'envios']);
        $autorizado = $comprobante->estado === EstadoComprobante::Autorizado;

        return Inertia::render('comprobantes/Detalle', [
            'comprobante' => $this->detalle($comprobante),
            'puede' => [
                'editar' => $comprobante->estado->esEditable(),
                'emitir' => $comprobante->estado->esEmitible(),
                'borrar' => $comprobante->estado->esEditable(),
                'enviar' => $autorizado,
            ],
            'email' => $autorizado ? $this->datosDelEmail($comprobante) : null,
        ]);
    }

    public function edit(Request $peticion, Comprobante $comprobante): Response
    {
        $this->authorize('update', $comprobante);
        $comprobante->load('items');

        return Inertia::render('comprobantes/Formulario', [
            ...$this->datosDelFormulario($this->emisorDe($peticion)),
            'comprobante' => ['id' => $comprobante->id, ...$this->editable($comprobante)],
            'base' => null,
            'duplicadoDe' => null,
        ]);
    }

    /**
     * Una factura nueva armada a partir de otra, sin guardarla: abre el
     * formulario con el mismo cliente, los mismos ítems y el período corrido
     * al mes que toca. Nada queda en la base hasta que se toca Guardar o Emitir.
     *
     * Sirve desde cualquier estado, también desde una autorizada: es el caso
     * típico, la factura del mes pasado.
     */
    public function duplicar(Request $peticion, Comprobante $comprobante): Response
    {
        $this->authorize('view', $comprobante);
        $comprobante->load('items');

        $emisor = $this->emisorDe($peticion);
        $datos = $this->datosDelFormulario($emisor);
        $hoy = (new RelojFiscal)->hoy();
        $base = $this->editable($comprobante);

        // El cliente, solo si sigue existiendo: si lo borraron, que se elija.
        if ($base['cliente_id'] !== null && ! Cliente::query()->whereKey($base['cliente_id'])->exists()) {
            $base['cliente_id'] = null;
        }

        // Puede venir de otro ambiente, o de un punto de venta dado de baja.
        $puntos = array_column($datos['puntosDeVenta'], 'id');
        if (! in_array($base['punto_de_venta_id'], $puntos, true)) {
            $base['punto_de_venta_id'] = $puntos[0] ?? null;
        }

        /*
         * Todo se corre lo mismo que pasó desde la factura original: la de
         * octubre emitida en noviembre, duplicada en diciembre, factura
         * noviembre. El vencimiento conserva los días que daba de plazo.
         */
        $meses = ($hoy->year * 12 + $hoy->month) - ($comprobante->fecha_emision->year * 12 + $comprobante->fecha_emision->month);
        $base['fecha_emision'] = $hoy->toDateString();
        [$base['fecha_servicio_desde'], $base['fecha_servicio_hasta']] = $this->periodoCorrido($comprobante, $meses);
        $base['fecha_vto_pago'] = $comprobante->fecha_vto_pago === null
            ? null
            : $hoy->addDays((int) $comprobante->fecha_emision->diffInDays($comprobante->fecha_vto_pago, false))->toDateString();

        return Inertia::render('comprobantes/Formulario', [
            ...$datos,
            'comprobante' => null,
            'base' => $base,
            // Completa la frase "Copia de …" del formulario.
            'duplicadoDe' => $comprobante->numero !== null ? "la factura {$comprobante->numeroCompleto()}" : 'un borrador',
        ]);
    }

    /**
     * Un período de mes completo sigue siendo de mes completo (octubre →
     * noviembre, aunque tengan distinta cantidad de días); cualquier otro se
     * corre fecha por fecha.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function periodoCorrido(Comprobante $comprobante, int $meses): array
    {
        $desde = $comprobante->fecha_servicio_desde;
        $hasta = $comprobante->fecha_servicio_hasta;

        if ($desde === null || $hasta === null) {
            return [null, null];
        }

        $mesCompleto = $desde->day === 1 && $hasta->isSameDay($desde->endOfMonth());

        if ($mesCompleto) {
            $mes = $desde->addMonthsNoOverflow($meses);

            return [$mes->startOfMonth()->toDateString(), $mes->endOfMonth()->toDateString()];
        }

        return [
            $desde->addMonthsNoOverflow($meses)->toDateString(),
            $hasta->addMonthsNoOverflow($meses)->toDateString(),
        ];
    }

    /**
     * Los datos de una factura tal como los carga el formulario.
     *
     * @return array{punto_de_venta_id: int|null, cliente_id: int|null, concepto: int, fecha_emision: string, fecha_servicio_desde: string|null, fecha_servicio_hasta: string|null, fecha_vto_pago: string|null, observaciones: string|null, items: list<array{descripcion: string, cantidad: string, unidad: string|null, precio_unitario: string}>}
     */
    private function editable(Comprobante $comprobante): array
    {
        return [
            'punto_de_venta_id' => $comprobante->punto_de_venta_id,
            'cliente_id' => $comprobante->cliente_id,
            'concepto' => $comprobante->concepto->value,
            'fecha_emision' => $comprobante->fecha_emision->toDateString(),
            'fecha_servicio_desde' => $comprobante->fecha_servicio_desde?->toDateString(),
            'fecha_servicio_hasta' => $comprobante->fecha_servicio_hasta?->toDateString(),
            'fecha_vto_pago' => $comprobante->fecha_vto_pago?->toDateString(),
            'observaciones' => $comprobante->observaciones,
            'enviar_al_autorizar' => $comprobante->enviar_al_autorizar,
            'items' => array_values($comprobante->items->map(fn (ComprobanteItem $item): array => [
                'descripcion' => $item->descripcion,
                'cantidad' => rtrim(rtrim($item->cantidad, '0'), '.'),
                'unidad' => $item->unidad,
                'precio_unitario' => Dinero::aDecimal($item->precio_unitario),
            ])->all()),
        ];
    }

    public function update(ComprobanteGuardarRequest $peticion, Comprobante $comprobante): RedirectResponse
    {
        $this->armador->guardar($this->emisorDe($peticion), $peticion->datos(), $comprobante);

        if ($peticion->boolean('emitir')) {
            return $this->emitirYAvisar($comprobante->refresh());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Se guardaron los cambios.']);

        return to_route('comprobantes.show', $comprobante);
    }

    public function emitir(Comprobante $comprobante): RedirectResponse
    {
        $this->authorize('emitir', $comprobante);

        return $this->emitirYAvisar($comprobante);
    }

    public function destroy(Comprobante $comprobante): RedirectResponse
    {
        $this->authorize('delete', $comprobante);
        $comprobante->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Se borró el borrador.']);

        return to_route('comprobantes.index');
    }

    /**
     * Pide el CAE y avisa el resultado. El estado del comprobante ya quedó
     * guardado tanto si salió bien como si no: siempre se vuelve al detalle.
     */
    public function enviar(EnviarComprobanteRequest $peticion, Comprobante $comprobante): RedirectResponse
    {
        $envio = $this->enviador->enviar(
            $comprobante,
            $peticion->destinatarios(),
            $peticion->boolean('copia_a_mi'),
            (string) $peticion->validated('texto'),
        );

        Inertia::flash('toast', $envio->enviado
            ? ['type' => 'success', 'message' => 'Factura enviada a '.implode(', ', $envio->destinatarios).'.']
            : ['type' => 'error', 'message' => 'No se pudo enviar el email. Probá de nuevo en un rato.']);

        return to_route('comprobantes.show', $comprobante);
    }

    /**
     * Lo que necesita el diálogo "Enviar por email" y el historial de envíos.
     *
     * @return array<string, mixed>
     */
    private function datosDelEmail(Comprobante $comprobante): array
    {
        $propio = $comprobante->emisor->usuario->email;
        $deCliente = $comprobante->cliente === null ? null : $comprobante->cliente->email;
        $homologacion = $comprobante->ambiente === Ambiente::Homologacion;

        return [
            // En homologación, solo a la cuenta: una factura de prueba no sale de acá.
            'para' => $homologacion ? $propio : (string) $deCliente,
            'solo_propio' => $homologacion,
            'propio' => $propio,
            'texto' => $this->textoDelEmail->para($comprobante),
            'envios' => $comprobante->envios->map(fn (EnvioDeComprobante $envio): array => [
                'id' => $envio->id,
                'fecha' => $envio->created_at?->toIso8601String(),
                'destinatarios' => $envio->destinatarios,
                'con_copia' => $envio->con_copia,
                'enviado' => $envio->enviado,
            ])->all(),
        ];
    }

    private function emitirYAvisar(Comprobante $comprobante): RedirectResponse
    {
        // Hasta cuatro llamadas a ARCA en serie (ticket, último, CAE, consulta).
        set_time_limit(120);

        try {
            $this->emisorDeComprobantes->emitir($comprobante);
            Inertia::flash('toast', $this->avisoDeAutorizacion($comprobante));
        } catch (ExcepcionDeArca $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return to_route('comprobantes.show', $comprobante);
    }

    /**
     * El toast de una factura recién autorizada, con lo que pasó con el email
     * si estaba la casilla de enviarla (EnviarAlAutorizar).
     *
     * @return array{type: string, message: string}
     */
    private function avisoDeAutorizacion(Comprobante $comprobante): array
    {
        $autorizada = "Factura {$comprobante->numeroCompleto()} autorizada por ARCA. CAE {$comprobante->cae}.";
        $envio = $comprobante->enviar_al_autorizar ? $comprobante->envios()->first() : null;

        return match (true) {
            $envio === null => ['type' => 'success', 'message' => $autorizada],
            $envio->enviado => ['type' => 'success', 'message' => "{$autorizada} Se envió a ".implode(', ', $envio->destinatarios).'.'],
            default => ['type' => 'warning', 'message' => "{$autorizada} No se pudo enviar el email: reintentá desde el detalle."],
        };
    }

    /** @return array<string, mixed> */
    private function datosDelFormulario(Emisor $emisor): array
    {
        $hoy = (new RelojFiscal)->hoy();

        return [
            'puntosDeVenta' => $emisor->puntosDeVenta()
                ->where('ambiente', $emisor->ambiente->value)
                ->where('activo', true)
                ->orderBy('numero')
                ->get()
                ->map(fn (PuntoDeVenta $p): array => ['id' => $p->id, 'etiqueta' => $p->numeroFormateado().($p->descripcion ? " · {$p->descripcion}" : '')])
                ->all(),
            'clientes' => Cliente::query()
                ->where('emisor_id', $emisor->id)
                ->orderBy('nombre')
                ->limit(1000)
                ->get()
                ->map(fn (Cliente $c): array => [
                    'id' => $c->id,
                    'etiqueta' => ($c->alias ? "{$c->nombre} ({$c->alias})" : $c->nombre)." · {$c->documentoFormateado()}",
                    // El formulario la completa con el período y el total.
                    'plantilla' => $c->plantilla_descripcion,
                    // Para la casilla "Enviarla a … cuando ARCA la autorice".
                    'email' => $c->email,
                ])
                ->all(),
            'conceptos' => array_map(
                fn (Concepto $c): array => ['value' => $c->value, 'etiqueta' => $c->etiqueta(), 'con_servicios' => $c->exigeFechasDeServicio()],
                Concepto::cases(),
            ),
            'hoy' => $hoy->toDateString(),
            'inicioDeMes' => $hoy->startOfMonth()->toDateString(),
            'finDeMes' => $hoy->endOfMonth()->toDateString(),
            'topeConsumidorFinal' => $emisor->topeConsumidorFinal(),
            'ambiente' => $emisor->ambiente->etiqueta(),
            'esProduccion' => $emisor->ambiente === Ambiente::Produccion,
        ];
    }

    /** @return array<string, mixed> */
    private function detalle(Comprobante $comprobante): array
    {
        return [
            'id' => $comprobante->id,
            'tipo' => $comprobante->tipo->etiqueta(),
            'numero' => $comprobante->numeroCompleto(),
            'tiene_numero' => $comprobante->numero !== null,
            'estado' => $comprobante->estado->value,
            'estado_etiqueta' => $comprobante->estado->etiqueta(),
            'ambiente' => $comprobante->ambiente->etiqueta(),
            'concepto' => $comprobante->concepto->etiqueta(),
            'fecha_emision' => $comprobante->fecha_emision->toDateString(),
            'fecha_servicio_desde' => $comprobante->fecha_servicio_desde?->toDateString(),
            'fecha_servicio_hasta' => $comprobante->fecha_servicio_hasta?->toDateString(),
            'fecha_vto_pago' => $comprobante->fecha_vto_pago?->toDateString(),
            'receptor' => [
                'nombre' => $comprobante->receptor_nombre,
                'documento' => $comprobante->esConsumidorFinalSinIdentificar()
                    ? null
                    : $comprobante->receptor_tipo_documento->etiqueta().' '.$comprobante->receptor_tipo_documento->formatear($comprobante->receptor_numero_documento),
                'condicion_iva' => $comprobante->receptor_condicion_iva->etiqueta(),
                'domicilio' => $comprobante->receptor_domicilio,
            ],
            'items' => $comprobante->items->map(fn (ComprobanteItem $item): array => [
                'descripcion' => $item->descripcion,
                'cantidad' => rtrim(rtrim($item->cantidad, '0'), '.'),
                'unidad' => $item->unidad,
                'precio_unitario' => Dinero::formatear($item->precio_unitario),
                'importe' => Dinero::formatear($item->importe),
            ])->all(),
            'total' => Dinero::formatear($comprobante->importe_total),
            'cae' => $comprobante->cae,
            'cae_vencimiento' => $comprobante->cae_vencimiento?->toDateString(),
            'observaciones' => $comprobante->observaciones,
            'respuesta_arca' => $comprobante->respuesta_arca,
        ];
    }

    private function emisorDe(Request $peticion): Emisor
    {
        $usuario = $peticion->user();
        assert($usuario instanceof User && $usuario->emisor !== null);

        return $usuario->emisor;
    }
}
