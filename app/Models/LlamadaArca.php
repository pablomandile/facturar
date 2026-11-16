<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Arca\Ambiente;
use App\Enums\Arca\ResultadoLlamada;
use App\Enums\Arca\ServicioArca;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una llamada a ARCA, con su pedido y su respuesta (token y sign tachados).
 * La escribe solo `RegistradorDeLlamadas`. Se purga sola con `model:prune`.
 *
 * @property int $id
 * @property int $emisor_id
 * @property int|null $comprobante_id
 * @property Ambiente $ambiente
 * @property ServicioArca $servicio
 * @property string $metodo
 * @property string|null $pedido
 * @property string|null $respuesta
 * @property ResultadoLlamada $resultado
 * @property string|null $codigo
 * @property string|null $mensaje
 * @property int $duracion_ms
 * @property CarbonImmutable $created_at
 * @property-read Emisor $emisor
 */
class LlamadaArca extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $table = 'llamadas_arca';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ambiente' => Ambiente::class,
            'servicio' => ServicioArca::class,
            'resultado' => ResultadoLlamada::class,
            'duracion_ms' => 'integer',
        ];
    }

    /** @return BelongsTo<Emisor, $this> */
    public function emisor(): BelongsTo
    {
        return $this->belongsTo(Emisor::class);
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('arca.retencion_llamadas_dias')));
    }
}
