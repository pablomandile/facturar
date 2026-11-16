<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Arca\Concepto;
use App\Enums\EstadoComprobante;
use App\Exceptions\Arca\ExcepcionDeArca;
use App\Http\Requests\ComprobanteGuardarRequest;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\ComprobanteItem;
use App\Models\Emisor;
use App\Models\PuntoDeVenta;
use App\Models\User;
use App\Services\Arca\EmisorDeComprobantes;
use App\Services\Arca\RelojFiscal;
use App\Services\ArmadorDeComprobantes;
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
        return Inertia::render('comprobantes/Formulario', [
            ...$this->datosDelFormulario($this->emisorDe($peticion)),
            'comprobante' => null,
        ]);
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
        $comprobante->load(['items', 'puntoDeVenta', 'emisor']);

        return Inertia::render('comprobantes/Detalle', [
            'comprobante' => $this->detalle($comprobante),
            'puede' => [
                'editar' => $comprobante->estado->esEditable(),
                'emitir' => $comprobante->estado->esEmitible(),
                'borrar' => $comprobante->estado->esEditable(),
            ],
        ]);
    }

    public function edit(Request $peticion, Comprobante $comprobante): Response
    {
        $this->authorize('update', $comprobante);
        $comprobante->load('items');

        return Inertia::render('comprobantes/Formulario', [
            ...$this->datosDelFormulario($this->emisorDe($peticion)),
            'comprobante' => [
                'id' => $comprobante->id,
                'punto_de_venta_id' => $comprobante->punto_de_venta_id,
                'cliente_id' => $comprobante->cliente_id,
                'concepto' => $comprobante->concepto->value,
                'fecha_emision' => $comprobante->fecha_emision->toDateString(),
                'fecha_servicio_desde' => $comprobante->fecha_servicio_desde?->toDateString(),
                'fecha_servicio_hasta' => $comprobante->fecha_servicio_hasta?->toDateString(),
                'fecha_vto_pago' => $comprobante->fecha_vto_pago?->toDateString(),
                'observaciones' => $comprobante->observaciones,
                'items' => $comprobante->items->map(fn (ComprobanteItem $item): array => [
                    'descripcion' => $item->descripcion,
                    'cantidad' => rtrim(rtrim($item->cantidad, '0'), '.'),
                    'unidad' => $item->unidad,
                    'precio_unitario' => Dinero::aDecimal($item->precio_unitario),
                ])->all(),
            ],
        ]);
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
    private function emitirYAvisar(Comprobante $comprobante): RedirectResponse
    {
        // Hasta cuatro llamadas a ARCA en serie (ticket, último, CAE, consulta).
        set_time_limit(120);

        try {
            $this->emisorDeComprobantes->emitir($comprobante);
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => "Factura {$comprobante->numeroCompleto()} autorizada por ARCA. CAE {$comprobante->cae}.",
            ]);
        } catch (ExcepcionDeArca $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return to_route('comprobantes.show', $comprobante);
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
                ->map(fn (Cliente $c): array => ['id' => $c->id, 'etiqueta' => "{$c->nombre} · {$c->documentoFormateado()}"])
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
