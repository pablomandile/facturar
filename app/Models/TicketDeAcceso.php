<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Arca\Ambiente;
use App\Enums\Arca\ServicioArca;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El ticket de acceso de WSAA de un emisor, por ambiente y servicio. Ver la
 * migración: esta fila no se borra nunca. Lo maneja solo
 * `ProveedorDeTickets`.
 *
 * @property int $id
 * @property int $emisor_id
 * @property int|null $certificado_id
 * @property Ambiente $ambiente
 * @property ServicioArca $servicio
 * @property string|null $token
 * @property string|null $sign
 * @property string|null $unique_id
 * @property CarbonImmutable|null $generado_en
 * @property CarbonImmutable|null $expira_en
 * @property CarbonImmutable|null $bloqueado_hasta
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Emisor $emisor
 */
#[Hidden(['token', 'sign'])]
class TicketDeAcceso extends Model
{
    protected $table = 'tickets_de_acceso';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ambiente' => Ambiente::class,
            'servicio' => ServicioArca::class,
            'token' => 'encrypted',
            'sign' => 'encrypted',
            'generado_en' => 'datetime',
            'expira_en' => 'datetime',
            'bloqueado_hasta' => 'datetime',
        ];
    }

    /** @return BelongsTo<Emisor, $this> */
    public function emisor(): BelongsTo
    {
        return $this->belongsTo(Emisor::class);
    }

    /**
     * ¿Sirve para una llamada que empieza ahora? Tiene que ser del
     * certificado vigente y no vencer en los próximos minutos: si vence en el
     * medio de una emisión, ARCA la rechaza con un error de token.
     */
    public function sirvePara(Certificado $certificado, int $margenMinutos): bool
    {
        return $this->token !== null
            && $this->sign !== null
            && $this->certificado_id === $certificado->id
            && $this->expira_en !== null
            && $this->expira_en->isAfter(now()->addMinutes($margenMinutos));
    }

    public function estaBloqueado(Certificado $certificado): bool
    {
        return $this->certificado_id === $certificado->id
            && $this->bloqueado_hasta !== null
            && $this->bloqueado_hasta->isFuture();
    }
}
