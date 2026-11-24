<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un envío de una factura por email, salga bien o mal. Lo escribe solo
 * `EnviadorDeFacturas`. Vive lo que vive la factura: se va con ella (FK en
 * cascada), y con la cuenta.
 *
 * @property int $id
 * @property int $comprobante_id
 * @property list<string> $destinatarios
 * @property bool $con_copia
 * @property string|null $asunto
 * @property string $texto
 * @property bool $enviado
 * @property string|null $error
 * @property CarbonImmutable|null $created_at
 * @property-read Comprobante $comprobante
 */
class EnvioDeComprobante extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'envios_de_comprobantes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'destinatarios' => 'array',
            'con_copia' => 'boolean',
            'enviado' => 'boolean',
        ];
    }

    /** @return BelongsTo<Comprobante, $this> */
    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }
}
