<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Arca\Ambiente;
use App\Enums\Arca\EstadoCertificado;
use App\Support\Arca\Cuit;
use Carbon\CarbonImmutable;
use Database\Factories\EmisorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Quién factura: el perfil fiscal de una cuenta. Todo lo que es del emisor
 * (certificados, puntos de venta, clientes, comprobantes) cuelga de acá, y
 * las Policies comparan contra `usuario_id`.
 *
 * @property int $id
 * @property int $usuario_id
 * @property string $cuit
 * @property string $razon_social
 * @property string $domicilio_fiscal
 * @property string|null $ingresos_brutos
 * @property CarbonImmutable $inicio_actividades
 * @property Ambiente $ambiente
 * @property int|null $tope_consumidor_final
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read User $usuario
 */
#[Fillable(['cuit', 'razon_social', 'domicilio_fiscal', 'ingresos_brutos', 'inicio_actividades', 'ambiente'])]
class Emisor extends Model
{
    /** @use HasFactory<EmisorFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'emisores';

    protected function casts(): array
    {
        return [
            'inicio_actividades' => 'date',
            'ambiente' => Ambiente::class,
            'tope_consumidor_final' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return HasMany<Certificado, $this> */
    public function certificados(): HasMany
    {
        return $this->hasMany(Certificado::class);
    }

    /** @return HasMany<PuntoDeVenta, $this> */
    public function puntosDeVenta(): HasMany
    {
        return $this->hasMany(PuntoDeVenta::class);
    }

    /**
     * El certificado con que se firma hoy en el ambiente pedido (por
     * defecto, el del emisor). Null si todavía no hay uno vigente.
     */
    public function certificadoVigente(?Ambiente $ambiente = null): ?Certificado
    {
        return $this->certificados()
            ->where('ambiente', ($ambiente ?? $this->ambiente)->value)
            ->where('estado', EstadoCertificado::Vigente->value)
            ->latest('id')
            ->first();
    }

    /** El que espera el certificado de ARCA en ese ambiente, si hay. */
    public function certificadoPendiente(?Ambiente $ambiente = null): ?Certificado
    {
        return $this->certificados()
            ->where('ambiente', ($ambiente ?? $this->ambiente)->value)
            ->where('estado', EstadoCertificado::EsperandoCertificado->value)
            ->latest('id')
            ->first();
    }

    /** En centavos: el propio si lo cargó, si no el de la norma vigente. */
    public function topeConsumidorFinal(): int
    {
        return $this->tope_consumidor_final ?? (int) config('arca.tope_consumidor_final');
    }

    public function cuitFormateado(): string
    {
        return Cuit::formatear($this->cuit);
    }
}
