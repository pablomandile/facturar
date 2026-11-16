<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Arca\CondicionIvaReceptor;
use App\Enums\Arca\TipoDocumento;
use Carbon\CarbonImmutable;
use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un cliente del emisor: a quién se le factura. `emisor_id` no es fillable:
 * lo pone el controlador desde la cuenta.
 *
 * @property int $id
 * @property int $emisor_id
 * @property TipoDocumento $tipo_documento
 * @property string $numero_documento
 * @property string $nombre
 * @property CondicionIvaReceptor $condicion_iva
 * @property string|null $domicilio
 * @property string|null $email
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Emisor $emisor
 */
#[Fillable(['tipo_documento', 'numero_documento', 'nombre', 'condicion_iva', 'domicilio', 'email'])]
class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'clientes';

    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumento::class,
            'condicion_iva' => CondicionIvaReceptor::class,
        ];
    }

    /** @return BelongsTo<Emisor, $this> */
    public function emisor(): BelongsTo
    {
        return $this->belongsTo(Emisor::class);
    }

    public function documentoFormateado(): string
    {
        return $this->tipo_documento->etiqueta().' '.$this->tipo_documento->formatear($this->numero_documento);
    }

    /** @return array<string, mixed> */
    public function paraLista(): array
    {
        return [
            'id' => $this->id,
            'tipo_documento' => $this->tipo_documento->value,
            'numero_documento' => $this->numero_documento,
            'documento' => $this->documentoFormateado(),
            'nombre' => $this->nombre,
            'condicion_iva' => $this->condicion_iva->value,
            'condicion_iva_etiqueta' => $this->condicion_iva->etiqueta(),
            'domicilio' => $this->domicilio,
            'email' => $this->email,
        ];
    }
}
