<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Arca\Ambiente;
use App\Enums\Arca\Concepto;
use App\Enums\Arca\CondicionIvaReceptor;
use App\Enums\Arca\TipoComprobante;
use App\Enums\Arca\TipoDocumento;
use App\Enums\EstadoComprobante;
use Carbon\CarbonImmutable;
use Database\Factories\ComprobanteFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Un comprobante (en la v1, una factura C).
 *
 * **Nada es fillable.** Los datos del formulario los vuelca
 * `ArmadorDeComprobantes`; el número, el estado y el CAE los pone solo
 * `EmisorDeComprobantes`. Un comprobante autorizado no se modifica: lo
 * impide `saving` acá abajo, por si algún camino se olvida de la Policy.
 *
 * @property int $id
 * @property int $emisor_id
 * @property int $punto_de_venta_id
 * @property int|null $cliente_id
 * @property Ambiente $ambiente
 * @property TipoComprobante $tipo
 * @property Concepto $concepto
 * @property int|null $numero
 * @property CarbonImmutable $fecha_emision
 * @property CarbonImmutable|null $fecha_servicio_desde
 * @property CarbonImmutable|null $fecha_servicio_hasta
 * @property CarbonImmutable|null $fecha_vto_pago
 * @property TipoDocumento $receptor_tipo_documento
 * @property string $receptor_numero_documento
 * @property string $receptor_nombre
 * @property CondicionIvaReceptor $receptor_condicion_iva
 * @property string|null $receptor_domicilio
 * @property int $importe_neto
 * @property int $importe_tributos
 * @property int $importe_total
 * @property string $moneda
 * @property string $cotizacion
 * @property EstadoComprobante $estado
 * @property string|null $cae
 * @property CarbonImmutable|null $cae_vencimiento
 * @property array{observaciones?: list<array{codigo: int, mensaje: string}>, errores?: list<array{codigo: int, mensaje: string}>, eventos?: list<array{codigo: int, mensaje: string}>, mensaje?: string}|null $respuesta_arca
 * @property int $intentos
 * @property CarbonImmutable|null $enviado_en
 * @property CarbonImmutable|null $autorizado_en
 * @property string|null $observaciones
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Emisor $emisor
 * @property-read PuntoDeVenta $puntoDeVenta
 * @property-read Cliente|null $cliente
 * @property-read Collection<int, ComprobanteItem> $items
 */
class Comprobante extends Model
{
    /** @use HasFactory<ComprobanteFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'comprobantes';

    /**
     * Los mismos defaults que la migración. Sin esto, un comprobante recién
     * creado tiene `moneda` null en memoria (el default lo pone la base) y
     * se mandaba a ARCA sin MonId.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'moneda' => 'PES',
        'cotizacion' => '1',
        'importe_tributos' => 0,
        'intentos' => 0,
        'estado' => 'borrador',
    ];

    protected function casts(): array
    {
        return [
            'ambiente' => Ambiente::class,
            'tipo' => TipoComprobante::class,
            'concepto' => Concepto::class,
            'numero' => 'integer',
            'fecha_emision' => 'date',
            'fecha_servicio_desde' => 'date',
            'fecha_servicio_hasta' => 'date',
            'fecha_vto_pago' => 'date',
            'receptor_tipo_documento' => TipoDocumento::class,
            'receptor_condicion_iva' => CondicionIvaReceptor::class,
            'importe_neto' => 'integer',
            'importe_tributos' => 'integer',
            'importe_total' => 'integer',
            'estado' => EstadoComprobante::class,
            'cae_vencimiento' => 'date',
            'respuesta_arca' => 'array',
            'intentos' => 'integer',
            'enviado_en' => 'datetime',
            'autorizado_en' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Comprobante $comprobante): void {
            if ($comprobante->exists && $comprobante->getOriginal('estado') === EstadoComprobante::Autorizado) {
                throw new LogicException("El comprobante {$comprobante->id} está autorizado por ARCA y no se puede modificar.");
            }
        });

        static::deleting(function (Comprobante $comprobante): void {
            if (! $comprobante->estado->esEditable()) {
                throw new LogicException("El comprobante {$comprobante->id} no se puede borrar en estado {$comprobante->estado->value}.");
            }
        });
    }

    /** @return BelongsTo<Emisor, $this> */
    public function emisor(): BelongsTo
    {
        return $this->belongsTo(Emisor::class);
    }

    /** @return BelongsTo<PuntoDeVenta, $this> */
    public function puntoDeVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoDeVenta::class);
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    /** @return HasMany<ComprobanteItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ComprobanteItem::class)->orderBy('orden');
    }

    /** Totales desde los ítems. Factura C: sin IVA discriminado, sin tributos en la v1. */
    public function recalcular(): void
    {
        $neto = (int) $this->items->sum('importe');

        $this->importe_neto = $neto;
        $this->importe_tributos = 0;
        $this->importe_total = $neto + $this->importe_tributos;
    }

    /** "00001-00000042", o el punto de venta solo si todavía no tiene número. */
    public function numeroCompleto(): string
    {
        $punto = $this->puntoDeVenta->numeroFormateado();

        return $this->numero === null ? "{$punto}-········" : $punto.'-'.str_pad((string) $this->numero, 8, '0', STR_PAD_LEFT);
    }

    public function esConsumidorFinalSinIdentificar(): bool
    {
        return $this->receptor_tipo_documento === TipoDocumento::ConsumidorFinal;
    }
}
