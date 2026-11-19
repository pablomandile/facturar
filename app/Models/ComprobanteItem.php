<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una línea de la factura. ARCA no recibe los ítems (WSFEv1 solo pide
 * totales): van al PDF. El importe lo calcula `Comprobante::recalcular()`.
 *
 * @property int $id
 * @property int $comprobante_id
 * @property int $orden
 * @property string $descripcion
 * @property string $cantidad
 * @property string|null $unidad
 * @property int $precio_unitario
 * @property int $importe
 * @property-read Comprobante $comprobante
 */
#[Fillable(['orden', 'descripcion', 'cantidad', 'unidad', 'precio_unitario', 'importe'])]
class ComprobanteItem extends Model
{
    /** Caracteres de una descripción: alcanza para varias líneas. */
    public const int DESCRIPCION_MAXIMA = 1000;

    public $timestamps = false;

    protected $table = 'comprobante_items';

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'cantidad' => 'decimal:4',
            'precio_unitario' => 'integer',
            'importe' => 'integer',
        ];
    }

    /** @return BelongsTo<Comprobante, $this> */
    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }

    /**
     * Cantidad por precio, en centavos, redondeado al centavo. La cantidad
     * tiene hasta 4 decimales (0,5 kg; 1,25 h).
     */
    public static function importeDe(string $cantidad, int $precioUnitario): int
    {
        return (int) round((float) $cantidad * $precioUnitario);
    }
}
