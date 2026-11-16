<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Arca\Ambiente;
use App\Enums\Arca\EstadoCertificado;
use Database\Factories\CertificadoFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * La clave y el certificado de un emisor para un ambiente.
 *
 * **Nada es fillable**: lo arma `GeneradorDeCertificados`, que es el único
 * lugar que sabe generar la clave y validar lo que devuelve ARCA. La clave
 * privada está oculta en la serialización y no hay ruta que la devuelva:
 * no sale de la base más que para firmar el pedido del ticket de acceso.
 *
 * @property int $id
 * @property int $emisor_id
 * @property Ambiente $ambiente
 * @property string $alias
 * @property string $clave_privada
 * @property string $csr
 * @property string|null $certificado
 * @property Carbon|null $valido_desde
 * @property Carbon|null $valido_hasta
 * @property EstadoCertificado $estado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Emisor $emisor
 */
#[Hidden(['clave_privada'])]
class Certificado extends Model
{
    /** @use HasFactory<CertificadoFactory> */
    use HasFactory;

    protected $table = 'certificados';

    protected function casts(): array
    {
        return [
            'ambiente' => Ambiente::class,
            'estado' => EstadoCertificado::class,
            'clave_privada' => 'encrypted',
            'valido_desde' => 'datetime',
            'valido_hasta' => 'datetime',
        ];
    }

    /** @return BelongsTo<Emisor, $this> */
    public function emisor(): BelongsTo
    {
        return $this->belongsTo(Emisor::class);
    }

    /** Días hasta que vence; negativo si ya venció; null si no hay certificado. */
    public function diasParaVencer(): ?int
    {
        if ($this->valido_hasta === null) {
            return null;
        }

        return (int) floor(now()->diffInDays($this->valido_hasta, false));
    }

    /** Nombre del archivo del pedido que se sube a ARCA. */
    public function nombreArchivoCsr(): string
    {
        return "{$this->alias}-{$this->emisor->cuit}-{$this->ambiente->value}.csr";
    }
}
