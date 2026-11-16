<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Arca\Ambiente;
use Database\Factories\PuntoDeVentaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un punto de venta de tipo "Web Services" dado de alta en ARCA. Es de un
 * ambiente: el 1 de homologación y el 1 de producción no tienen nada que ver.
 *
 * `emisor_id` y `ambiente` no son fillable: los pone el controlador desde el
 * emisor de la cuenta, nunca desde el formulario.
 *
 * @property int $id
 * @property int $emisor_id
 * @property Ambiente $ambiente
 * @property int $numero
 * @property string|null $descripcion
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Emisor $emisor
 */
#[Fillable(['numero', 'descripcion', 'activo'])]
class PuntoDeVenta extends Model
{
    /** @use HasFactory<PuntoDeVentaFactory> */
    use HasFactory;

    protected $table = 'puntos_de_venta';

    protected function casts(): array
    {
        return [
            'ambiente' => Ambiente::class,
            'numero' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Emisor, $this> */
    public function emisor(): BelongsTo
    {
        return $this->belongsTo(Emisor::class);
    }

    /** "00003": como va impreso en la factura. */
    public function numeroFormateado(): string
    {
        return str_pad((string) $this->numero, 5, '0', STR_PAD_LEFT);
    }
}
